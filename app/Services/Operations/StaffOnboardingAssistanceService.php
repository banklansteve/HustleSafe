<?php

namespace App\Services\Operations;

use App\Models\StaffOnboardingAssistanceRecord;
use App\Models\StaffOnboardingOutreach;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffOnboardingAssistanceService
{
    public function __construct(
        private readonly AdminActivityLogger $logger,
        private readonly StaffSupportMessagingService $messaging,
    ) {}

    public function listing(Request $request): array
    {
        $query = StaffOnboardingAssistanceRecord::query()
            ->with('user:id,name,email,avatar_url')
            ->whereNull('resolved_at')
            ->orderByDesc('staleness_score');

        if ($type = $request->input('user_type')) {
            $query->where('user_type', $type);
        }

        if ($scenario = $request->input('scenario')) {
            $query->where('scenario', $scenario);
        }

        if ($q = trim((string) $request->input('q', ''))) {
            $query->whereHas('user', fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        $items = $query->limit(200)->get()->map(fn (StaffOnboardingAssistanceRecord $r) => $this->row($r));

        return [
            'items' => $items,
            'scenarios' => collect(config('operations.onboarding_scenarios', []))->map(fn ($meta, $key) => [
                'key' => $key,
                'label' => $meta['label'] ?? Str::headline(str_replace('_', ' ', $key)),
            ])->values(),
            'filters' => [
                ['key' => '', 'label' => 'All types'],
                ['key' => 'client', 'label' => 'Clients'],
                ['key' => 'freelancer', 'label' => 'Freelancers'],
            ],
        ];
    }

    public function detail(StaffOnboardingAssistanceRecord $record): array
    {
        $record->load('user');
        $scenario = $record->scenario;
        $template = config("operations.onboarding_scenarios.{$scenario}", []);
        $body = str_replace(':name', $record->user?->name ?? 'there', $template['template_body'] ?? '');

        return [
            'record' => $this->row($record),
            'user' => $record->user ? ['id' => $record->user->id, 'name' => $record->user->name, 'email' => $record->user->email] : null,
            'flow_metadata' => $record->flow_metadata ?? [],
            'fields_completed' => $record->fields_completed ?? [],
            'template' => [
                'subject' => $template['template_subject'] ?? 'We are here to help',
                'body' => $body,
            ],
            'history' => StaffOnboardingOutreach::query()
                ->where('user_id', $record->user_id)
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (StaffOnboardingOutreach $row) => [
                    'scenario' => $row->scenario,
                    'status' => $row->status,
                    'contacted_at' => $row->contacted_at?->toIso8601String(),
                ]),
        ];
    }

    public function outreach(User $staff, StaffOnboardingAssistanceRecord $record, array $data, Request $request): void
    {
        $user = $record->user;
        abort_if($user === null, 404);

        StaffOnboardingOutreach::query()->updateOrCreate(
            ['user_id' => $user->id, 'scenario' => $record->scenario],
            [
                'status' => 'contacted',
                'friction_point' => $record->milestone_reached,
                'assigned_staff_id' => $staff->id,
                'contacted_by_staff_id' => $staff->id,
                'contacted_at' => now(),
                'context' => $record->flow_metadata ?? [],
            ],
        );

        $channel = $data['channel'] ?? 'both';
        if (in_array($channel, ['email', 'both'], true)) {
            $this->messaging->sendPanelEmail($staff, $user, [
                'subject' => $data['subject'],
                'body' => $data['body'],
                'channel' => $channel,
                'context' => 'onboarding_'.$record->scenario,
            ]);
        } else {
            $user->notify(new AdminUserMessageNotification($data['subject'], $data['body']));
        }

        $record->forceFill([
            'status' => 'contacted',
            'assigned_staff_id' => $staff->id,
            'contacted_at' => now(),
        ])->save();

        $this->logger->log($staff, 'operations.onboarding.outreach', StaffOnboardingAssistanceRecord::class, $record->id, $data, $request);
    }

    public function resolve(User $staff, StaffOnboardingAssistanceRecord $record, Request $request): void
    {
        $record->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'assigned_staff_id' => $staff->id,
        ])->save();

        $this->logger->log($staff, 'operations.onboarding.resolved', StaffOnboardingAssistanceRecord::class, $record->id, [], $request);
    }

    public function createTicket(User $staff, StaffOnboardingAssistanceRecord $record, array $data, Request $request): void
    {
        $user = $record->user;
        abort_if($user === null, 404);

        $this->messaging->createTicket($staff, [
            'user_id' => $user->id,
            'subject' => $data['subject'] ?? 'Onboarding assistance · '.$record->milestone_reached,
            'description' => $data['body'] ?? 'Follow-up from onboarding assistance queue.',
            'priority' => $data['priority'] ?? 'medium',
            'category' => 'onboarding',
        ]);

        $record->forceFill(['assigned_staff_id' => $staff->id])->save();
        $this->logger->log($staff, 'operations.onboarding.ticket', StaffOnboardingAssistanceRecord::class, $record->id, $data, $request);
    }

    private function row(StaffOnboardingAssistanceRecord $record): array
    {
        $label = config("operations.onboarding_scenarios.{$record->scenario}.label")
            ?? Str::headline(str_replace('_', ' ', $record->scenario));

        return [
            'id' => $record->id,
            'scenario' => $record->scenario,
            'scenario_label' => $label,
            'user_type' => $record->user_type,
            'user' => $record->user ? [
                'id' => $record->user->id,
                'name' => $record->user->name,
                'email' => $record->user->email,
            ] : null,
            'milestone_reached' => $record->milestone_reached,
            'staleness_score' => $record->staleness_score,
            'cycles_elapsed' => $record->cycles_elapsed,
            'status' => $record->status,
            'last_activity_at' => $record->last_activity_at?->toIso8601String(),
            'last_meaningful_action_at' => $record->last_meaningful_action_at?->toIso8601String(),
            'contacted_at' => $record->contacted_at?->toIso8601String(),
        ];
    }
}
