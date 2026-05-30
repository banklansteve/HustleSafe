<?php

namespace App\Services\Support;

use App\Mail\SupportTicketStatusMail;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\SupportTicketEmailLog;
use App\Models\SupportTicketIssueGroup;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\Platform\PlatformSlaService;
use App\Services\Operations\StaffNotificationCentreService;
use App\Support\Support\SupportWorkingDays;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SupportTicketManagementService
{
    public const STATUSES = [
        'open',
        'in_progress',
        'awaiting_customer',
        'resolved',
        'closed',
    ];

    public function __construct(
        private readonly StaffNotificationCentreService $notifications,
        private readonly PlatformSlaService $sla,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function issueGroups(): array
    {
        if (! Schema::hasTable('support_ticket_issue_groups')) {
            return $this->defaultIssueGroups();
        }

        return SupportTicketIssueGroup::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['key', 'label', 'description'])
            ->map(fn (SupportTicketIssueGroup $group) => [
                'key' => $group->key,
                'label' => $group->label,
                'description' => $group->description,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allIssueGroupsForSettings(): array
    {
        if (! Schema::hasTable('support_ticket_issue_groups')) {
            return $this->defaultIssueGroups();
        }

        return SupportTicketIssueGroup::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (SupportTicketIssueGroup $group) => $group->only(['id', 'key', 'label', 'description', 'sort_order', 'is_active']))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertIssueGroup(array $data, ?SupportTicketIssueGroup $group = null): SupportTicketIssueGroup
    {
        $payload = [
            'key' => Str::slug((string) ($data['key'] ?? $data['label'] ?? ''), '_'),
            'label' => (string) $data['label'],
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($group) {
            $group->update($payload);

            return $group->fresh();
        }

        return SupportTicketIssueGroup::query()->create($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCustomers(string $query, int $limit = 12): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        return User::query()
            ->with('role:id,name,slug')
            ->where(function ($builder) use ($term): void {
                $builder->where('email', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%");
            })
            ->whereHas('role', fn ($role) => $role->whereNotIn('slug', ['admin', 'super_admin']))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => $this->customerSummary($user))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function createTicket(User $actor, array $data, array $attachments = []): SupportTicket
    {
        return DB::transaction(function () use ($actor, $data, $attachments): SupportTicket {
            $customer = User::query()->with('role:id,name,slug')->findOrFail((int) $data['user_id']);
            $expectedResolutionAt = $this->sla->computeDueAt('support_ticket_response', now());
            $actionItems = $this->normalizeActionItems($data['action_items'] ?? []);

            $ticket = SupportTicket::query()->create([
                'ticket_reference' => $this->generateReference(),
                'user_id' => $customer->id,
                'customer_username' => $customer->username,
                'customer_full_name' => $customer->name,
                'opened_by_admin_id' => $actor->id,
                'assigned_admin_id' => $actor->id,
                'subject' => $data['subject'],
                'category' => $data['issue_group'],
                'issue_group' => $data['issue_group'],
                'priority' => $data['priority'],
                'status' => 'open',
                'description' => $data['description'],
                'internal_notes' => $data['internal_notes'] ?? null,
                'action_items' => $actionItems,
                'opened_at' => now(),
                'expected_resolution_at' => $expectedResolutionAt,
                'last_activity_at' => now(),
            ]);

            $storedAttachments = $this->storeAttachments($attachments);
            $ticket->messages()->create([
                'sender_user_id' => $actor->id,
                'sender_type' => 'admin',
                'visibility' => 'internal',
                'body' => strip_tags((string) $data['description']),
                'metadata' => [
                    'event' => 'ticket_opened',
                    'html' => $data['description'],
                    'attachments' => $storedAttachments,
                ],
            ]);

            $this->recordActivity($ticket, $actor, 'created', 'Ticket created', [
                'priority' => $ticket->priority,
                'issue_group' => $ticket->issue_group,
            ]);

            $this->sendCustomerEmail($ticket, 'opened', $customer->email, [
                'issue_group_label' => $this->issueGroupLabel((string) $ticket->issue_group),
            ]);

            if ($ticket->priority === 'critical') {
                $this->notifySuperAdminsCritical($ticket);
            }

            $this->notifications->notifyCustomerSupportAssigned($actor, $ticket);

            $this->sla->start(
                'support_ticket_response',
                $ticket,
                $actor,
                $actor,
                [
                    'subject_label' => $ticket->ticket_reference,
                    'ticket_uuid' => $ticket->uuid,
                ],
                $ticket->opened_at,
            );

            try {
                $feed = app(AdminActivityFeedService::class);
                $feed->record(
                    'support_tickets',
                    'support_ticket.created',
                    'Support ticket opened',
                    "{$actor->name} opened {$ticket->ticket_reference}: {$ticket->subject}",
                    $feed->entities([
                        ['type' => 'user', 'id' => $customer->id, 'label' => $customer->name],
                        ['type' => 'support_ticket', 'id' => $ticket->id, 'uuid' => $ticket->uuid, 'label' => $ticket->ticket_reference, 'href' => route('admin.support-tickets.show', ['ticket' => $ticket->uuid], false)],
                    ]),
                    [
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                        'assignee' => $actor->name,
                    ],
                    null,
                    $actor,
                    SupportTicket::class,
                    $ticket->id,
                    severity: $ticket->priority === 'critical' ? 'warning' : 'info',
                    occurredAt: $ticket->opened_at,
                );
            } catch (\Throwable $exception) {
                report($exception);
            }

            return $ticket->fresh([
                'customer.role:id,name,slug',
                'assignedAdmin:id,name,email',
                'openedByAdmin:id,name,email',
                'activities.actor:id,name,email',
            ]);
        });
    }

    public function ticketDetail(SupportTicket $ticket, User $viewer): array
    {
        $ticket->load([
            'customer.role:id,name,slug',
            'assignedAdmin.role:id,name,slug',
            'assignedAdmin:id,name,email',
            'openedByAdmin:id,name,email',
            'activities' => fn ($query) => $query
                ->with([
                    'actor.role:id,name,slug',
                    'actor:id,name,email',
                ])
                ->orderByDesc('occurred_at'),
            'emailLogs',
            'messages' => fn ($query) => $query
                ->with('sender.role:id,name,slug')
                ->orderByDesc('created_at'),
        ]);

        abort_if(! $this->canView($ticket, $viewer), 403);

        return $this->ticketPayload($ticket, true);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function queueFor(User $viewer, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        if (! Schema::hasTable('support_tickets')) {
            return new Paginator([], 0, $perPage);
        }

        return $this->filteredQuery($viewer, $filters)->paginate($perPage)->through(fn (SupportTicket $ticket) => $this->ticketPayload($ticket));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function listRows(User $viewer, array $filters = [], int $limit = 300): array
    {
        if (! Schema::hasTable('support_tickets')) {
            return [];
        }

        return $this->filteredQuery($viewer, $filters)
            ->limit($limit)
            ->get()
            ->map(fn (SupportTicket $ticket) => $this->ticketPayload($ticket))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function liveFeedPaginate(User $viewer, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        abort_unless($viewer->role?->slug === 'super_admin', 403);

        if (! Schema::hasTable('support_tickets')) {
            return new Paginator([], 0, $perPage);
        }

        return $this->filteredQuery($viewer, $filters)
            ->paginate(min(200, max(1, $perPage)))
            ->through(fn (SupportTicket $ticket) => $this->liveFeedRow($ticket));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTicket(User $actor, SupportTicket $ticket, array $data): SupportTicket
    {
        abort_if($ticket->isReadOnly(), 422, __('This ticket cannot be edited.'));
        abort_if(! $this->canManage($ticket, $actor), 403);

        return DB::transaction(function () use ($actor, $ticket, $data): SupportTicket {
            $ticket->update([
                'subject' => $data['subject'],
                'category' => $data['issue_group'],
                'issue_group' => $data['issue_group'],
                'priority' => $data['priority'],
                'description' => $data['description'],
                'internal_notes' => $data['internal_notes'] ?? null,
                'action_items' => $this->normalizeActionItems($data['action_items'] ?? []),
                'last_activity_at' => now(),
            ]);

            $this->recordActivity($ticket, $actor, 'updated', 'Ticket details updated', [
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
            ]);

            $this->notifyTicketCreatorAfterSuperAdminUpdate($actor, $ticket, 'Ticket details were updated');

            return $ticket->fresh();
        });
    }

    public function deleteTicket(User $actor, SupportTicket $ticket): void
    {
        abort_if(! $ticket->isManagedTicket(), 422, __('Only admin-created tickets can be deleted.'));
        abort_if($ticket->isReadOnly(), 422, __('This ticket cannot be deleted.'));
        abort_if(! $this->canManage($ticket, $actor), 403);

        DB::transaction(function () use ($ticket): void {
            $ticket->messages()->delete();
            $ticket->activities()->delete();
            $ticket->emailLogs()->delete();
            $ticket->handoffs()->delete();
            $ticket->delete();
        });
    }

    public function updateStatus(User $actor, SupportTicket $ticket, string $status, string $summary): SupportTicket
    {
        abort_if($ticket->isReadOnly(), 422, __('Closed tickets are read-only.'));

        return DB::transaction(function () use ($actor, $ticket, $status, $summary): SupportTicket {
            $before = $ticket->status;
            $updates = ['status' => $status, 'last_activity_at' => now()];

            if ($status === 'in_progress' && $ticket->in_progress_at === null) {
                $updates['in_progress_at'] = now();
            }
            if ($status === 'resolved') {
                $updates['resolution_summary'] = $summary;
            }
            if ($status === 'closed') {
                $updates['closed_at'] = now();
                $updates['resolution_summary'] = $summary;
            }

            $ticket->update($updates);

            $this->recordActivity($ticket, $actor, 'status_changed', 'Status changed from '.str_replace('_', ' ', $before).' to '.str_replace('_', ' ', $status), [
                'from' => $before,
                'to' => $status,
                'summary' => $summary,
            ]);

            if (in_array($status, ['resolved', 'closed'], true)) {
                $ticket->load('customer');
                if ($ticket->customer?->email) {
                    $this->sendCustomerEmail($ticket, $status, $ticket->customer->email, [
                        'resolution_summary' => $summary,
                    ]);
                }
                $this->sla->resolveForSubject('support_ticket_response', $ticket);
            }

            $this->notifyTicketCreatorAfterSuperAdminUpdate(
                $actor,
                $ticket,
                'Status changed to '.str_replace('_', ' ', $status),
            );

            return $ticket->fresh();
        });
    }

    public function reassign(User $actor, SupportTicket $ticket, int $assigneeId): SupportTicket
    {
        abort_if($ticket->isReadOnly(), 422, __('Closed tickets are read-only.'));

        return DB::transaction(function () use ($actor, $ticket, $assigneeId): SupportTicket {
            $assignee = User::query()->with('role:id,slug')->findOrFail($assigneeId);
            abort_unless(in_array($assignee->role?->slug, ['admin', 'super_admin'], true), 422);

            $fromId = $ticket->assigned_admin_id;
            $ticket->update([
                'assigned_admin_id' => $assignee->id,
                'last_activity_at' => now(),
            ]);

            $this->recordActivity($ticket, $actor, 'reassigned', 'Ticket reassigned to '.$assignee->name, [
                'from_admin_id' => $fromId,
                'to_admin_id' => $assignee->id,
            ]);

            $this->notifications->notifyCustomerSupportAssigned($assignee, $ticket);

            if ($clock = $this->sla->activeClockForSubject('support_ticket_response', $ticket)) {
                $this->sla->reassign($clock, $assignee);
            }

            $this->notifyTicketCreatorAfterSuperAdminUpdate($actor, $ticket, 'Ticket reassigned to '.$assignee->name);

            return $ticket->fresh(['assignedAdmin:id,name,email']);
        });
    }

    /**
     * @param  list<array{id?: string, label: string, completed?: bool}>  $items
     */
    public function updateActionItems(User $actor, SupportTicket $ticket, array $items): SupportTicket
    {
        abort_if($ticket->isReadOnly(), 422, __('Closed tickets are read-only.'));

        $normalized = $this->normalizeActionItems($items);
        $previous = collect($ticket->action_items ?? []);
        $ticket->update(['action_items' => $normalized, 'last_activity_at' => now()]);

        foreach ($normalized as $item) {
            $prev = $previous->firstWhere('id', $item['id']);
            if ($prev && ! ($prev['completed'] ?? false) && ($item['completed'] ?? false)) {
                $this->recordActivity($ticket, $actor, 'action_checked', 'Action completed: '.$item['label'], [
                    'action_item_id' => $item['id'],
                ]);
            }
        }

        $this->notifyTicketCreatorAfterSuperAdminUpdate($actor, $ticket, 'Action checklist updated');

        return $ticket->fresh();
    }

    public function addComment(
        User $actor,
        SupportTicket $ticket,
        string $body,
        bool $customerFacing = false,
        array $attachments = [],
    ): SupportTicketMessage {
        abort_if($ticket->isReadOnly(), 422, __('Closed tickets are read-only.'));

        return DB::transaction(function () use ($actor, $ticket, $body, $customerFacing, $attachments): SupportTicketMessage {
            $storedAttachments = $this->storeAttachments($attachments);
            $message = $ticket->messages()->create([
                'sender_user_id' => $actor->id,
                'sender_type' => 'admin',
                'visibility' => $customerFacing ? 'public' : 'internal',
                'body' => strip_tags($body),
                'metadata' => [
                    'html' => $body,
                    'attachments' => $storedAttachments,
                    'customer_facing' => $customerFacing,
                ],
            ]);

            $ticket->update(['last_activity_at' => now(), 'last_admin_activity_at' => now()]);

            $this->recordActivity(
                $ticket,
                $actor,
                $customerFacing ? 'customer_comment' : 'comment_added',
                $customerFacing ? 'Customer-facing update added' : 'Internal comment added',
                ['message_id' => $message->id],
            );

            if ($customerFacing && $ticket->customer?->email) {
                $ticket->loadMissing('customer');
                $this->sendCustomerEmail($ticket, 'update', $ticket->customer->email, [
                    'comment' => strip_tags($body),
                ]);
            }

            $this->notifyTicketCreatorAfterSuperAdminUpdate(
                $actor,
                $ticket,
                $customerFacing ? 'Customer-facing update added' : 'Internal comment added',
            );

            return $message;
        });
    }

    public function overrideSla(User $actor, SupportTicket $ticket, string $date, string $reason): SupportTicket
    {
        abort_if($actor->role?->slug !== 'super_admin', 403);

        $ticket->update([
            'expected_resolution_at' => $date,
            'sla_override_reason' => $reason,
            'sla_override_by_user_id' => $actor->id,
            'sla_breached' => false,
            'sla_overdue_at' => null,
            'last_activity_at' => now(),
        ]);

        $this->recordActivity($ticket, $actor, 'sla_override', 'Expected resolution date overridden', [
            'expected_resolution_at' => $date,
            'reason' => $reason,
        ]);

        $this->notifyTicketCreatorAfterSuperAdminUpdate($actor, $ticket, 'Expected resolution date overridden');

        return $ticket->fresh();
    }

    public function mergeTickets(User $actor, SupportTicket $primary, SupportTicket $duplicate): SupportTicket
    {
        abort_if($actor->role?->slug !== 'super_admin', 403);
        abort_if($primary->id === $duplicate->id, 422);

        return DB::transaction(function () use ($actor, $primary, $duplicate): SupportTicket {
            $duplicate->update([
                'merged_into_support_ticket_id' => $primary->id,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            SupportTicketActivity::query()
                ->where('support_ticket_id', $duplicate->id)
                ->update(['support_ticket_id' => $primary->id]);

            SupportTicketMessage::query()
                ->where('support_ticket_id', $duplicate->id)
                ->update(['support_ticket_id' => $primary->id]);

            $this->recordActivity($primary, $actor, 'merged', 'Merged ticket '.$duplicate->ticket_reference.' into this record', [
                'merged_ticket_id' => $duplicate->id,
                'merged_ticket_reference' => $duplicate->ticket_reference,
            ]);

            $this->notifyTicketCreatorAfterSuperAdminUpdate(
                $actor,
                $primary,
                'Merged ticket '.$duplicate->ticket_reference.' into this record',
            );

            return $primary->fresh(['activities', 'messages']);
        });
    }

    public function flagOverdueTickets(): int
    {
        if (! Schema::hasTable('support_tickets')) {
            return 0;
        }

        return SupportTicket::query()
            ->whereNotNull('opened_by_admin_id')
            ->whereNull('merged_into_support_ticket_id')
            ->whereIn('status', ['open', 'in_progress', 'awaiting_customer'])
            ->where('sla_breached', false)
            ->whereNotNull('expected_resolution_at')
            ->where('expected_resolution_at', '<', now())
            ->update([
                'sla_breached' => true,
                'sla_overdue_at' => now(),
            ]);
    }

    /**
     * Platform-wide support metrics for super-admin analytics (cached 24 hours).
     *
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        if (! Schema::hasTable('support_tickets')) {
            return $this->emptyAnalyticsPayload();
        }

        return Cache::remember(
            'support_ticket.platform_analytics',
            now()->addDay(),
            fn (): array => $this->computeAnalytics(),
        );
    }

    public function refreshAnalyticsCache(): array
    {
        if (! Schema::hasTable('support_tickets')) {
            Cache::forget('support_ticket.platform_analytics');

            return $this->emptyAnalyticsPayload();
        }

        $payload = $this->computeAnalytics();
        Cache::put('support_ticket.platform_analytics', $payload, now()->addDay());

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeAnalytics(): array
    {
        $base = SupportTicket::query()->whereNotNull('opened_by_admin_id');

        $open = (clone $base)->whereIn('status', ['open', 'in_progress', 'awaiting_customer'])->count();
        $breached = (clone $base)->where('sla_breached', true)->whereNotIn('status', ['closed', 'resolved'])->count();
        $resolved = (clone $base)->whereIn('status', ['resolved', 'closed'])->get(['opened_at', 'closed_at']);

        $avgHours = $resolved
            ->filter(fn (SupportTicket $ticket) => $ticket->opened_at && $ticket->closed_at)
            ->avg(fn (SupportTicket $ticket) => $ticket->opened_at->diffInHours($ticket->closed_at));

        $avgHoursRounded = $avgHours ? round((float) $avgHours, 1) : 0.0;

        $byCategory = (clone $base)
            ->select('issue_group', DB::raw('count(*) as total'))
            ->groupBy('issue_group')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->issue_group,
                'label' => $this->issueGroupLabel((string) $row->issue_group),
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $categoryMax = collect($byCategory)->max('total') ?: 1;

        $byAssignee = (clone $base)
            ->whereIn('status', ['open', 'in_progress', 'awaiting_customer'])
            ->select('assigned_admin_id', DB::raw('count(*) as total'))
            ->groupBy('assigned_admin_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $admin = User::query()->find($row->assigned_admin_id);

                return [
                    'admin' => $admin?->name ?? 'Unassigned',
                    'total' => (int) $row->total,
                ];
            })
            ->values()
            ->all();

        $workloadMax = collect($byAssignee)->max('total') ?: 1;

        $refreshedAt = now();

        return [
            'open_tickets' => $open,
            'sla_breach_rate' => $open > 0 ? round(($breached / $open) * 100, 1) : 0.0,
            'sla_breached_count' => $breached,
            'average_resolution_hours' => $avgHoursRounded,
            'average_resolution_label' => $this->formatResolutionDuration($avgHoursRounded),
            'tickets_by_category' => $byCategory,
            'category_max' => $categoryMax,
            'workload_by_admin' => $byAssignee,
            'workload_max' => $workloadMax,
            'ticket_trends' => $this->computeTicketTrends(),
            'refreshed_at' => $refreshedAt->toIso8601String(),
            'next_refresh_at' => $refreshedAt->copy()->addDay()->toIso8601String(),
        ];
    }

    /**
     * @return array{daily: list<array<string, mixed>>, weekly: list<array<string, mixed>>, monthly: list<array<string, mixed>>}
     */
    private function computeTicketTrends(): array
    {
        $base = SupportTicket::query()->whereNotNull('opened_by_admin_id');

        $daily = collect(range(29, 0))->map(function (int $daysAgo) use ($base): array {
            $date = CarbonImmutable::now()->subDays($daysAgo)->startOfDay();
            $end = $date->endOfDay();

            return [
                'label' => $date->format('M j'),
                'created' => (clone $base)->whereBetween('opened_at', [$date, $end])->count(),
                'resolved' => (clone $base)->whereIn('status', ['resolved', 'closed'])->whereBetween('closed_at', [$date, $end])->count(),
            ];
        })->values()->all();

        $weekly = collect(range(11, 0))->map(function (int $weeksAgo) use ($base): array {
            $start = CarbonImmutable::now()->subWeeks($weeksAgo)->startOfWeek();
            $end = $start->endOfWeek();

            return [
                'label' => $start->format('M j'),
                'created' => (clone $base)->whereBetween('opened_at', [$start, $end])->count(),
                'resolved' => (clone $base)->whereIn('status', ['resolved', 'closed'])->whereBetween('closed_at', [$start, $end])->count(),
            ];
        })->values()->all();

        $monthly = collect(range(11, 0))->map(function (int $monthsAgo) use ($base): array {
            $start = CarbonImmutable::now()->subMonths($monthsAgo)->startOfMonth();
            $end = $start->endOfMonth();

            return [
                'label' => $start->format('M Y'),
                'created' => (clone $base)->whereBetween('opened_at', [$start, $end])->count(),
                'resolved' => (clone $base)->whereIn('status', ['resolved', 'closed'])->whereBetween('closed_at', [$start, $end])->count(),
            ];
        })->values()->all();

        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalyticsPayload(): array
    {
        return [
            'open_tickets' => 0,
            'sla_breach_rate' => 0.0,
            'sla_breached_count' => 0,
            'average_resolution_hours' => 0.0,
            'average_resolution_label' => '—',
            'tickets_by_category' => [],
            'category_max' => 1,
            'workload_by_admin' => [],
            'workload_max' => 1,
            'ticket_trends' => ['daily' => [], 'weekly' => [], 'monthly' => []],
            'refreshed_at' => now()->toIso8601String(),
            'next_refresh_at' => now()->addDay()->toIso8601String(),
        ];
    }

    private function formatResolutionDuration(float $hours): string
    {
        if ($hours <= 0) {
            return '—';
        }

        if ($hours >= 48) {
            return round($hours / 24, 1).' days';
        }

        if ($hours >= 24) {
            return '1 day';
        }

        return $hours.' hrs';
    }

    /**
     * @return array<string, mixed>
     */
    public function analyticsLive(): array
    {
        if (! Schema::hasTable('support_tickets')) {
            return $this->emptyAnalyticsPayload();
        }

        return $this->computeAnalytics();
    }

    /**
     * @return list<User>
     */
    public function assignableAdmins(): array
    {
        return User::query()
            ->with('role:id,slug')
            ->whereHas('role', fn ($role) => $role->whereIn('slug', ['admin', 'super_admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->all();
    }

    private function canView(SupportTicket $ticket, User $viewer): bool
    {
        if ($viewer->role?->slug === 'super_admin') {
            return true;
        }

        return (int) $ticket->assigned_admin_id === (int) $viewer->id
            || (int) $ticket->opened_by_admin_id === (int) $viewer->id;
    }

    private function canManage(SupportTicket $ticket, User $viewer): bool
    {
        if ($viewer->role?->slug === 'super_admin') {
            return true;
        }

        return (int) $ticket->assigned_admin_id === (int) $viewer->id
            || (int) $ticket->opened_by_admin_id === (int) $viewer->id;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(User $viewer, array $filters = [])
    {
        $query = SupportTicket::query()
            ->with(['customer:id,name,email,username', 'assignedAdmin:id,name,email', 'openedByAdmin:id,name,email'])
            ->whereNotNull('opened_by_admin_id')
            ->whereNull('merged_into_support_ticket_id')
            ->latest('opened_at');

        if ($viewer->role?->slug !== 'super_admin') {
            $query->where(function ($builder) use ($viewer): void {
                $builder->where('assigned_admin_id', $viewer->id)
                    ->orWhere('opened_by_admin_id', $viewer->id);
            });
        }

        if ($status = (string) ($filters['status'] ?? '')) {
            $query->where('status', $status);
        }
        if ($priority = (string) ($filters['priority'] ?? '')) {
            $query->where('priority', $priority);
        }
        if ($issueGroup = (string) ($filters['issue_group'] ?? '')) {
            $query->where('issue_group', $issueGroup);
        }
        if ($assignee = (int) ($filters['assignee_id'] ?? 0)) {
            $query->where('assigned_admin_id', $assignee);
        }
        if (($filters['sla_breached'] ?? '') === '1') {
            $query->where('sla_breached', true);
        }
        if ($from = (string) ($filters['from'] ?? '')) {
            $query->whereDate('opened_at', '>=', $from);
        }
        if ($to = (string) ($filters['to'] ?? '')) {
            $query->whereDate('opened_at', '<=', $to);
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('ticket_reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    private function generateReference(): string
    {
        $date = now()->format('Ymd');
        $count = SupportTicket::query()
            ->whereDate('created_at', today())
            ->whereNotNull('ticket_reference')
            ->count() + 1;

        return sprintf('TKT-%s-%05d', $date, $count);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function notifyTicketCreatorAfterSuperAdminUpdate(User $actor, SupportTicket $ticket, string $summary): void
    {
        if ($actor->role?->slug !== Role::SLUG_SUPER_ADMIN) {
            return;
        }

        if (! $ticket->opened_by_admin_id || (int) $ticket->opened_by_admin_id === (int) $actor->id) {
            return;
        }

        $creator = User::query()
            ->with('role:id,name,slug')
            ->find($ticket->opened_by_admin_id);

        if (! $creator) {
            return;
        }

        $this->notifications->notifyManagedSupportTicketUpdated($creator, $ticket, $actor, $summary);
    }

    private function staffRoleLabel(?User $user): string
    {
        if (! $user) {
            return 'Staff';
        }

        if ($user->relationLoaded('role') && $user->role) {
            return $user->role->standardLabel();
        }

        return Role::standardLabels()[(int) $user->role_id] ?? 'Staff';
    }

    private function commentAuthorLine(?User $sender): string
    {
        if (! $sender) {
            return 'System';
        }

        return $sender->name.' - '.$this->staffRoleLabel($sender);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordActivity(SupportTicket $ticket, User $actor, string $eventType, string $summary, array $metadata = []): void
    {
        if (! Schema::hasTable('support_ticket_activities')) {
            return;
        }

        SupportTicketActivity::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role?->slug,
            'event_type' => $eventType,
            'summary' => $summary,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function sendCustomerEmail(SupportTicket $ticket, string $event, string $email, array $extra = []): void
    {
        $mail = new SupportTicketStatusMail($ticket, $event, $extra);
        Mail::to($email)->send($mail);

        if (Schema::hasTable('support_ticket_email_logs')) {
            SupportTicketEmailLog::query()->create([
                'support_ticket_id' => $ticket->id,
                'recipient_email' => $email,
                'subject' => $mail->envelope()->subject,
                'event_type' => $event,
                'metadata' => $extra,
                'sent_at' => now(),
            ]);
        }
    }

    private function notifySuperAdminsCritical(SupportTicket $ticket): void
    {
        User::query()
            ->whereHas('role', fn ($role) => $role->where('slug', 'super_admin'))
            ->get(['id'])
            ->each(function (User $admin) use ($ticket): void {
                $this->notifications->notifyCustomerSupportAssigned($admin, $ticket);
            });
    }

    /**
     * @param  list<UploadedFile>  $attachments
     * @return list<array<string, mixed>>
     */
    private function storeAttachments(array $attachments): array
    {
        $stored = [];
        foreach (array_slice($attachments, 0, 5) as $file) {
            if (! $file instanceof UploadedFile || $file->getSize() > 5 * 1024 * 1024) {
                continue;
            }
            $path = $file->store('support-tickets/'.now()->format('Y/m'), 'public');
            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @param  mixed  $items
     * @return list<array{id: string, label: string, completed: bool}>
     */
    private function normalizeActionItems(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(function ($item) {
                if (is_string($item)) {
                    return ['id' => (string) Str::uuid(), 'label' => $item, 'completed' => false];
                }

                return [
                    'id' => (string) ($item['id'] ?? Str::uuid()),
                    'label' => (string) ($item['label'] ?? ''),
                    'completed' => (bool) ($item['completed'] ?? false),
                ];
            })
            ->filter(fn (array $item) => $item['label'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function liveFeedRow(SupportTicket $ticket): array
    {
        return [
            'uuid' => $ticket->uuid,
            'ticket_reference' => $ticket->ticket_reference,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'issue_group_label' => $this->issueGroupLabel((string) $ticket->issue_group),
            'assigned_admin' => $ticket->assignedAdmin?->only(['id', 'name', 'email']),
            'opened_by_admin' => $ticket->openedByAdmin?->only(['id', 'name', 'email']),
            'customer' => $ticket->customer ? $this->customerSummary($ticket->customer) : null,
            'opened_at' => $ticket->opened_at?->toIso8601String(),
            'opened_at_label' => $ticket->opened_at?->diffForHumans(),
            'sla_breached' => (bool) $ticket->sla_breached,
        ];
    }

    private function customerSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role?->name,
            'role_slug' => $user->role?->slug,
            'status' => $user->suspended_at ? 'Suspended' : ($user->email_verified_at ? 'Active' : 'Unverified'),
            'joined_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket, bool $detailed = false): array
    {
        $payload = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'ticket_reference' => $ticket->ticket_reference,
            'subject' => $ticket->subject,
            'issue_group' => $ticket->issue_group,
            'issue_group_label' => $this->issueGroupLabel((string) $ticket->issue_group),
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'description' => $ticket->description,
            'internal_notes' => $ticket->internal_notes,
            'action_items' => $ticket->action_items ?? [],
            'resolution_summary' => $ticket->resolution_summary,
            'opened_at' => $ticket->opened_at?->toIso8601String(),
            'expected_resolution_at' => $ticket->expected_resolution_at?->toIso8601String(),
            'sla_breached' => (bool) $ticket->sla_breached,
            'sla_override_reason' => $ticket->sla_override_reason,
            'is_read_only' => $ticket->isReadOnly(),
            'customer' => $ticket->customer ? $this->customerSummary($ticket->customer) : null,
            'assigned_admin' => $ticket->assignedAdmin?->only(['id', 'name', 'email']),
            'opened_by_admin' => $ticket->openedByAdmin?->only(['id', 'name', 'email']),
            'age_hours' => $ticket->opened_at ? $ticket->opened_at->diffInHours(now()) : 0,
        ];

        if ($detailed) {
            $payload['activities'] = $ticket->activities
                ?->sortByDesc(fn (SupportTicketActivity $activity) => $activity->occurred_at?->timestamp ?? 0)
                ?->values()
                ?->map(fn (SupportTicketActivity $activity) => [
                'id' => $activity->id,
                'event_type' => $activity->event_type,
                'summary' => $activity->summary,
                'metadata' => $activity->metadata,
                'occurred_at' => $activity->occurred_at?->toIso8601String(),
                'actor' => [
                    'name' => $activity->actor?->name ?? 'System',
                    'role' => $activity->actor_role ?? $activity->actor?->role?->slug,
                ],
            ])->all();
            $payload['email_logs'] = $ticket->emailLogs?->map(fn ($log) => $log->only(['id', 'recipient_email', 'subject', 'event_type', 'sent_at']))->all();
            $payload['comments'] = $ticket->messages
                ?->sortByDesc(fn (SupportTicketMessage $message) => $message->created_at?->timestamp ?? 0)
                ?->values()
                ?->map(fn (SupportTicketMessage $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'html' => data_get($message->metadata, 'html'),
                    'visibility' => $message->visibility,
                    'customer_facing' => (bool) data_get($message->metadata, 'customer_facing', false),
                    'attachments' => data_get($message->metadata, 'attachments', []),
                    'sender' => [
                        'id' => $message->sender?->id,
                        'name' => $message->sender?->name,
                        'role_label' => $this->staffRoleLabel($message->sender),
                    ],
                    'author_line' => $this->commentAuthorLine($message->sender),
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->all();
        }

        return $payload;
    }

    private function issueGroupLabel(string $key): string
    {
        if ($key === '') {
            return 'General';
        }

        if (Schema::hasTable('support_ticket_issue_groups')) {
            $label = SupportTicketIssueGroup::query()->where('key', $key)->value('label');
            if ($label) {
                return $label;
            }
        }

        return Str::headline(str_replace('_', ' ', $key));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultIssueGroups(): array
    {
        return [
            ['key' => 'account_verification', 'label' => 'Account & Verification'],
            ['key' => 'payments_escrow', 'label' => 'Payments & Escrow'],
            ['key' => 'disputes_contracts', 'label' => 'Disputes & Contracts'],
            ['key' => 'technical_issues', 'label' => 'Technical Issues'],
            ['key' => 'fraud_security', 'label' => 'Fraud & Security'],
            ['key' => 'quest_proposals', 'label' => 'Quest & Proposals'],
            ['key' => 'reviews_ratings', 'label' => 'Reviews & Ratings'],
            ['key' => 'general_enquiries', 'label' => 'General Enquiries'],
        ];
    }
}
