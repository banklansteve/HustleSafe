<?php

namespace App\Services\Admin;

use App\Models\AdminActivityFeedEvent;
use App\Models\AdminActivityLog;
use App\Models\AdminTask;
use App\Models\QuestDispute;
use App\Models\User;
use App\Models\UserVerification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminStaffActivityDigestService
{
    public function payload(Request $request): array
    {
        $date = $this->date($request);
        $start = $date->startOfDay();
        $end = $date->endOfDay();
        $actorId = $request->integer('admin_id') ?: null;

        $logs = $this->logs($start, $end, $actorId)->get();
        $events = $this->feedEvents($start, $end, $actorId)->get();
        $admins = $this->admins();

        return [
            'filters' => [
                'date' => $date->toDateString(),
                'admin_id' => $actorId,
            ],
            'admins' => $admins,
            'summary' => $this->summary($logs, $events, $start, $end, $actorId),
            'categories' => $this->categories($logs, $events, $start, $end, $actorId),
            'staff' => $this->staffRows($logs, $events, $admins),
            'timeline' => $this->timeline($logs, $events),
            'attention' => $this->attention($start, $end, $actorId),
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

    private function logs(CarbonImmutable $start, CarbonImmutable $end, ?int $actorId = null): Builder
    {
        return AdminActivityLog::query()
            ->with('actor:id,name,email,avatar_url')
            ->whereBetween('created_at', [$start, $end])
            ->when($actorId, fn (Builder $query) => $query->where('actor_user_id', $actorId))
            ->latest('created_at');
    }

    private function feedEvents(CarbonImmutable $start, CarbonImmutable $end, ?int $actorId = null): Builder
    {
        if (! Schema::hasTable('admin_activity_feed_events')) {
            return AdminActivityFeedEvent::query()->whereRaw('1 = 0');
        }

        return AdminActivityFeedEvent::query()
            ->with('actor:id,name,email,avatar_url')
            ->whereBetween('occurred_at', [$start, $end])
            ->when($actorId, fn (Builder $query) => $query->where('actor_user_id', $actorId))
            ->latest('occurred_at');
    }

    private function summary(Collection $logs, Collection $events, CarbonImmutable $start, CarbonImmutable $end, ?int $actorId): array
    {
        return [
            ['key' => 'daily_activity', 'label' => 'Daily activity', 'value' => $logs->count() + $events->count(), 'tone' => 'blue'],
            ['key' => 'resolved', 'label' => 'Resolved today', 'value' => $this->resolvedLogs($logs)->count() + $this->resolvedEvents($events)->count() + $this->resolvedDisputes($start, $end, $actorId)->count(), 'tone' => 'green'],
            ['key' => 'pending', 'label' => 'Pending handoffs', 'value' => $this->pendingTasks($actorId)->count() + $this->pendingLogs($logs)->count(), 'tone' => 'amber'],
            ['key' => 'overdue', 'label' => 'Overdue', 'value' => $this->overdueTasks($actorId)->count() + $this->overdueDisputes($actorId)->count(), 'tone' => 'red'],
            ['key' => 'disputes', 'label' => 'Dispute resolutions', 'value' => $this->resolvedDisputes($start, $end, $actorId)->count(), 'tone' => 'purple'],
            ['key' => 'verifications', 'label' => 'Verifications completed', 'value' => $this->completedVerifications($start, $end, $actorId)->count(), 'tone' => 'indigo'],
            ['key' => 'messages', 'label' => 'Messages & notices', 'value' => $this->messageLogs($logs)->count() + $events->where('category', 'communications')->count(), 'tone' => 'cyan'],
            ['key' => 'updates', 'label' => 'Updates made', 'value' => $this->updateLogs($logs)->count(), 'tone' => 'slate'],
        ];
    }

    private function categories(Collection $logs, Collection $events, CarbonImmutable $start, CarbonImmutable $end, ?int $actorId): array
    {
        return [
            'resolved' => [
                'label' => 'What was resolved',
                'items' => $this->resolvedLogs($logs)
                    ->merge($this->resolvedEvents($events))
                    ->merge($this->resolvedDisputes($start, $end, $actorId)->map(fn (QuestDispute $dispute) => $this->disputeRow($dispute)))
                    ->take(16)
                    ->values(),
            ],
            'pending' => [
                'label' => 'Pending work',
                'items' => $this->pendingTasks($actorId)->map(fn (AdminTask $task) => $this->taskRow($task, 'pending'))
                    ->merge($this->pendingLogs($logs)->take(8)->map(fn (AdminActivityLog $log) => $this->logRow($log)))
                    ->values(),
            ],
            'overdue' => [
                'label' => 'Overdue items',
                'items' => $this->overdueTasks($actorId)->map(fn (AdminTask $task) => $this->taskRow($task, 'overdue'))
                    ->merge($this->overdueDisputes($actorId)->map(fn (QuestDispute $dispute) => $this->disputeRow($dispute, 'overdue')))
                    ->values(),
            ],
            'verifications' => [
                'label' => 'Verification outcomes',
                'items' => $this->completedVerifications($start, $end, $actorId)->map(fn (UserVerification $verification) => $this->verificationRow($verification))->values(),
            ],
            'messages' => [
                'label' => 'Messages, notices, broadcasts',
                'items' => $this->messageLogs($logs)->take(18)->map(fn (AdminActivityLog $log) => $this->logRow($log))->values(),
            ],
            'updates' => [
                'label' => 'Updates and changes',
                'items' => $this->updateLogs($logs)->take(22)->map(fn (AdminActivityLog $log) => $this->logRow($log))->values(),
            ],
        ];
    }

    private function staffRows(Collection $logs, Collection $events, Collection $admins): Collection
    {
        return $admins->map(function (array $admin) use ($logs, $events): array {
            $adminLogs = $logs->where('actor_user_id', $admin['id']);
            $adminEvents = $events->where('actor_user_id', $admin['id']);
            $last = $adminLogs->first()?->created_at ?? $adminEvents->first()?->occurred_at;

            return [
                ...$admin,
                'activity_count' => $adminLogs->count() + $adminEvents->count(),
                'resolved_count' => $this->resolvedLogs($adminLogs)->count() + $this->resolvedEvents($adminEvents)->count(),
                'message_count' => $this->messageLogs($adminLogs)->count() + $adminEvents->where('category', 'communications')->count(),
                'update_count' => $this->updateLogs($adminLogs)->count(),
                'last_seen' => $last?->toIso8601String(),
            ];
        })->sortByDesc('activity_count')->values();
    }

    private function timeline(Collection $logs, Collection $events): Collection
    {
        $logRows = $logs->take(120)->map(fn (AdminActivityLog $log) => $this->logRow($log));
        $eventRows = $events->take(80)->map(fn (AdminActivityFeedEvent $event) => $this->eventRow($event));

        return $logRows
            ->merge($eventRows)
            ->sortByDesc('sort_at')
            ->take(160)
            ->values()
            ->map(fn (array $row) => collect($row)->except('sort_at')->all());
    }

    private function attention(CarbonImmutable $start, CarbonImmutable $end, ?int $actorId): array
    {
        return [
            'tasks_due_today' => $this->tasksDueBetween($start, $end, $actorId)->map(fn (AdminTask $task) => $this->taskRow($task, 'due_today'))->values(),
            'disputes_needing_ruling' => $this->overdueDisputes($actorId)->map(fn (QuestDispute $dispute) => $this->disputeRow($dispute, 'needs_ruling'))->values(),
        ];
    }

    private function admins(): Collection
    {
        return User::query()
            ->whereHas('role', fn (Builder $query) => $query->whereIn('slug', ['admin', 'super_admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar_url'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?: $user->email,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ]);
    }

    private function resolvedLogs(Collection $logs): Collection
    {
        return $logs->filter(fn (AdminActivityLog $log) => $this->matches($log->action, ['resolved', 'approved', 'completed', 'decided', 'decision', 'released', 'closed']));
    }

    private function resolvedEvents(Collection $events): Collection
    {
        return $events->filter(fn (AdminActivityFeedEvent $event) => $this->matches($event->event_key.' '.$event->title, ['resolved', 'completed', 'approved', 'closed', 'funded']));
    }

    private function pendingLogs(Collection $logs): Collection
    {
        return $logs->filter(fn (AdminActivityLog $log) => $this->matches($log->action, ['created', 'opened', 'assigned', 'referred', 'under_review', 'action_required']));
    }

    private function messageLogs(Collection $logs): Collection
    {
        return $logs->filter(fn (AdminActivityLog $log) => $this->matches($log->action, ['message', 'email', 'notice', 'notification', 'broadcast', 'contact', 'communication']));
    }

    private function updateLogs(Collection $logs): Collection
    {
        return $logs->filter(fn (AdminActivityLog $log) => $this->matches($log->action, ['updated', 'changed', 'status', 'decision', 'created', 'deleted', 'flag', 'note', 'sanction', 'suspend']));
    }

    private function pendingTasks(?int $actorId): Collection
    {
        if (! Schema::hasTable('admin_tasks')) {
            return collect();
        }

        return AdminTask::query()
            ->with(['creator:id,name,email', 'assignee:id,name,email'])
            ->where('status', '!=', 'done')
            ->when($actorId, fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('assigned_to_admin_id', $actorId)->orWhere('created_by_admin_id', $actorId)))
            ->orderBy('due_at')
            ->limit(30)
            ->get();
    }

    private function overdueTasks(?int $actorId): Collection
    {
        if (! Schema::hasTable('admin_tasks')) {
            return collect();
        }

        return AdminTask::query()
            ->with(['creator:id,name,email', 'assignee:id,name,email'])
            ->where('status', '!=', 'done')
            ->whereDate('due_at', '<', now('Africa/Lagos')->toDateString())
            ->when($actorId, fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('assigned_to_admin_id', $actorId)->orWhere('created_by_admin_id', $actorId)))
            ->orderBy('due_at')
            ->limit(30)
            ->get();
    }

    private function tasksDueBetween(CarbonImmutable $start, CarbonImmutable $end, ?int $actorId): Collection
    {
        if (! Schema::hasTable('admin_tasks')) {
            return collect();
        }

        return AdminTask::query()
            ->with(['creator:id,name,email', 'assignee:id,name,email'])
            ->where('status', '!=', 'done')
            ->whereBetween('due_at', [$start->toDateString(), $end->toDateString()])
            ->when($actorId, fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('assigned_to_admin_id', $actorId)->orWhere('created_by_admin_id', $actorId)))
            ->orderBy('due_at')
            ->limit(20)
            ->get();
    }

    private function resolvedDisputes(CarbonImmutable $start, CarbonImmutable $end, ?int $actorId): Collection
    {
        if (! Schema::hasTable('quest_disputes')) {
            return collect();
        }

        return QuestDispute::query()
            ->with(['quest:id,title,reference_code', 'awaitingUser:id,name,email'])
            ->whereBetween('resolved_at', [$start, $end])
            ->limit(20)
            ->get();
    }

    private function overdueDisputes(?int $actorId): Collection
    {
        if (! Schema::hasTable('quest_disputes')) {
            return collect();
        }

        return QuestDispute::query()
            ->with('quest:id,title,reference_code')
            ->whereNull('resolved_at')
            ->where(fn (Builder $query) => $query
                ->where('response_required_by', '<', now())
                ->orWhere('ruling_required_by', '<', now()))
            ->limit(20)
            ->get();
    }

    private function completedVerifications(CarbonImmutable $start, CarbonImmutable $end, ?int $actorId): Collection
    {
        if (! Schema::hasTable('user_verifications')) {
            return collect();
        }

        return UserVerification::query()
            ->with(['user:id,name,email', 'reviewer:id,name,email'])
            ->whereBetween('reviewed_at', [$start, $end])
            ->when($actorId, fn (Builder $query) => $query->where('reviewed_by', $actorId))
            ->latest('reviewed_at')
            ->limit(30)
            ->get();
    }

    private function taskRow(AdminTask $task, string $tone): array
    {
        return [
            'id' => 'task-'.$task->id,
            'type' => 'task',
            'tone' => $tone,
            'title' => $task->title,
            'summary' => $task->description ?: 'Admin task',
            'actor' => $task->assignee?->name ?? $task->creator?->name ?? 'Unassigned',
            'meta' => collect([$task->priority, $task->status, $task->due_at?->toDateString()])->filter()->join(' · '),
            'at' => $task->due_at?->toIso8601String(),
        ];
    }

    private function disputeRow(QuestDispute $dispute, string $tone = 'resolved'): array
    {
        return [
            'id' => 'dispute-'.$dispute->id,
            'type' => 'dispute',
            'tone' => $tone,
            'title' => $dispute->quest?->title ?? 'Dispute '.$dispute->uuid,
            'summary' => $dispute->resolution_outcome ?: $dispute->reason,
            'actor' => $dispute->awaitingUser?->name ?? 'Dispute desk',
            'meta' => collect([$dispute->quest?->reference_code, $dispute->status?->value ?? (string) $dispute->status])->filter()->join(' · '),
            'at' => ($dispute->resolved_at ?? $dispute->ruling_required_by ?? $dispute->response_required_by)?->toIso8601String(),
        ];
    }

    private function verificationRow(UserVerification $verification): array
    {
        return [
            'id' => 'verification-'.$verification->id,
            'type' => 'verification',
            'tone' => $verification->status?->value === 'approved' ? 'resolved' : 'pending',
            'title' => 'Verification '.$this->label((string) ($verification->verification_type ?? $verification->category?->value ?? 'review')),
            'summary' => $verification->user?->email ?? 'User verification reviewed',
            'actor' => $verification->reviewer?->name ?? $verification->reviewer?->email ?? 'System',
            'meta' => $this->label($verification->status?->value ?? (string) $verification->status),
            'at' => $verification->reviewed_at?->toIso8601String(),
        ];
    }

    private function logRow(AdminActivityLog $log): array
    {
        return [
            'id' => 'log-'.$log->id,
            'type' => 'audit',
            'tone' => $this->toneFor($log->action),
            'title' => $this->label($log->action),
            'summary' => $this->propertiesSummary($log->properties ?? []),
            'actor' => $log->actor?->name ?? $log->actor?->email ?? 'System',
            'actor_email' => $log->actor?->email,
            'subject' => $this->subject($log->subject_type, $log->subject_id),
            'meta' => collect([$log->ip_address, $this->device($log->user_agent)])->filter()->join(' · '),
            'at' => $log->created_at?->toIso8601String(),
            'sort_at' => $log->created_at?->timestamp ?? 0,
        ];
    }

    private function eventRow(AdminActivityFeedEvent $event): array
    {
        return [
            'id' => 'event-'.$event->id,
            'type' => 'feed',
            'tone' => $event->severity ?: 'info',
            'title' => $event->title,
            'summary' => $event->summary,
            'actor' => $event->actor?->name ?? $event->actor?->email ?? 'System',
            'actor_email' => $event->actor?->email,
            'subject' => $this->subject($event->subject_type, $event->subject_id),
            'meta' => $this->label($event->category),
            'at' => $event->occurred_at?->toIso8601String(),
            'sort_at' => $event->occurred_at?->timestamp ?? 0,
        ];
    }

    private function matches(string $value, array $needles): bool
    {
        $haystack = Str::of($value)->lower()->toString();

        return collect($needles)->contains(fn (string $needle) => str_contains($haystack, $needle));
    }

    private function label(string $value): string
    {
        return Str::of($value)->replace(['.', '_', '-'], ' ')->headline()->toString();
    }

    private function subject(?string $type, ?int $id): string
    {
        if (! $type) {
            return 'Platform';
        }

        return class_basename($type).' #'.($id ?: '—');
    }

    private function toneFor(string $action): string
    {
        return match (true) {
            $this->matches($action, ['resolved', 'approved', 'completed', 'released']) => 'resolved',
            $this->matches($action, ['deleted', 'suspend', 'rejected', 'flag']) => 'overdue',
            $this->matches($action, ['created', 'opened', 'assigned', 'referred']) => 'pending',
            default => 'neutral',
        };
    }

    private function propertiesSummary(array $properties): string
    {
        $reason = $properties['reason'] ?? $properties['resolution_note'] ?? $properties['description'] ?? $properties['note'] ?? null;
        if (is_string($reason) && $reason !== '') {
            return Str::limit($reason, 150);
        }

        $keys = collect($properties)->keys()->take(5)->map(fn ($key) => $this->label((string) $key))->join(', ');

        return $keys ? 'Fields: '.$keys : 'Audit footprint recorded.';
    }

    private function device(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        return Str::limit($userAgent, 52);
    }
}
