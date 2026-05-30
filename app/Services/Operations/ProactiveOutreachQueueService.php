<?php

namespace App\Services\Operations;

use App\Models\StaffProactiveOutreachItem;
use App\Models\StaffProactiveOutreachLog;
use App\Models\StaffResponseTemplate;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProactiveOutreachQueueService
{
    public function __construct(
        private readonly StaffResponseTemplateService $templates,
        private readonly StaffSupportMessagingService $messaging,
        private readonly AdminActivityLogger $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listing(Request $request): array
    {
        $query = StaffProactiveOutreachItem::query()
            ->with(['targetUser:id,name,email', 'quest:id,title,reference_code', 'assignedStaff:id,name'])
            ->whereNull('resolved_at')
            ->where(function ($q): void {
                $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
            })
            ->orderByDesc('priority_score')
            ->orderByDesc('detected_at');

        if ($situation = $request->input('situation')) {
            $query->where('situation_key', $situation);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        if ($assigned = $request->input('assigned')) {
            if ($assigned === 'me' && $request->user()) {
                $query->where('assigned_staff_id', $request->user()->id);
            } elseif ($assigned === 'unassigned') {
                $query->whereNull('assigned_staff_id');
            }
        }

        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($sub) use ($q): void {
                $sub->whereHas('targetUser', fn ($u) => $u
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('quest', fn ($quest) => $quest
                        ->where('title', 'like', "%{$q}%")
                        ->orWhere('reference_code', 'like', "%{$q}%"));
            });
        }

        $items = $query->limit(250)->get()->map(fn (StaffProactiveOutreachItem $item) => $this->row($item));

        return [
            'items' => $items,
            'situations' => $this->situationFilters(),
            'statuses' => [
                ['key' => '', 'label' => 'All statuses'],
                ['key' => 'open', 'label' => 'Open'],
                ['key' => 'in_progress', 'label' => 'In progress'],
                ['key' => 'contacted', 'label' => 'Contacted'],
            ],
            'priorities' => [
                ['key' => '', 'label' => 'All priorities'],
                ['key' => 'urgent', 'label' => 'Urgent'],
                ['key' => 'high', 'label' => 'High'],
                ['key' => 'medium', 'label' => 'Medium'],
                ['key' => 'low', 'label' => 'Low'],
            ],
            'counts' => $this->openCounts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(StaffProactiveOutreachItem $item): array
    {
        $item->load([
            'targetUser:id,name,email',
            'quest.client:id,name,email',
            'quest:id,title,reference_code,client_id,uuid',
            'offer.freelancer:id,name,email',
            'dispute:id,uuid,reason,status,created_at',
            'assignedStaff:id,name',
            'logs.staff:id,name',
            'logs.template:id,slug,title',
        ]);

        $situationKey = $item->situation_key;
        $suggestedSlug = $item->suggested_template_slug;
        $template = $suggestedSlug ? $this->templates->findBySlug($suggestedSlug) : null;

        $availableTemplates = StaffResponseTemplate::query()
            ->where('is_active', true)
            ->where('situation_key', $situationKey)
            ->orderBy('sort_order')
            ->get();

        $defaultRender = $template
            ? $this->templates->renderForItem($template, $item)
            : ['subject' => '', 'body' => ''];

        return [
            'item' => $this->row($item, detailed: true),
            'user' => $item->targetUser ? [
                'id' => $item->targetUser->id,
                'name' => $item->targetUser->name,
                'email' => $item->targetUser->email,
            ] : null,
            'quest' => $item->quest ? [
                'id' => $item->quest->id,
                'uuid' => $item->quest->uuid,
                'title' => $item->quest->title,
                'reference_code' => $item->quest->reference_code,
                'client' => $item->quest->client?->only(['id', 'name', 'email']),
            ] : null,
            'context' => $item->context ?? [],
            'situation' => config("operations.proactive_outreach.situations.{$situationKey}", []),
            'templates' => $availableTemplates->map(fn (StaffResponseTemplate $t) => [
                ...$this->templates->row($t),
                'preview' => $this->templates->renderForItem($t, $item),
            ]),
            'default_template' => $template ? [
                'slug' => $template->slug,
                'subject' => $defaultRender['subject'],
                'body' => $defaultRender['body'],
            ] : null,
            'history' => $item->logs->map(fn (StaffProactiveOutreachLog $log) => [
                'id' => $log->id,
                'subject' => $log->subject,
                'channel' => $log->channel,
                'staff' => $log->staff?->name,
                'template' => $log->template?->title,
                'sent_at' => $log->sent_at?->toIso8601String(),
            ]),
        ];
    }

    public function assign(User $staff, StaffProactiveOutreachItem $item, Request $request): void
    {
        $item->forceFill([
            'assigned_staff_id' => $staff->id,
            'status' => $item->status === 'open' ? 'in_progress' : $item->status,
        ])->save();

        $this->logger->log($staff, 'operations.outreach.assign', StaffProactiveOutreachItem::class, $item->id, [], $request);
    }

    public function snooze(User $staff, StaffProactiveOutreachItem $item, array $data, Request $request): void
    {
        $days = (int) ($data['days'] ?? 3);

        $item->forceFill([
            'snoozed_until' => now()->addDays(max(1, min($days, 30))),
            'assigned_staff_id' => $item->assigned_staff_id ?? $staff->id,
        ])->save();

        $this->logger->log($staff, 'operations.outreach.snooze', StaffProactiveOutreachItem::class, $item->id, ['days' => $days], $request);
    }

    public function resolve(User $staff, StaffProactiveOutreachItem $item, array $data, Request $request): void
    {
        $item->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_note' => $data['note'] ?? null,
            'assigned_staff_id' => $item->assigned_staff_id ?? $staff->id,
        ])->save();

        $this->logger->log($staff, 'operations.outreach.resolve', StaffProactiveOutreachItem::class, $item->id, $data, $request);
    }

    public function outreach(User $staff, StaffProactiveOutreachItem $item, array $data, Request $request): void
    {
        $user = $item->targetUser;
        abort_if($user === null, 404);

        $template = null;
        if (! empty($data['template_id'])) {
            $template = StaffResponseTemplate::query()->find($data['template_id']);
        } elseif (! empty($data['template_slug'])) {
            $template = $this->templates->findBySlug($data['template_slug']);
        }

        $channel = $data['channel'] ?? 'both';
        $subject = $data['subject'];
        $body = $data['body'];

        if (in_array($channel, ['email', 'both'], true)) {
            $this->messaging->sendPanelEmail($staff, $user, [
                'subject' => $subject,
                'body' => $body,
                'channel' => $channel,
                'context' => 'proactive_outreach_'.$item->situation_key,
            ]);
        } else {
            $user->notify(new AdminUserMessageNotification($subject, $body));
        }

        StaffProactiveOutreachLog::query()->create([
            'outreach_item_id' => $item->id,
            'staff_user_id' => $staff->id,
            'template_id' => $template?->id,
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'sent_at' => now(),
        ]);

        $item->forceFill([
            'status' => 'contacted',
            'assigned_staff_id' => $staff->id,
            'last_outreach_at' => now(),
        ])->save();

        $this->logger->log($staff, 'operations.outreach.contact', StaffProactiveOutreachItem::class, $item->id, [
            'template_id' => $template?->id,
            'channel' => $channel,
        ], $request);
    }

    /**
     * @return array<string, int>
     */
    public function openCounts(): array
    {
        $base = StaffProactiveOutreachItem::query()
            ->whereNull('resolved_at')
            ->where(function ($q): void {
                $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
            });

        return [
            'total' => (clone $base)->count(),
            'urgent' => (clone $base)->where('priority', 'urgent')->count(),
            'unassigned' => (clone $base)->whereNull('assigned_staff_id')->count(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function situationFilters(): array
    {
        $options = collect(config('operations.proactive_outreach.situations', []))
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'] ?? Str::headline(str_replace('_', ' ', $key)),
            ])
            ->values()
            ->all();

        return array_merge([['key' => '', 'label' => 'All situations']], $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(StaffProactiveOutreachItem $item, bool $detailed = false): array
    {
        $meta = config("operations.proactive_outreach.situations.{$item->situation_key}", []);

        $row = [
            'id' => $item->id,
            'uuid' => $item->uuid,
            'situation_key' => $item->situation_key,
            'situation_label' => $meta['label'] ?? Str::headline(str_replace('_', ' ', $item->situation_key)),
            'situation_hint' => $meta['hint'] ?? '',
            'status' => $item->status,
            'priority' => $item->priority,
            'priority_score' => $item->priority_score,
            'user' => $item->targetUser ? [
                'id' => $item->targetUser->id,
                'name' => $item->targetUser->name,
                'email' => $item->targetUser->email,
            ] : null,
            'quest' => $item->quest ? [
                'id' => $item->quest->id,
                'title' => $item->quest->title,
                'reference_code' => $item->quest->reference_code,
            ] : null,
            'assigned_staff' => $item->assignedStaff?->name,
            'detected_at' => $item->detected_at?->toIso8601String(),
            'last_outreach_at' => $item->last_outreach_at?->toIso8601String(),
            'snoozed_until' => $item->snoozed_until?->toIso8601String(),
            'suggested_template_slug' => $item->suggested_template_slug,
        ];

        if ($detailed) {
            $row['context'] = $item->context ?? [];
            $row['resolution_note'] = $item->resolution_note;
        }

        return $row;
    }
}
