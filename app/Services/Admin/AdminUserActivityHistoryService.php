<?php

namespace App\Services\Admin;

use App\Enums\QuestPatrolSubjectType;
use App\Models\ActivityLog;
use App\Models\AdminActivityFeedEvent;
use App\Models\AdminActivityLog;
use App\Models\DisputeEvent;
use App\Models\KycAuditEvent;
use App\Models\LoginEvent;
use App\Models\PaymentEscrow;
use App\Models\Portfolio;
use App\Models\Quest;
use App\Models\QuestCompletionEvent;
use App\Models\QuestContractEvent;
use App\Models\QuestOffer;
use App\Models\QuestPatrolFlag;
use App\Models\Review;
use App\Models\StaffHrAuditTrail;
use App\Models\User;
use App\Models\UserActivityPatrolFlag;
use App\Models\UserAuditEvent;
use App\Models\VerificationEngineAuditLog;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use App\Support\UserAgentFriendly;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AdminUserActivityHistoryService
{
    private const MAX_ITEMS = 1500;

    private const SOURCE_LIMIT = 200;

    /**
     * @return list<array<string, mixed>>
     */
    public function userDirectory(): array
    {
        return User::query()
            ->with('role:id,slug,name')
            ->whereDoesntHave('role', fn ($role) => $role->where('slug', 'super_admin'))
            ->orderBy('name')
            ->limit(3000)
            ->get([
                'id',
                'name',
                'email',
                'username',
                'avatar_url',
                'current_verification_level',
                'verification_tier',
                'role_id',
                'created_at',
            ])
            ->map(fn (User $user) => $this->userSnapshot($user))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function timeline(User $user, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $items = collect()
            ->concat($this->userAuditEventItems($user, $from, $to))
            ->concat($this->activityLogItems($user, $from, $to))
            ->concat($this->loginItems($user, $from, $to))
            ->concat($this->adminAuditItems($user, $from, $to))
            ->concat($this->verificationAuditItems($user, $from, $to))
            ->concat($this->feedItems($user, $from, $to))
            ->concat($this->questItems($user, $from, $to))
            ->concat($this->proposalItems($user, $from, $to))
            ->concat($this->contractEventItems($user, $from, $to))
            ->concat($this->portfolioItems($user, $from, $to))
            ->concat($this->portfolioFavoriteItems($user, $from, $to))
            ->concat($this->followItems($user, $from, $to))
            ->concat($this->reviewItems($user, $from, $to))
            ->concat($this->bookmarkItems($user, $from, $to))
            ->concat($this->kycAuditItems($user, $from, $to))
            ->concat($this->staffHrAuditItems($user, $from, $to))
            ->concat($this->staffSessionItems($user, $from, $to))
            ->concat($this->staffActionItems($user, $from, $to))
            ->concat($this->staffPageActivityItems($user, $from, $to))
            ->concat($this->contentReportItems($user, $from, $to))
            ->concat($this->questCompletionEventItems($user, $from, $to))
            ->concat($this->disputeEventItems($user, $from, $to))
            ->concat($this->walletTransactionItems($user, $from, $to))
            ->concat($this->withdrawalItems($user, $from, $to))
            ->concat($this->escrowFundingItems($user, $from, $to))
            ->concat($this->patrolFlagItems($user, $from, $to))
            ->sortByDesc('occurred_at')
            ->take(self::MAX_ITEMS)
            ->values();

        $groups = $this->groupByDay($items);

        return [
            'user' => $this->userSnapshot($user),
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'from_label' => $this->formatDateLabel($from),
                'to_label' => $this->formatDateLabel($to),
            ],
            'summary' => [
                'total' => $items->count(),
                'by_category' => $items
                    ->groupBy('category')
                    ->map(fn (Collection $group, string $category) => [
                        'category' => $category,
                        'label' => $group->first()['category_label'] ?? Str::headline($category),
                        'count' => $group->count(),
                    ])
                    ->sortByDesc('count')
                    ->values()
                    ->all(),
            ],
            'groups' => $groups,
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userSnapshot(User $user): array
    {
        $user->loadMissing('role:id,slug,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'role_slug' => $user->role?->slug,
            'role_label' => $user->role?->name ?? Str::headline((string) ($user->role?->slug ?? 'user')),
            'verification_level' => (int) ($user->current_verification_level ?? $user->verification_tier ?? 0),
            'joined_at' => $user->created_at?->toIso8601String(),
            'joined_at_label' => $user->created_at ? $this->formatTimestamp($user->created_at)['occurred_at_label'] : null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function userAuditEventItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('user_audit_events')) {
            return collect();
        }

        return UserAuditEvent::query()
            ->where('user_id', $user->id)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (UserAuditEvent $event): array {
                $category = $this->categoryForAction($event->action);

                return $this->item(
                    id: 'user-audit-'.$event->id,
                    source: 'user_audit',
                    category: $category,
                    categoryLabel: $this->categoryLabel($category),
                    title: $event->title,
                    summary: $event->summary,
                    occurredAt: $event->occurred_at ?? now(),
                    meta: array_merge(
                        $this->deviceMeta($event->ip_address, $event->user_agent),
                        [
                            'action' => $event->action,
                            'subject_type' => $event->subject_type,
                            'subject_id' => $event->subject_id,
                            'details' => $event->meta,
                        ],
                    ),
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function activityLogItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('activity_logs')) {
            return collect();
        }

        return ActivityLog::query()
            ->where('subject_user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn (ActivityLog $log) => $this->item(
                id: 'activity-log-'.$log->id,
                source: 'activity_log',
                category: 'platform',
                categoryLabel: 'Platform activity',
                title: $log->title,
                summary: $log->body,
                occurredAt: $log->created_at ?? now(),
                meta: [
                    'type' => $log->type,
                    'actor_id' => $log->actor_id,
                    'details' => $log->meta,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loginItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('login_events')) {
            return collect();
        }

        return LoginEvent::query()
            ->where('user_id', $user->id)
            ->whereBetween('logged_in_at', [$from, $to])
            ->orderByDesc('logged_in_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (LoginEvent $login): array {
                $device = $this->deviceMeta($login->ip_address, $login->user_agent);

                return $this->item(
                    id: 'login-'.$login->id,
                    source: 'login',
                    category: 'security',
                    categoryLabel: 'Security',
                    title: 'Signed in',
                    summary: $device['device_label'] ?? ($login->ip_address ? 'From '.$login->ip_address : 'Session started'),
                    occurredAt: $login->logged_in_at ?? now(),
                    meta: $device,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function adminAuditItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return collect();
        }

        $userClass = User::class;

        return AdminActivityLog::query()
            ->with('actor:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($query) use ($user, $userClass): void {
                $query->where(fn ($sub) => $sub
                    ->where('subject_type', $userClass)
                    ->where('subject_id', $user->id))
                    ->orWhere('actor_user_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (AdminActivityLog $log) use ($user, $userClass): array {
                $actedOnUser = $log->subject_type === $userClass && (int) $log->subject_id === $user->id;
                $actorIsUser = (int) $log->actor_user_id === $user->id;

                return $this->item(
                    id: 'admin-audit-'.$log->id,
                    source: 'admin_audit',
                    category: 'admin',
                    categoryLabel: 'Admin console',
                    title: Str::headline(str_replace('.', ' ', $log->action)),
                    summary: $actedOnUser
                        ? 'Admin action on this account'.($log->actor ? ' by '.$log->actor->name : '')
                        : ($actorIsUser ? 'Staff action performed in admin console' : 'Admin audit entry'),
                    occurredAt: $log->created_at ?? now(),
                    meta: array_merge(
                        $this->deviceMeta($log->ip_address, $log->user_agent),
                        [
                            'action' => $log->action,
                            'actor' => $log->actor?->only(['id', 'name', 'email']),
                            'subject_type' => $log->subject_type,
                            'subject_id' => $log->subject_id,
                            'properties' => $log->properties,
                        ],
                    ),
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function verificationAuditItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('verification_engine_audit_logs')) {
            return collect();
        }

        return VerificationEngineAuditLog::query()
            ->with(['actor:id,name,email', 'affectedUser:id,name,email'])
            ->whereBetween('created_at', [$from, $to])
            ->where(fn ($query) => $query
                ->where('affected_user_id', $user->id)
                ->orWhere('actor_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn (VerificationEngineAuditLog $log) => $this->item(
                id: 'verification-audit-'.$log->id,
                source: 'verification_audit',
                category: 'verification',
                categoryLabel: 'Verification',
                title: Str::headline(str_replace('.', ' ', $log->action)),
                summary: $log->reason ?: ($log->affected_user_id === $user->id
                    ? 'Verification engine change affecting this account'
                    : 'Verification action performed by this user'),
                occurredAt: $log->created_at ?? now(),
                meta: [
                    'action' => $log->action,
                    'actor' => $log->actor?->only(['id', 'name', 'email']),
                    'affected_user' => $log->affectedUser?->only(['id', 'name', 'email']),
                    'old_value' => $log->old_value,
                    'new_value' => $log->new_value,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function feedItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('admin_activity_feed_events')) {
            return collect();
        }

        $userClass = User::class;

        return AdminActivityFeedEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->where(function ($query) use ($user, $userClass): void {
                $query->where(fn ($sub) => $sub
                    ->where('subject_type', $userClass)
                    ->where('subject_id', $user->id))
                    ->orWhere('actor_user_id', $user->id)
                    ->orWhereJsonContains('entities', [['type' => 'user', 'id' => $user->id]]);
            })
            ->orderByDesc('occurred_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn (AdminActivityFeedEvent $event) => $this->item(
                id: 'feed-'.$event->id,
                source: 'feed',
                category: 'operations',
                categoryLabel: 'Operations feed',
                title: $event->title,
                summary: $event->summary,
                occurredAt: $event->occurred_at ?? now(),
                meta: [
                    'event_key' => $event->event_key,
                    'category' => $event->category,
                    'severity' => $event->severity,
                    'details' => $event->metadata,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function questItems(User $user, Carbon $from, Carbon $to): Collection
    {
        $items = collect();

        Quest::query()
            ->where('client_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get(['id', 'title', 'reference_code', 'budget_amount_minor', 'status', 'created_at'])
            ->each(function (Quest $quest) use ($items): void {
                $items->push($this->item(
                    id: 'quest-created-'.$quest->id,
                    source: 'quest',
                    category: 'marketplace',
                    categoryLabel: 'Marketplace',
                    title: 'Posted quest',
                    summary: $quest->title.($quest->reference_code ? ' · '.$quest->reference_code : ''),
                    occurredAt: $quest->created_at ?? now(),
                    meta: $this->questMeta($quest),
                ));
            });

        Quest::query()
            ->where('client_id', $user->id)
            ->whereBetween('updated_at', [$from, $to])
            ->whereColumn('updated_at', '>', 'created_at')
            ->orderByDesc('updated_at')
            ->limit(self::SOURCE_LIMIT)
            ->get(['id', 'title', 'reference_code', 'budget_amount_minor', 'status', 'updated_at'])
            ->each(function (Quest $quest) use ($items): void {
                $items->push($this->item(
                    id: 'quest-updated-'.$quest->id.'-'.$quest->updated_at?->timestamp,
                    source: 'quest',
                    category: 'marketplace',
                    categoryLabel: 'Marketplace',
                    title: 'Updated quest',
                    summary: $quest->title.($quest->reference_code ? ' · '.$quest->reference_code : ''),
                    occurredAt: $quest->updated_at ?? now(),
                    meta: $this->questMeta($quest),
                ));
            });

        if (Schema::hasColumn('quests', 'freelancer_id')) {
            Quest::query()
                ->where('freelancer_id', $user->id)
                ->whereNotNull('freelancer_id')
                ->whereBetween('updated_at', [$from, $to])
                ->orderByDesc('updated_at')
                ->limit(80)
                ->get(['id', 'title', 'reference_code', 'status', 'updated_at'])
                ->each(function (Quest $quest) use ($items): void {
                    $items->push($this->item(
                        id: 'quest-assigned-'.$quest->id,
                        source: 'quest',
                        category: 'marketplace',
                        categoryLabel: 'Marketplace',
                        title: 'Assigned to quest',
                        summary: $quest->title.($quest->reference_code ? ' · '.$quest->reference_code : ''),
                        occurredAt: $quest->updated_at ?? now(),
                        meta: $this->questMeta($quest),
                    ));
                });
        }

        return $items;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function proposalItems(User $user, Carbon $from, Carbon $to): Collection
    {
        $items = collect();

        QuestOffer::query()
            ->with('quest:id,title,reference_code')
            ->where('freelancer_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get(['id', 'quest_id', 'quoted_amount_minor', 'status', 'created_at'])
            ->each(function (QuestOffer $offer) use ($items): void {
                $items->push($this->item(
                    id: 'proposal-created-'.$offer->id,
                    source: 'proposal',
                    category: 'marketplace',
                    categoryLabel: 'Marketplace',
                    title: 'Submitted proposal',
                    summary: $offer->quest?->title
                        ? 'On “'.$offer->quest->title.'”'
                        : 'Proposal #'.$offer->id,
                    occurredAt: $offer->created_at ?? now(),
                    meta: $this->proposalMeta($offer),
                ));
            });

        QuestOffer::query()
            ->with('quest:id,title,reference_code')
            ->where('freelancer_id', $user->id)
            ->whereBetween('updated_at', [$from, $to])
            ->whereColumn('updated_at', '>', 'created_at')
            ->orderByDesc('updated_at')
            ->limit(self::SOURCE_LIMIT)
            ->get(['id', 'quest_id', 'quoted_amount_minor', 'status', 'updated_at'])
            ->each(function (QuestOffer $offer) use ($items): void {
                $items->push($this->item(
                    id: 'proposal-updated-'.$offer->id.'-'.$offer->updated_at?->timestamp,
                    source: 'proposal',
                    category: 'marketplace',
                    categoryLabel: 'Marketplace',
                    title: 'Updated proposal',
                    summary: $offer->quest?->title
                        ? 'On “'.$offer->quest->title.'”'
                        : 'Proposal #'.$offer->id,
                    occurredAt: $offer->updated_at ?? now(),
                    meta: $this->proposalMeta($offer),
                ));
            });

        return $items;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function contractEventItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('quest_contract_events')) {
            return collect();
        }

        return QuestContractEvent::query()
            ->with(['contract:id,reference_code,quest_id', 'contract.quest:id,title,reference_code'])
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (QuestContractEvent $event): array {
                $quest = $event->contract?->quest;

                return $this->item(
                    id: 'contract-event-'.$event->id,
                    source: 'contract',
                    category: 'contracts',
                    categoryLabel: 'Contracts',
                    title: $this->contractEventTitle($event->event_type),
                    summary: $quest?->title
                        ? ($event->contract?->reference_code
                            ? $quest->title.' · '.$event->contract->reference_code
                            : $quest->title)
                        : ($event->contract?->reference_code ?? 'Contract activity'),
                    occurredAt: $event->created_at ?? now(),
                    meta: array_merge(
                        $this->deviceMeta($event->ip_address, $event->user_agent),
                        [
                            'event_type' => $event->event_type,
                            'contract_id' => $event->quest_contract_id,
                            'contract_reference' => $event->contract?->reference_code,
                            'quest_id' => $quest?->id,
                            'quest_reference' => $quest?->reference_code,
                            'details' => $event->properties,
                        ],
                    ),
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function portfolioItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('portfolios')) {
            return collect();
        }

        $items = collect();

        Portfolio::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get(['id', 'title', 'slug', 'status', 'created_at'])
            ->each(function (Portfolio $portfolio) use ($items): void {
                $items->push($this->item(
                    id: 'portfolio-created-'.$portfolio->id,
                    source: 'portfolio',
                    category: 'portfolio',
                    categoryLabel: 'Portfolio',
                    title: 'Created portfolio item',
                    summary: $portfolio->title,
                    occurredAt: $portfolio->created_at ?? now(),
                    meta: $this->portfolioMeta($portfolio),
                ));
            });

        Portfolio::query()
            ->where('user_id', $user->id)
            ->whereBetween('updated_at', [$from, $to])
            ->whereColumn('updated_at', '>', 'created_at')
            ->orderByDesc('updated_at')
            ->limit(self::SOURCE_LIMIT)
            ->get(['id', 'title', 'slug', 'status', 'updated_at'])
            ->each(function (Portfolio $portfolio) use ($items): void {
                $items->push($this->item(
                    id: 'portfolio-updated-'.$portfolio->id.'-'.$portfolio->updated_at?->timestamp,
                    source: 'portfolio',
                    category: 'portfolio',
                    categoryLabel: 'Portfolio',
                    title: 'Updated portfolio item',
                    summary: $portfolio->title,
                    occurredAt: $portfolio->updated_at ?? now(),
                    meta: $this->portfolioMeta($portfolio),
                ));
            });

        if (Schema::hasColumn('portfolios', 'published_at')) {
            Portfolio::query()
                ->where('user_id', $user->id)
                ->whereNotNull('published_at')
                ->whereBetween('published_at', [$from, $to])
                ->orderByDesc('published_at')
                ->limit(80)
                ->get(['id', 'title', 'slug', 'status', 'published_at'])
                ->each(function (Portfolio $portfolio) use ($items): void {
                    $items->push($this->item(
                        id: 'portfolio-published-'.$portfolio->id,
                        source: 'portfolio',
                        category: 'portfolio',
                        categoryLabel: 'Portfolio',
                        title: 'Published portfolio item',
                        summary: $portfolio->title,
                        occurredAt: $portfolio->published_at ?? now(),
                        meta: $this->portfolioMeta($portfolio),
                    ));
                });
        }

        return $items;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function portfolioFavoriteItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('portfolio_favorites')) {
            return collect();
        }

        return DB::table('portfolio_favorites')
            ->join('portfolios', 'portfolios.id', '=', 'portfolio_favorites.portfolio_id')
            ->where('portfolio_favorites.user_id', $user->id)
            ->whereBetween('portfolio_favorites.created_at', [$from, $to])
            ->orderByDesc('portfolio_favorites.created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get([
                'portfolio_favorites.created_at',
                'portfolios.id as portfolio_id',
                'portfolios.title',
                'portfolios.slug',
                'portfolios.user_id as owner_id',
            ])
            ->map(fn ($row) => $this->item(
                id: 'portfolio-fav-'.$row->portfolio_id.'-'.Carbon::parse($row->created_at)->timestamp,
                source: 'portfolio',
                category: 'social',
                categoryLabel: 'Social',
                title: 'Liked portfolio',
                summary: (string) $row->title,
                occurredAt: Carbon::parse($row->created_at),
                meta: [
                    'portfolio_id' => (int) $row->portfolio_id,
                    'portfolio_slug' => $row->slug,
                    'owner_id' => (int) $row->owner_id,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function followItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('user_follows')) {
            return collect();
        }

        return DB::table('user_follows')
            ->join('users as targets', 'targets.id', '=', 'user_follows.following_id')
            ->where('user_follows.follower_id', $user->id)
            ->whereBetween('user_follows.created_at', [$from, $to])
            ->orderByDesc('user_follows.created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get([
                'user_follows.created_at',
                'targets.id as target_id',
                'targets.name as target_name',
                'targets.username as target_username',
            ])
            ->map(fn ($row) => $this->item(
                id: 'follow-'.$row->target_id.'-'.Carbon::parse($row->created_at)->timestamp,
                source: 'social',
                category: 'social',
                categoryLabel: 'Social',
                title: 'Followed member',
                summary: (string) $row->target_name.($row->target_username ? ' (@'.$row->target_username.')' : ''),
                occurredAt: Carbon::parse($row->created_at),
                meta: [
                    'target_user_id' => (int) $row->target_id,
                    'target_name' => $row->target_name,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function reviewItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('reviews')) {
            return collect();
        }

        $items = collect();

        Review::query()
            ->with(['quest:id,title,reference_code', 'reviewee:id,name'])
            ->where('reviewer_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->each(function (Review $review) use ($items): void {
                $items->push($this->item(
                    id: 'review-left-'.$review->id,
                    source: 'review',
                    category: 'marketplace',
                    categoryLabel: 'Marketplace',
                    title: 'Left a review',
                    summary: $review->reviewee?->name
                        ? 'For '.$review->reviewee->name.($review->quest?->title ? ' · '.$review->quest->title : '')
                        : ($review->quest?->title ?? 'Review #'.$review->id),
                    occurredAt: $review->created_at ?? now(),
                    meta: [
                        'review_id' => $review->id,
                        'rating' => $review->rating,
                        'status' => $review->status instanceof \BackedEnum ? $review->status->value : (string) $review->status,
                        'quest_id' => $review->quest_id,
                    ],
                ));
            });

        Review::query()
            ->with(['quest:id,title,reference_code', 'reviewer:id,name'])
            ->where('reviewee_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->each(function (Review $review) use ($items): void {
                $items->push($this->item(
                    id: 'review-received-'.$review->id,
                    source: 'review',
                    category: 'marketplace',
                    categoryLabel: 'Marketplace',
                    title: 'Received a review',
                    summary: $review->reviewer?->name
                        ? 'From '.$review->reviewer->name.($review->quest?->title ? ' · '.$review->quest->title : '')
                        : ($review->quest?->title ?? 'Review #'.$review->id),
                    occurredAt: $review->created_at ?? now(),
                    meta: [
                        'review_id' => $review->id,
                        'rating' => $review->rating,
                        'status' => $review->status instanceof \BackedEnum ? $review->status->value : (string) $review->status,
                        'quest_id' => $review->quest_id,
                    ],
                ));
            });

        return $items;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function bookmarkItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('quest_bookmarks')) {
            return collect();
        }

        return DB::table('quest_bookmarks')
            ->join('quests', 'quests.id', '=', 'quest_bookmarks.quest_id')
            ->where('quest_bookmarks.user_id', $user->id)
            ->whereBetween('quest_bookmarks.created_at', [$from, $to])
            ->orderByDesc('quest_bookmarks.created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get([
                'quest_bookmarks.created_at',
                'quests.id as quest_id',
                'quests.title',
                'quests.reference_code',
            ])
            ->map(fn ($row) => $this->item(
                id: 'bookmark-'.$row->quest_id.'-'.Carbon::parse($row->created_at)->timestamp,
                source: 'quest',
                category: 'marketplace',
                categoryLabel: 'Marketplace',
                title: 'Saved quest',
                summary: (string) $row->title.($row->reference_code ? ' · '.$row->reference_code : ''),
                occurredAt: Carbon::parse($row->created_at),
                meta: [
                    'quest_id' => (int) $row->quest_id,
                    'reference_code' => $row->reference_code,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function kycAuditItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('kyc_audit_events')) {
            return collect();
        }

        return KycAuditEvent::query()
            ->with(['admin:id,name,email', 'case:id,user_id,uuid'])
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($query) use ($user): void {
                $query->where('admin_user_id', $user->id)
                    ->orWhereHas('case', fn ($case) => $case->where('user_id', $user->id));
            })
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (KycAuditEvent $event) use ($user): array {
                $isStaff = (int) $event->admin_user_id === $user->id;

                return $this->item(
                    id: 'kyc-audit-'.$event->id,
                    source: 'kyc',
                    category: $isStaff ? 'staff' : 'verification',
                    categoryLabel: $isStaff ? 'Staff HR & console' : 'Verification',
                    title: Str::headline(str_replace('.', ' ', $event->event)),
                    summary: $isStaff
                        ? 'KYC review action in admin centre'
                        : 'KYC case activity on this account',
                    occurredAt: $event->created_at ?? now(),
                    meta: array_merge(
                        $this->deviceMeta($event->ip_address, $event->user_agent),
                        [
                            'event' => $event->event,
                            'case_uuid' => $event->case?->uuid,
                            'admin' => $event->admin?->only(['id', 'name', 'email']),
                            'details' => $event->metadata,
                        ],
                    ),
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function staffHrAuditItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('staff_hr_audit_trails')) {
            return collect();
        }

        return StaffHrAuditTrail::query()
            ->with('actor:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->where(fn ($query) => $query
                ->where('actor_user_id', $user->id)
                ->orWhere('target_staff_user_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (StaffHrAuditTrail $trail) use ($user): array {
                $actorIsUser = (int) $trail->actor_user_id === $user->id;

                return $this->item(
                    id: 'staff-hr-'.$trail->id,
                    source: 'staff_hr',
                    category: 'staff',
                    categoryLabel: 'Staff HR & console',
                    title: Str::headline(str_replace('.', ' ', $trail->action_type)),
                    summary: $actorIsUser
                        ? 'HR action performed on staff record'
                        : 'HR action affecting this staff account',
                    occurredAt: $trail->created_at ?? now(),
                    meta: array_merge(
                        $this->deviceMeta($trail->ip_address, $trail->user_agent),
                        [
                            'action_type' => $trail->action_type,
                            'actor' => $trail->actor?->only(['id', 'name', 'email']),
                            'target_staff_user_id' => $trail->target_staff_user_id,
                            'before' => $trail->before_values,
                            'after' => $trail->after_values,
                            'details' => $trail->metadata,
                        ],
                    ),
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function staffSessionItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('staff_session_logs')) {
            return collect();
        }

        return DB::table('staff_session_logs')
            ->where('staff_user_id', $user->id)
            ->whereBetween('login_at', [$from, $to])
            ->orderByDesc('login_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function ($row): array {
                $device = $this->deviceMeta($row->ip_address, $row->user_agent);
                $duration = (int) $row->duration_seconds;
                $summary = $duration > 0
                    ? sprintf('Active for %s', $this->humanDuration($duration))
                    : 'Staff console session';

                return $this->item(
                    id: 'staff-session-'.$row->id,
                    source: 'staff_session',
                    category: 'staff',
                    categoryLabel: 'Staff HR & console',
                    title: 'Staff console sign-in',
                    summary: ($device['device_label'] ?? 'Staff session').' · '.$summary,
                    occurredAt: Carbon::parse($row->login_at),
                    meta: array_merge($device, [
                        'duration_seconds' => $duration,
                        'active_seconds' => (int) $row->active_seconds,
                        'actions_count' => (int) $row->actions_count,
                        'logout_at' => $row->logout_at,
                        'details' => $row->metadata ? json_decode((string) $row->metadata, true) : null,
                    ]),
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function staffActionItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('staff_action_logs')) {
            return collect();
        }

        return DB::table('staff_action_logs')
            ->where('staff_user_id', $user->id)
            ->whereBetween('acted_at', [$from, $to])
            ->orderByDesc('acted_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn ($row) => $this->item(
                id: 'staff-action-'.$row->id,
                source: 'staff_action',
                category: 'staff',
                categoryLabel: 'Staff HR & console',
                title: Str::headline(str_replace('.', ' ', (string) $row->action_type)),
                summary: $row->entity_type
                    ? trim((string) $row->entity_type).' #'.(int) $row->entity_id.($row->outcome ? ' · '.$row->outcome : '')
                    : ((string) ($row->outcome ?? 'Staff action')),
                occurredAt: Carbon::parse($row->acted_at),
                meta: [
                    'action_type' => $row->action_type,
                    'entity_type' => $row->entity_type,
                    'entity_id' => $row->entity_id,
                    'outcome' => $row->outcome,
                    'details' => $row->metadata ? json_decode((string) $row->metadata, true) : null,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function staffPageActivityItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('staff_page_activity_logs')) {
            return collect();
        }

        return DB::table('staff_page_activity_logs')
            ->where('staff_user_id', $user->id)
            ->whereBetween('activity_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('activity_date')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn ($row) => $this->item(
                id: 'staff-page-'.$row->id,
                source: 'staff_page',
                category: 'staff',
                categoryLabel: 'Staff HR & console',
                title: 'Admin section time',
                summary: Str::headline(str_replace(['_', '-'], ' ', (string) $row->section_key))
                    .' · '.(int) $row->visits.' visit(s), '.$this->humanDuration((int) $row->seconds_spent),
                occurredAt: Carbon::parse($row->activity_date)->endOfDay(),
                meta: [
                    'section_key' => $row->section_key,
                    'visits' => (int) $row->visits,
                    'seconds_spent' => (int) $row->seconds_spent,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function contentReportItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('content_reports')) {
            return collect();
        }

        return DB::table('content_reports')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->map(fn ($row) => $this->item(
                id: 'content-report-'.$row->id,
                source: 'moderation',
                category: 'platform',
                categoryLabel: 'Platform activity',
                title: 'Submitted content report',
                summary: Str::headline((string) ($row->reason ?? 'report')).' · '.class_basename((string) $row->reportable_type),
                occurredAt: Carbon::parse($row->created_at),
                meta: [
                    'reason' => $row->reason,
                    'reportable_type' => $row->reportable_type,
                    'reportable_id' => $row->reportable_id,
                    'status' => $row->status ?? null,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function questCompletionEventItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('quest_completion_events')) {
            return collect();
        }

        return QuestCompletionEvent::query()
            ->with(['quest:id,title,reference_code,client_id,freelancer_id', 'actor:id,name,email'])
            ->whereBetween('occurred_at', [$from, $to])
            ->where(function ($query) use ($user): void {
                $query->where('actor_user_id', $user->id)
                    ->orWhereHas('quest', fn ($quest) => $quest
                        ->where('client_id', $user->id)
                        ->orWhere('freelancer_id', $user->id));
            })
            ->orderByDesc('occurred_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn (QuestCompletionEvent $event) => $this->item(
                id: 'quest-completion-'.$event->id,
                source: 'quest_completion',
                category: 'finance',
                categoryLabel: 'Payments & escrow',
                title: $this->completionEventTitle($event->event_type),
                summary: $event->quest?->title
                    ? $event->quest->title.($event->quest->reference_code ? ' · '.$event->quest->reference_code : '')
                    : 'Quest activity',
                occurredAt: $event->occurred_at ?? now(),
                meta: array_merge(
                    $this->deviceMeta($event->ip_address, $event->user_agent),
                    [
                        'event_type' => $event->event_type,
                        'quest_id' => $event->quest_id,
                        'actor' => $event->actor?->only(['id', 'name', 'email']),
                        'details' => $event->meta,
                    ],
                ),
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function disputeEventItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('dispute_events')) {
            return collect();
        }

        return DisputeEvent::query()
            ->with(['dispute.quest:id,title,reference_code,client_id,freelancer_id', 'actor:id,name,email'])
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($query) use ($user): void {
                $query->where('actor_user_id', $user->id)
                    ->orWhereHas('dispute', fn ($dispute) => $dispute
                        ->where('opened_by_user_id', $user->id)
                        ->orWhereHas('quest', fn ($quest) => $quest
                            ->where('client_id', $user->id)
                            ->orWhere('freelancer_id', $user->id)));
            })
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn (DisputeEvent $event) => $this->item(
                id: 'dispute-event-'.$event->id,
                source: 'dispute',
                category: 'disputes',
                categoryLabel: 'Disputes',
                title: $this->disputeEventTitle($event->action),
                summary: $event->dispute?->quest?->title ?? 'Dispute activity',
                occurredAt: $event->created_at ?? now(),
                meta: [
                    'action' => $event->action,
                    'quest_id' => $event->dispute?->quest_id,
                    'dispute_uuid' => $event->dispute?->uuid,
                    'actor' => $event->actor?->only(['id', 'name', 'email']),
                    'details' => $event->properties,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function walletTransactionItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return collect();
        }

        return WalletTransaction::query()
            ->with('quest:id,title,reference_code')
            ->where('user_id', $user->id)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn (WalletTransaction $tx) => $this->item(
                id: 'wallet-tx-'.$tx->id,
                source: 'wallet',
                category: 'finance',
                categoryLabel: 'Payments & escrow',
                title: $this->walletTransactionTitle($tx->type, $tx->direction),
                summary: $tx->description ?: ($tx->quest?->title ?? $tx->reference),
                occurredAt: $tx->occurred_at ?? now(),
                meta: [
                    'type' => $tx->type,
                    'direction' => $tx->direction,
                    'amount_minor' => (int) $tx->amount_minor,
                    'status' => $tx->status,
                    'reference' => $tx->reference,
                    'quest_id' => $tx->quest_id,
                    'details' => $tx->meta,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function withdrawalItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('wallet_withdrawals')) {
            return collect();
        }

        return WalletWithdrawal::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn (WalletWithdrawal $withdrawal) => $this->item(
                id: 'withdrawal-'.$withdrawal->id,
                source: 'withdrawal',
                category: 'finance',
                categoryLabel: 'Payments & escrow',
                title: $this->withdrawalTitle($withdrawal->status),
                summary: $withdrawal->reference,
                occurredAt: $withdrawal->processed_at ?? $withdrawal->created_at ?? now(),
                meta: [
                    'reference' => $withdrawal->reference,
                    'amount_minor' => (int) $withdrawal->amount_minor,
                    'fee_minor' => (int) $withdrawal->fee_minor,
                    'status' => $withdrawal->status,
                    'failure_reason' => $withdrawal->failure_reason,
                    'details' => $withdrawal->meta,
                ],
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function escrowFundingItems(User $user, Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('payment_escrows')) {
            return collect();
        }

        return PaymentEscrow::query()
            ->with(['quest:id,title,reference_code'])
            ->where(function ($query) use ($user): void {
                $query->where('client_id', $user->id)
                    ->orWhere('freelancer_id', $user->id);
            })
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('funded_at', [$from, $to])
                    ->orWhereBetween('released_at', [$from, $to])
                    ->orWhereBetween('refunded_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->orderByDesc('updated_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->flatMap(function (PaymentEscrow $escrow) use ($user): Collection {
                $items = collect();
                $questLabel = $escrow->quest?->title
                    ? $escrow->quest->title.($escrow->quest->reference_code ? ' · '.$escrow->quest->reference_code : '')
                    : $escrow->reference;

                if ($escrow->funded_at !== null) {
                    $items->push($this->item(
                        id: 'escrow-funded-'.$escrow->id,
                        source: 'escrow',
                        category: 'finance',
                        categoryLabel: 'Payments & escrow',
                        title: 'Escrow funded',
                        summary: $questLabel,
                        occurredAt: $escrow->funded_at,
                        meta: [
                            'reference' => $escrow->reference,
                            'amount_minor' => (int) $escrow->amount_minor,
                            'status' => $escrow->status,
                            'role' => (int) $escrow->client_id === $user->id ? 'client' : 'freelancer',
                        ],
                    ));
                }

                if ($escrow->released_at !== null) {
                    $items->push($this->item(
                        id: 'escrow-released-'.$escrow->id,
                        source: 'escrow',
                        category: 'finance',
                        categoryLabel: 'Payments & escrow',
                        title: 'Escrow released',
                        summary: $questLabel,
                        occurredAt: $escrow->released_at,
                        meta: [
                            'reference' => $escrow->reference,
                            'released_minor' => (int) $escrow->released_minor,
                            'status' => $escrow->status,
                        ],
                    ));
                }

                if ($escrow->refunded_at !== null) {
                    $items->push($this->item(
                        id: 'escrow-refunded-'.$escrow->id,
                        source: 'escrow',
                        category: 'finance',
                        categoryLabel: 'Payments & escrow',
                        title: 'Escrow refunded',
                        summary: $questLabel,
                        occurredAt: $escrow->refunded_at,
                        meta: [
                            'reference' => $escrow->reference,
                            'refunded_minor' => (int) $escrow->refunded_minor,
                            'status' => $escrow->status,
                        ],
                    ));
                }

                return $items;
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function patrolFlagItems(User $user, Carbon $from, Carbon $to): Collection
    {
        $items = collect();

        if (Schema::hasTable('user_activity_patrol_flags')) {
            UserActivityPatrolFlag::query()
                ->where('user_id', $user->id)
                ->whereBetween('detected_at', [$from, $to])
                ->orderByDesc('detected_at')
                ->limit(80)
                ->get()
                ->each(function (UserActivityPatrolFlag $flag) use ($items): void {
                    $items->push($this->item(
                        id: 'user-patrol-'.$flag->id,
                        source: 'patrol',
                        category: 'moderation',
                        categoryLabel: 'Moderation & flags',
                        title: 'Activity patrol flag',
                        summary: $flag->summary ?: Str::headline((string) $flag->anomaly_type),
                        occurredAt: $flag->detected_at ?? now(),
                        meta: [
                            'anomaly_type' => $flag->anomaly_type,
                            'risk_level' => $flag->risk_level,
                            'status' => $flag->status,
                            'details' => $flag->meta,
                        ],
                    ));
                });
        }

        if (Schema::hasTable('quest_patrol_flags')) {
            $questIds = Quest::query()->where('client_id', $user->id)->pluck('id');
            $offerIds = QuestOffer::query()->where('freelancer_id', $user->id)->pluck('id');

            if ($questIds->isNotEmpty() || $offerIds->isNotEmpty()) {
                QuestPatrolFlag::query()
                    ->whereBetween('detected_at', [$from, $to])
                    ->where(function ($query) use ($questIds, $offerIds): void {
                        if ($questIds->isNotEmpty()) {
                            $query->orWhere(fn ($sub) => $sub
                                ->where('subject_type', QuestPatrolSubjectType::Quest->value)
                                ->whereIn('subject_id', $questIds));
                        }
                        if ($offerIds->isNotEmpty()) {
                            $query->orWhere(fn ($sub) => $sub
                                ->where('subject_type', QuestPatrolSubjectType::Proposal->value)
                                ->whereIn('subject_id', $offerIds));
                        }
                    })
                ->orderByDesc('detected_at')
                ->limit(80)
                ->get()
                ->each(function (QuestPatrolFlag $flag) use ($items): void {
                    $items->push($this->item(
                        id: 'quest-patrol-'.$flag->id,
                        source: 'patrol',
                        category: 'moderation',
                        categoryLabel: 'Moderation & flags',
                        title: 'Quest patrol flag',
                        summary: Str::headline(str_replace('_', ' ', (string) $flag->flag_type)),
                        occurredAt: $flag->detected_at ?? now(),
                        meta: [
                            'flag_type' => $flag->flag_type,
                            'severity' => $flag->severity,
                            'status' => $flag->status,
                            'subject_type' => $flag->subject_type,
                            'subject_id' => $flag->subject_id,
                            'details' => $flag->meta,
                        ],
                    ));
                });
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function item(
        string $id,
        string $source,
        string $category,
        string $categoryLabel,
        string $title,
        ?string $summary,
        Carbon $occurredAt,
        array $meta = [],
    ): array {
        $timestamps = $this->formatTimestamp($occurredAt);

        return array_merge([
            'id' => $id,
            'source' => $source,
            'category' => $category,
            'category_label' => $categoryLabel,
            'title' => $title,
            'summary' => $summary,
            'meta' => $meta,
        ], $timestamps);
    }

    /**
     * @return array<string, mixed>
     */
    private function questMeta(Quest $quest): array
    {
        return [
            'quest_id' => $quest->id,
            'reference_code' => $quest->reference_code ?? null,
            'status' => $quest->status instanceof \BackedEnum ? $quest->status->value : (string) $quest->status,
            'budget_minor' => isset($quest->budget_amount_minor) ? (int) $quest->budget_amount_minor : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalMeta(QuestOffer $offer): array
    {
        return [
            'proposal_id' => $offer->id,
            'quest_id' => $offer->quest_id,
            'quest_reference' => $offer->quest?->reference_code,
            'status' => (string) $offer->status,
            'quoted_minor' => (int) $offer->quoted_amount_minor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function portfolioMeta(Portfolio $portfolio): array
    {
        return [
            'portfolio_id' => $portfolio->id,
            'portfolio_slug' => $portfolio->slug,
            'status' => $portfolio->status instanceof \BackedEnum ? $portfolio->status->value : (string) $portfolio->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceMeta(?string $ip, ?string $userAgent): array
    {
        $details = UserAgentFriendly::details($userAgent);

        return array_filter([
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 500) : null,
            'device_label' => $details['label'],
            'browser' => $details['browser'],
            'os' => $details['os'],
            'device' => $details['device'],
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function contractEventTitle(string $eventType): string
    {
        return match ($eventType) {
            'contract.viewed' => 'Viewed contract',
            'contract.generated' => 'Contract generated',
            'contract.activated' => 'Contract activated',
            'contract.completed' => 'Contract completed',
            'contract.disputed' => 'Opened contract dispute',
            'contract.dispute_resolved' => 'Contract dispute resolved',
            'contract.cancelled' => 'Contract cancelled',
            'contract.terminated' => 'Contract terminated',
            'contract.extension_requested' => 'Requested delivery extension',
            'contract.extension_declined' => 'Declined delivery extension',
            'contract.payment_released' => 'Payment released',
            default => Str::headline(str_replace('.', ' ', $eventType)),
        };
    }

    private function completionEventTitle(string $eventType): string
    {
        return match ($eventType) {
            'escrow_funded' => 'Funded escrow',
            'deliverable_submitted' => 'Submitted deliverable',
            'delivery_revision_requested' => 'Requested delivery revision',
            'delivery_approved' => 'Approved delivery',
            'delivery_acknowledged' => 'Acknowledged delivery',
            'funds_released' => 'Released escrow funds',
            'installment_released' => 'Released installment',
            'auto_funds_released' => 'Auto-released escrow funds',
            'auto_completed' => 'Quest auto-completed',
            'auto_delivery_acknowledged' => 'Auto-acknowledged delivery',
            'dispute_settlement_executed' => 'Executed dispute settlement',
            'release_authorized' => 'Authorized escrow release',
            'release_hold' => 'Placed release hold',
            'sa_delivery_approved' => 'Staff approved delivery',
            default => Str::headline(str_replace('_', ' ', $eventType)),
        };
    }

    private function disputeEventTitle(string $action): string
    {
        return match ($action) {
            'dispute.opened' => 'Opened dispute',
            'dispute.message_added' => 'Added dispute message',
            'dispute.settlement_offered' => 'Offered dispute settlement',
            'dispute.settlement_accepted' => 'Accepted dispute settlement',
            'dispute.settlement_declined' => 'Declined dispute settlement',
            'dispute.mutual_resolve_ack' => 'Acknowledged mutual resolve',
            'dispute.escalated_silence' => 'Dispute escalated (no response)',
            'dispute.auto_timed_split' => 'Dispute auto-split executed',
            default => Str::headline(str_replace('.', ' ', $action)),
        };
    }

    private function walletTransactionTitle(string $type, string $direction): string
    {
        return match ($type) {
            'escrow_hold' => 'Escrow hold',
            'escrow_release' => 'Escrow released to wallet',
            'escrow_refund' => 'Escrow refunded',
            'withdrawal' => 'Wallet withdrawal',
            'withdrawal_fee' => 'Withdrawal fee',
            'credit' => $direction === 'credit' ? 'Wallet credit' : 'Wallet debit',
            default => Str::headline(str_replace('_', ' ', $type)),
        };
    }

    private function withdrawalTitle(string $status): string
    {
        return match ($status) {
            'pending' => 'Requested withdrawal',
            'processing' => 'Withdrawal processing',
            'completed', 'success' => 'Withdrawal completed',
            'failed' => 'Withdrawal failed',
            'reversed' => 'Withdrawal reversed',
            default => Str::headline(str_replace('_', ' ', $status)).' withdrawal',
        };
    }

    private function categoryForAction(string $action): string
    {
        if (str_starts_with($action, 'profile.') || str_starts_with($action, 'user.')) {
            return 'social';
        }

        if (str_starts_with($action, 'portfolio.')) {
            return 'portfolio';
        }

        if (str_starts_with($action, 'quest.') || str_starts_with($action, 'proposal.')) {
            return 'marketplace';
        }

        if (str_starts_with($action, 'contract.')) {
            return 'contracts';
        }

        if (str_starts_with($action, 'escrow.') || str_starts_with($action, 'wallet.') || str_starts_with($action, 'payment.')) {
            return 'finance';
        }

        if (str_starts_with($action, 'dispute.')) {
            return 'disputes';
        }

        if (str_starts_with($action, 'moderation.')) {
            return 'moderation';
        }

        return 'platform';
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'security' => 'Security',
            'platform' => 'Platform activity',
            'admin' => 'Admin console',
            'verification' => 'Verification',
            'operations' => 'Operations feed',
            'marketplace' => 'Marketplace',
            'contracts' => 'Contracts',
            'portfolio' => 'Portfolio',
            'social' => 'Social',
            'staff' => 'Staff HR & console',
            'finance' => 'Payments & escrow',
            'disputes' => 'Disputes',
            'moderation' => 'Moderation & flags',
            default => Str::headline($category),
        };
    }

    private function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
    }

    /**
     * @return array{occurred_at: string, occurred_at_label: string, relative_label: string}
     */
    private function formatTimestamp(Carbon $occurredAt): array
    {
        $localized = $occurredAt->copy()->timezone(config('app.timezone'));

        return [
            'occurred_at' => $occurredAt->toIso8601String(),
            'occurred_at_label' => $localized->isoFormat('dddd, D MMMM YYYY [at] h:mm A'),
            'relative_label' => $localized->diffForHumans(),
        ];
    }

    private function formatDateLabel(Carbon $date): string
    {
        return $date->copy()->timezone(config('app.timezone'))->isoFormat('dddd, D MMMM YYYY');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function groupByDay(Collection $items): array
    {
        return $items
            ->groupBy(fn (array $item) => Carbon::parse($item['occurred_at'])->timezone(config('app.timezone'))->toDateString())
            ->map(function (Collection $group, string $date): array {
                $day = Carbon::parse($date)->timezone(config('app.timezone'));

                return [
                    'date' => $date,
                    'date_label' => $day->isoFormat('dddd, D MMMM YYYY'),
                    'count' => $group->count(),
                    'items' => $group->values()->all(),
                ];
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }
}
