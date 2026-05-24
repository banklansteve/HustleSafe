<?php

namespace App\Services\Operations;

use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Enums\ReviewStatus;
use App\Models\AdminActivityFeedEvent;
use App\Models\AdminActivityLog;
use App\Models\AdminTask;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffOperationsDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(User $staff): array
    {
        return [
            'staff' => [
                'name' => $staff->name,
                'email' => $staff->email,
                'role_label' => 'Staff Admin',
            ],
            'workload_tiles' => $this->personalWorkload($staff),
            'team_tiles' => $this->teamWorkload(),
            'live_feed' => $this->liveFeed($staff),
            'my_tasks' => $this->myTasks($staff),
            'my_audit' => $this->myAudit($staff),
            'quick_actions' => $this->quickActions(),
            'permissions' => $this->permissions(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function personalWorkload(User $staff): array
    {
        $assignedTasks = AdminTask::query()
            ->where('assigned_to_admin_id', $staff->id)
            ->where('status', '<>', 'done');

        return [
            $this->tile('assigned', 'Assigned to me', (clone $assignedTasks)->count(), 'Flags, referrals, disputes, and KYC follow-up tasks.', route('operations.tasks.index')),
            $this->tile('overdue', 'Overdue', (clone $assignedTasks)->whereNotNull('due_at')->where('due_at', '<', now()->toDateString())->count(), 'Assigned tasks past their due date.', route('operations.tasks.index', ['quick' => 'overdue']), 'rose'),
            $this->tile('enquiries', 'User enquiries', $this->enquiriesCount(), 'Support and contact follow-ups from operational channels.', route('operations.communications.index', ['quick' => 'enquiries']), 'amber'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function teamWorkload(): array
    {
        return [
            $this->tile('quest_review', 'Quests needing review', Quest::query()->whereIn('status', [QuestStatus::PendingReview->value, QuestStatus::Open->value])->count(), 'Open or review-stage Quests the operations team can inspect.', route('operations.quests.index'), 'primary'),
            $this->tile('proposals_flagged', 'Proposals flagged today', $this->flaggedProposalsToday(), 'Proposal moderation activity requiring staff triage.', route('operations.proposals.index', ['quick' => 'flagged_today']), 'orange'),
            $this->tile('disputes', 'Active disputes', QuestDispute::query()->whereIn('status', $this->openDisputeStatuses())->count(), 'Open mediation and ruling queue.', route('operations.disputes.index'), 'rose'),
            $this->tile('kyc', 'Pending verifications', UserVerification::query()->whereIn('status', ['pending', 'in_review', 'flagged'])->count(), 'KYC, BVN, NIN, utility, identity, and credential reviews.', route('operations.verifications.index'), 'emerald'),
            $this->tile('users_flagged', 'Users flagged', $this->flaggedUsers(), 'Accounts with bans, suspensions, restrictions, or active concerns.', route('operations.users.index', ['quick' => 'flagged']), 'slate'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function liveFeed(User $staff): array
    {
        if (Schema::hasTable('admin_activity_feed_events')) {
            return AdminActivityFeedEvent::query()
                ->where('actor_user_id', $staff->id)
                ->latest('occurred_at')
                ->limit(12)
                ->get()
                ->map(fn (AdminActivityFeedEvent $event) => [
                    'id' => $event->uuid,
                    'title' => $event->title,
                    'summary' => $event->summary,
                    'category' => $event->category,
                    'severity' => $event->severity ?: 'info',
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])
                ->all();
        }

        return AdminTask::query()
            ->where(fn ($query) => $query->whereNull('assigned_to_admin_id')->orWhere('assigned_to_admin_id', $staff->id))
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (AdminTask $task) => [
                'id' => 'task-'.$task->id,
                'title' => $task->title,
                'summary' => $task->description,
                'category' => 'tasks',
                'severity' => $task->priority,
                'occurred_at' => $task->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function myTasks(User $staff, int $limit = 10): array
    {
        return AdminTask::query()
            ->with(['creator:id,name,email'])
            ->where('assigned_to_admin_id', $staff->id)
            ->where('status', '<>', 'done')
            ->orderByRaw("case priority when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
            ->oldest('due_at')
            ->limit($limit)
            ->get()
            ->map(fn (AdminTask $task) => $this->taskRow($task))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function myAudit(User $staff, int $limit = 8): array
    {
        return AdminActivityLog::query()
            ->where('actor_user_id', $staff->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AdminActivityLog $log) => [
                'id' => $log->id,
                'action' => Str::headline(str_replace('.', ' ', $log->action)),
                'subject' => class_basename((string) $log->subject_type).' #'.$log->subject_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function quickActions(): array
    {
        return [
            ['label' => 'Open My Tasks', 'href' => route('operations.tasks.index'), 'description' => 'See assigned flags, referrals, KYC items, disputes, and escalations.'],
            ['label' => 'Manage Quests', 'href' => route('operations.quests.index'), 'description' => 'Review quest queues, flags, notices, and moderation context.'],
            ['label' => 'Triage Proposals', 'href' => route('operations.proposals.index'), 'description' => 'Review flagged proposals and operational proposal risk signals.'],
            ['label' => 'Claim a Dispute', 'href' => route('operations.disputes.index'), 'description' => 'Review open disputes and pick one up for mediation.'],
            ['label' => 'Review KYC Queue', 'href' => route('operations.verifications.index'), 'description' => 'Approve, reject, request correction, or escalate submissions.'],
            ['label' => 'Open Support hub', 'href' => route('operations.support.index'), 'description' => 'Tickets, quest-thread chats, disputes, and user context.'],
            ['label' => 'Open CS Inbox', 'href' => route('operations.communications.index'), 'description' => 'Legacy communications and enquiry threads.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function permissions(): array
    {
        return [
            'allowed' => [
                'View operational modules',
                'Flag, refer, note, and contact users',
                'Handle Tier 1 and Tier 2 disputes',
                'Review KYC submissions',
                'Suspend users for up to 72 hours',
                'Request payout holds/releases or account escalations',
            ],
            'restricted' => [
                'No platform settings',
                'No financial control centre or revenue dashboards',
                'No mass email broadcasting',
                'No permanent bans',
                'No payout approval or release outside approved dispute workflow',
                'No overriding another admin ruling without escalation',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function openDisputeStatuses(): array
    {
        return [
            QuestDisputeStatus::Open->value,
            QuestDisputeStatus::SelfResolving->value,
            QuestDisputeStatus::Escalated->value,
            QuestDisputeStatus::AwaitingRuling->value,
        ];
    }

    private function enquiriesCount(): int
    {
        return AdminTask::query()
            ->where('status', '<>', 'done')
            ->where(function ($query): void {
                $query->where('title', 'like', '%support%')
                    ->orWhere('title', 'like', '%enquir%')
                    ->orWhere('description', 'like', '%support%')
                    ->orWhere('description', 'like', '%contact%');
            })
            ->count();
    }

    private function flaggedProposalsToday(): int
    {
        if (! Schema::hasColumn('quest_offers', 'admin_status')) {
            return 0;
        }

        return QuestOffer::query()
            ->where('admin_status', '<>', 'clear')
            ->where('updated_at', '>=', now()->startOfDay())
            ->count();
    }

    private function flaggedUsers(): int
    {
        return User::query()
            ->where(fn ($query) => $query->whereNotNull('suspended_at')->orWhereNotNull('under_review_at')->orWhereNotNull('banned_at')->orWhereNotNull('verification_restricted_at'))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function tile(string $key, string $label, int $value, string $hint, string $href, string $tone = 'primary'): array
    {
        return compact('key', 'label', 'value', 'hint', 'href', 'tone');
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRow(AdminTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'source_type' => $task->source_type ? class_basename($task->source_type) : null,
            'source_id' => $task->source_id,
            'due_at' => $task->due_at?->toDateString(),
            'creator' => $task->creator?->name,
        ];
    }
}
