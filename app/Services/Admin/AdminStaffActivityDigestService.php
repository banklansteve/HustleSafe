<?php

namespace App\Services\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminStaffActivityDigestService
{
    private const STAFF_ROLES = ['admin', 'super_admin'];

    public function payload(Request $request): array
    {
        [$start, $end, $rangeMeta] = $this->window($request);
        $staffId = $request->integer('admin_id') ?: null;
        $sort = (string) $request->input('sort', 'newest');
        if (! in_array($sort, ['newest', 'oldest'], true)) {
            $sort = 'newest';
        }

        $logs = $this->staffLogs($start, $end, $staffId, $sort)->get();
        $staff = $this->staffMembers();

        return [
            'filters' => [
                'date' => $rangeMeta['anchor_date'],
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
                'range' => $rangeMeta['range'],
                'admin_id' => $staffId,
                'sort' => $sort,
            ],
            'range_label' => $rangeMeta['label'],
            'admins' => $staff,
            'summary' => [
                ['key' => 'total_actions', 'label' => 'Staff actions', 'value' => $logs->count(), 'tone' => 'blue'],
                ['key' => 'staff_active', 'label' => 'Staff with activity', 'value' => $logs->pluck('actor_user_id')->unique()->filter()->count(), 'tone' => 'indigo'],
            ],
            'timeline' => $logs->map(fn (AdminActivityLog $log) => $this->timelineRow($log))->values()->all(),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: array{range: string, anchor_date: string, label: string}}
     */
    private function window(Request $request): array
    {
        $range = (string) $request->input('range', 'day');
        if (! in_array($range, ['day', 'custom'], true)) {
            $range = 'day';
        }

        $tz = 'Africa/Lagos';

        if ($range === 'custom') {
            $fromRaw = (string) $request->input('date_from', '');
            $toRaw = (string) $request->input('date_to', '');

            try {
                $start = CarbonImmutable::parse($fromRaw, $tz)->startOfDay();
                $end = CarbonImmutable::parse($toRaw, $tz)->endOfDay();
            } catch (\Throwable) {
                $start = CarbonImmutable::now($tz)->startOfDay();
                $end = CarbonImmutable::now($tz)->endOfDay();
            }

            if ($end->lessThan($start)) {
                [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
            }

            return [
                $start,
                $end,
                [
                    'range' => 'custom',
                    'anchor_date' => $start->toDateString(),
                    'label' => $start->toDateString().' → '.$end->toDateString(),
                ],
            ];
        }

        $anchor = $this->date($request);

        return [
            $anchor->startOfDay(),
            $anchor->endOfDay(),
            [
                'range' => 'day',
                'anchor_date' => $anchor->toDateString(),
                'label' => $anchor->isToday() ? 'Today · '.$anchor->format('l, j M Y') : $anchor->format('l, j M Y'),
            ],
        ];
    }

    private function date(Request $request): CarbonImmutable
    {
        $value = (string) $request->input('date', now('Africa/Lagos')->toDateString());

        try {
            return CarbonImmutable::parse($value, 'Africa/Lagos');
        } catch (\Throwable) {
            return CarbonImmutable::now('Africa/Lagos');
        }
    }

    private function staffLogs(CarbonImmutable $start, CarbonImmutable $end, ?int $staffId, string $sort): Builder
    {
        return AdminActivityLog::query()
            ->with(['actor:id,name,email,avatar_url', 'actor.role:id,slug'])
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('actor.role', fn (Builder $query) => $query->whereIn('slug', self::STAFF_ROLES))
            ->when($staffId, fn (Builder $query) => $query->where('actor_user_id', $staffId))
            ->when($sort === 'oldest', fn (Builder $query) => $query->orderBy('created_at'), fn (Builder $query) => $query->orderByDesc('created_at'))
            ->limit(500);
    }

    private function staffMembers(): Collection
    {
        return User::query()
            ->whereHas('role', fn (Builder $query) => $query->whereIn('slug', self::STAFF_ROLES))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar_url'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?: $user->email,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function timelineRow(AdminActivityLog $log): array
    {
        $at = $log->created_at?->timezone('Africa/Lagos');

        return [
            'id' => 'log-'.$log->id,
            'at' => $at?->toIso8601String(),
            'at_label' => $at ? $at->format('D, j M Y · g:i A').' WAT' : '—',
            'actor' => $log->actor?->name ?? $log->actor?->email ?? 'Unknown staff',
            'actor_email' => $log->actor?->email,
            'actor_role' => $log->actor?->role?->slug,
            'title' => $this->actionLabel($log->action),
            'action' => $log->action,
            'summary' => $this->propertiesSummary($log->properties ?? []),
            'subject' => $this->subjectLabel($log->subject_type, $log->subject_id, $log->properties ?? []),
            'tone' => $this->toneFor($log->action),
            'meta' => collect([$log->ip_address, $this->device($log->user_agent)])->filter()->join(' · '),
        ];
    }

    private function actionLabel(string $action): string
    {
        $map = [
            'conversation_monitoring.user_warned' => 'Conversation monitoring · user warned',
            'conversation_monitoring.user_suspended' => 'Conversation monitoring · user suspended',
            'conversation_monitoring.user_banned' => 'Conversation monitoring · user banned',
            'conversation_monitoring.assigned' => 'Conversation monitoring · case assigned',
            'conversation_monitoring.escalated' => 'Conversation monitoring · escalated to super admin',
            'conversation_monitoring.dismissed' => 'Conversation monitoring · case dismissed',
            'conversation_monitoring.systematic_resolved' => 'Conversation monitoring · systematic escalation resolved',
            'support_ticket.opened' => 'Support ticket opened',
            'support_ticket.status_changed' => 'Support ticket updated',
            'support_chat.claimed' => 'Live support session claimed',
            'support_chat.reply' => 'Live support reply sent',
            'customer_support.reassigned' => 'Support ticket reassigned',
            'customer_support.closed' => 'Support ticket closed',
            'verification.approved' => 'Verification approved',
            'verification.rejected' => 'Verification rejected',
            'verification.requested_info' => 'Verification · info requested',
            'operations.user.contacted' => 'User contacted by staff',
            'operations.user.note_created' => 'Staff note on user account',
            'operations.user.flagged_for_review' => 'User flagged for review',
            'operations.user.unsuspended' => 'User unsuspended',
            'admin.quest.status_changed' => 'Quest status changed',
            'admin.quest.flag_created' => 'Quest flagged by staff',
        ];

        return $map[$action] ?? Str::of($action)->replace(['.', '_', '-'], ' ')->headline()->toString();
    }

    private function subjectLabel(?string $type, ?int $id, array $properties): string
    {
        if (isset($properties['target_user_email'])) {
            return 'User · '.$properties['target_user_email'];
        }
        if (isset($properties['target_user_name'])) {
            return 'User · '.$properties['target_user_name'];
        }
        if (isset($properties['user_email'])) {
            return 'User · '.$properties['user_email'];
        }
        if (! $type) {
            return 'Platform';
        }

        return class_basename($type).' #'.($id ?: '—');
    }

    private function toneFor(string $action): string
    {
        if (str_contains($action, 'warn') || str_contains($action, 'suspend') || str_contains($action, 'ban') || str_contains($action, 'flag')) {
            return 'overdue';
        }
        if (str_contains($action, 'approved') || str_contains($action, 'resolved') || str_contains($action, 'closed')) {
            return 'resolved';
        }
        if (str_contains($action, 'opened') || str_contains($action, 'assigned') || str_contains($action, 'claimed') || str_contains($action, 'escalated')) {
            return 'pending';
        }

        return 'neutral';
    }

    private function propertiesSummary(array $properties): string
    {
        $preferred = [
            'note',
            'reason',
            'resolution_note',
            'description',
            'message',
            'status',
            'target_user_name',
            'quest_title',
            'template_slug',
        ];

        foreach ($preferred as $key) {
            $value = $properties[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 220);
            }
        }

        if ($properties === []) {
            return 'Staff action recorded.';
        }

        $pairs = collect($properties)
            ->reject(fn ($value, $key) => in_array($key, ['ip', 'user_agent'], true) || is_array($value))
            ->take(4)
            ->map(fn ($value, $key) => $this->label((string) $key).': '.Str::limit((string) $value, 80))
            ->join(' · ');

        return $pairs !== '' ? $pairs : 'Staff action recorded.';
    }

    private function label(string $value): string
    {
        return Str::of($value)->replace(['.', '_', '-'], ' ')->headline()->toString();
    }

    private function device(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        return Str::limit($userAgent, 52);
    }
}
