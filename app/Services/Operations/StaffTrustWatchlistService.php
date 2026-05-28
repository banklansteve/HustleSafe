<?php

namespace App\Services\Operations;

use App\Models\AdminNotification;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\StaffWatchlistFeedEvent;
use App\Models\StaffWatchlistItem;
use App\Models\User;
use App\Models\UserRiskProfile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffTrustWatchlistService
{
    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function watchlist(User $staff, bool $includeAllForSuperAdmin = false): array
    {
        $query = StaffWatchlistItem::query()
            ->with(['watchable', 'staff:id,name,email'])
            ->where('watchable_type', User::class);

        if ($includeAllForSuperAdmin) {
            // all personal + team
        } else {
            $query->where(function ($q) use ($staff): void {
                $q->where('staff_user_id', $staff->id)
                    ->orWhere('visibility', 'team');
            });
        }

        $items = $query->latest()->get()->map(fn (StaffWatchlistItem $item) => $this->watchlistRow($item, $staff));

        return ['items' => $items->all()];
    }

    public function addToWatchlist(User $staff, array $data): StaffWatchlistItem
    {
        $visibility = $data['visibility'] ?? 'personal';
        $watchableType = $data['watchable_type'] ?? User::class;

        return StaffWatchlistItem::query()->updateOrCreate(
            [
                'staff_user_id' => $staff->id,
                'watchable_type' => $watchableType,
                'watchable_id' => $data['watchable_id'],
                'visibility' => $visibility,
            ],
            [
                'label' => $data['label'] ?? null,
                'reason' => Str::limit((string) ($data['reason'] ?? $data['notes'] ?? ''), 300),
                'notes' => $data['notes'] ?? null,
                'review_by_date' => $data['review_by_date'] ?? null,
                'severity' => $data['severity'] ?? 'observe',
                'priority' => $this->severityToPriority($data['severity'] ?? 'observe'),
            ],
        );
    }

    public function removeFromWatchlist(StaffWatchlistItem $item, User $staff, bool $isSuperAdmin = false): void
    {
        if (! $isSuperAdmin && (int) $item->staff_user_id !== (int) $staff->id) {
            abort(403);
        }
        $item->delete();
    }

    public function watchlistDetail(StaffWatchlistItem $item, User $staff, bool $isSuperAdmin = false): array
    {
        if (! $isSuperAdmin && (int) $item->staff_user_id !== (int) $staff->id && $item->visibility !== 'team') {
            abort(403);
        }

        $item->load(['watchable', 'staff:id,name,email']);
        $timeline = [];
        if ($item->watchable instanceof User) {
            $timeline = $this->userFeedForStaff($staff, $item->watchable, $isSuperAdmin);
        }

        return [
            'item' => $this->watchlistRow($item, $staff),
            'timeline' => $timeline,
        ];
    }

    /**
     * @return array{groups: list<array<string, mixed>>}
     */
    public function feed(User $staff, bool $isSuperAdmin = false): array
    {
        $watchedUserIds = $this->visibleWatchedUserIds($staff, $isSuperAdmin);
        if ($watchedUserIds === []) {
            return ['groups' => []];
        }

        $events = StaffWatchlistFeedEvent::query()
            ->with('watchedUser:id,name,email')
            ->whereIn('watched_user_id', $watchedUserIds)
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        $groups = $events
            ->groupBy('watched_user_id')
            ->map(function ($group, $userId) {
                $user = $group->first()->watchedUser;

                return [
                    'user' => [
                        'id' => (int) $userId,
                        'name' => $user?->name,
                        'email' => $user?->email,
                    ],
                    'events' => $group->take(20)->map(fn (StaffWatchlistFeedEvent $e) => $this->feedEventRow($e))->values()->all(),
                ];
            })
            ->values()
            ->all();

        return ['groups' => $groups];
    }

    public function recordActivity(
        User $watchedUser,
        string $eventType,
        string $title,
        ?string $summary = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionUrl = null,
        string $severity = 'observe',
        ?array $payload = null,
    ): void {
        if (! Schema::hasTable('staff_watchlist_feed_events')) {
            return;
        }

        $watchers = StaffWatchlistItem::query()
            ->where('watchable_type', User::class)
            ->where('watchable_id', $watchedUser->id)
            ->get();

        if ($watchers->isEmpty()) {
            return;
        }

        $event = StaffWatchlistFeedEvent::query()->create([
            'watched_user_id' => $watchedUser->id,
            'staff_watchlist_item_id' => $watchers->first()->id,
            'event_type' => $eventType,
            'severity' => $severity,
            'title' => $title,
            'summary' => $summary,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action_url' => $actionUrl,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        $staffIds = $watchers->pluck('staff_user_id')->unique();
        $urgent = $severity === 'urgent';

        foreach ($staffIds as $staffId) {
            $item = $watchers->firstWhere('staff_user_id', $staffId);
            if ($urgent || ($item && $item->severity === 'urgent')) {
                $this->notifyStaff((int) $staffId, $watchedUser, $title, $actionUrl, $urgent);
            }
        }
    }

    public function handleRiskScoreChange(User $user, int $previousScore, int $newScore, string $fromTier, string $toTier): void
    {
        $delta = abs($newScore - $previousScore);
        $threshold = (int) config('trust_risk.score_change_feed_threshold', 10);

        if ($delta >= $threshold) {
            $this->recordActivity(
                $user,
                'risk_score_change',
                "Risk score changed by {$delta} points",
                "Score moved from {$previousScore} to {$newScore}.",
                null,
                null,
                route('operations.trust.index', ['user' => $user->id]),
                $delta >= 20 ? 'urgent' : 'concern',
                ['previous' => $previousScore, 'current' => $newScore],
            );
        }

        if ($fromTier !== $toTier) {
            $watchers = StaffWatchlistItem::query()
                ->where('watchable_type', User::class)
                ->where('watchable_id', $user->id)
                ->get();

            foreach ($watchers as $item) {
                $staff = User::query()->find($item->staff_user_id);
                if ($staff) {
                    $this->notifyStaffTierCrossing($staff, $user, $fromTier, $toTier);
                }
            }
        }
    }

    public function notifyStaffTierCrossing(User $staff, User $watched, string $fromTier, string $toTier): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'quality',
            'priority' => in_array($toTier, ['high', 'critical'], true) ? 'high' : 'normal',
            'title' => 'Watched user risk tier changed',
            'body' => "{$watched->name} moved from {$fromTier} to {$toTier} risk.",
            'action_label' => 'Open trust monitoring',
            'action_url' => route('operations.trust.index', ['user' => $watched->id]),
            'data' => [
                'dedupe_key' => "watchlist_tier:{$watched->id}:{$toTier}:".now()->toDateString(),
                'watched_user_id' => $watched->id,
            ],
        ]);
    }

    /**
     * @return array{on_watchlist: bool, in_risk_queue: bool}
     */
    public function userOnWatchlistSummary(User $user): array
    {
        $onWatchlist = StaffWatchlistItem::query()
            ->where('watchable_type', User::class)
            ->where('watchable_id', $user->id)
            ->exists();

        $inQueue = UserRiskProfile::query()
            ->where('user_id', $user->id)
            ->where('in_risk_queue', true)
            ->exists();

        return ['on_watchlist' => $onWatchlist, 'in_risk_queue' => $inQueue];
    }

    private function watchlistRow(StaffWatchlistItem $item, User $viewer): array
    {
        $watchable = $item->watchable;
        $userId = $watchable instanceof User ? $watchable->id : null;
        $profile = $userId
            ? UserRiskProfile::query()->where('user_id', $userId)->first()
            : null;

        return [
            'id' => $item->id,
            'watchable_type' => class_basename($item->watchable_type),
            'watchable_id' => $item->watchable_id,
            'title' => $item->label ?? ($watchable instanceof User ? $watchable->name : 'Watchlist item'),
            'subtitle' => $watchable instanceof User ? $watchable->email : null,
            'reason' => $item->reason,
            'notes' => $item->notes,
            'severity' => $item->severity ?? 'observe',
            'priority' => $item->priority,
            'visibility' => $item->visibility ?? 'personal',
            'review_by_date' => $item->review_by_date?->toDateString(),
            'created_at' => $item->created_at?->toIso8601String(),
            'created_by' => $item->staff?->only(['id', 'name', 'email']),
            'is_mine' => (int) $item->staff_user_id === (int) $viewer->id,
            'in_risk_queue' => (bool) ($profile?->in_risk_queue ?? false),
            'risk_score' => (int) ($profile?->composite_score ?? 0),
            'risk_tier' => $profile?->tier,
        ];
    }

    private function feedEventRow(StaffWatchlistFeedEvent $e): array
    {
        return [
            'id' => $e->id,
            'event_type' => $e->event_type,
            'severity' => $e->severity,
            'title' => $e->title,
            'summary' => $e->summary,
            'action_url' => $e->action_url,
            'occurred_at' => $e->occurred_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<int>
     */
    private function visibleWatchedUserIds(User $staff, bool $isSuperAdmin): array
    {
        $query = StaffWatchlistItem::query()
            ->where('watchable_type', User::class);

        if (! $isSuperAdmin) {
            $query->where(fn ($q) => $q->where('staff_user_id', $staff->id)->orWhere('visibility', 'team'));
        }

        return $query->pluck('watchable_id')->map(fn ($id) => (int) $id)->unique()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function userFeedForStaff(User $staff, User $watched, bool $isSuperAdmin): array
    {
        if (! Schema::hasTable('staff_watchlist_feed_events')) {
            return [];
        }

        $allowed = $this->visibleWatchedUserIds($staff, $isSuperAdmin);
        if (! in_array($watched->id, $allowed, true)) {
            return [];
        }

        return StaffWatchlistFeedEvent::query()
            ->where('watched_user_id', $watched->id)
            ->orderByDesc('occurred_at')
            ->limit(40)
            ->get()
            ->map(fn (StaffWatchlistFeedEvent $e) => $this->feedEventRow($e))
            ->all();
    }

    private function notifyStaff(int $staffId, User $watched, string $title, ?string $url, bool $urgent): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        AdminNotification::query()->create([
            'admin_user_id' => $staffId,
            'category' => 'quality',
            'priority' => $urgent ? 'critical' : 'high',
            'title' => $urgent ? 'Urgent watchlist activity' : 'Watchlist activity',
            'body' => "{$watched->name}: {$title}",
            'action_label' => 'View feed',
            'action_url' => $url ?? route('operations.trust.index'),
            'data' => ['dedupe_key' => 'watchlist:'.md5($title.$watched->id.now()->format('Y-m-d-H'))],
        ]);
    }

    private function severityToPriority(string $severity): string
    {
        return match ($severity) {
            'urgent' => 'critical',
            'concern' => 'high',
            default => 'medium',
        };
    }
}
