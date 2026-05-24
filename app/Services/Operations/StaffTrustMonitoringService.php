<?php

namespace App\Services\Operations;

use App\Models\AdminActivityFeedEvent;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\StaffWatchlistItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffTrustMonitoringService
{

    public function watchlist(User $staff): array
    {
        $items = StaffWatchlistItem::query()
            ->with('watchable')
            ->where('staff_user_id', $staff->id)
            ->latest()
            ->get()
            ->map(fn (StaffWatchlistItem $item) => $this->watchlistRow($item));

        return ['items' => $items];
    }

    public function addToWatchlist(User $staff, array $data): StaffWatchlistItem
    {
        return StaffWatchlistItem::query()->updateOrCreate(
            [
                'staff_user_id' => $staff->id,
                'watchable_type' => $data['watchable_type'],
                'watchable_id' => $data['watchable_id'],
            ],
            [
                'label' => $data['label'] ?? null,
                'notes' => $data['notes'] ?? null,
                'priority' => $data['priority'] ?? 'medium',
            ],
        );
    }

    public function removeFromWatchlist(StaffWatchlistItem $item, User $staff): void
    {
        abort_unless((int) $item->staff_user_id === (int) $staff->id, 403);
        $item->delete();
    }

    public function watchlistDetail(StaffWatchlistItem $item, User $staff): array
    {
        abort_unless((int) $item->staff_user_id === (int) $staff->id, 403);
        $item->load('watchable');

        $timeline = $item->watchable_type === User::class && $item->watchable instanceof User
            ? $this->userTimeline($item->watchable)
            : [];

        return [
            'item' => $this->watchlistRow($item),
            'timeline' => $timeline,
        ];
    }

    public function riskClusters(): array
    {
        $clusters = [];

        if (Schema::hasTable('user_referrals')) {
            $ipClusters = DB::table('user_referrals')
                ->select('ip_address', DB::raw('count(distinct referred_user_id) as linked_accounts'))
                ->whereNotNull('ip_address')
                ->where('ip_address', '!=', '')
                ->groupBy('ip_address')
                ->having('linked_accounts', '>=', 2)
                ->orderByDesc('linked_accounts')
                ->limit(12)
                ->get();

            foreach ($ipClusters as $row) {
                $userIds = DB::table('user_referrals')
                    ->where('ip_address', $row->ip_address)
                    ->pluck('referred_user_id')
                    ->merge(DB::table('user_referrals')->where('ip_address', $row->ip_address)->pluck('referrer_user_id'))
                    ->unique()
                    ->take(8)
                    ->values();

                $clusters[] = [
                    'id' => 'ip:'.$row->ip_address,
                    'type' => 'shared_ip',
                    'label' => 'Shared IP · '.$row->ip_address,
                    'signal' => (int) $row->linked_accounts.' linked accounts',
                    'severity' => $row->linked_accounts >= 4 ? 'high' : 'medium',
                    'members' => User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email'])->map(fn (User $u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                    ]),
                ];
            }

            $fpClusters = DB::table('user_referrals')
                ->select('device_fingerprint', DB::raw('count(distinct referred_user_id) as linked_accounts'))
                ->whereNotNull('device_fingerprint')
                ->where('device_fingerprint', '!=', '')
                ->groupBy('device_fingerprint')
                ->having('linked_accounts', '>=', 2)
                ->orderByDesc('linked_accounts')
                ->limit(8)
                ->get();

            foreach ($fpClusters as $row) {
                $clusters[] = [
                    'id' => 'fp:'.$row->device_fingerprint,
                    'type' => 'shared_device',
                    'label' => 'Shared device fingerprint',
                    'signal' => (int) $row->linked_accounts.' accounts',
                    'severity' => 'high',
                    'members' => [],
                ];
            }
        }

        return ['clusters' => $clusters];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function userTimeline(User $user): array
    {
        if (! Schema::hasTable('admin_activity_feed_events')) {
            return [];
        }

        return AdminActivityFeedEvent::query()
            ->where(function ($q) use ($user): void {
                $q->where('subject_type', User::class)->where('subject_id', $user->id)
                    ->orWhere('actor_user_id', $user->id)
                    ->orWhereJsonContains('entities', ['user_id' => $user->id]);
            })
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (AdminActivityFeedEvent $event) => [
                'id' => $event->id,
                'title' => $event->title ?? $event->event_key,
                'body' => $event->summary,
                'created_at' => ($event->occurred_at ?? $event->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    private function watchlistRow(StaffWatchlistItem $item): array
    {
        $watchable = $item->watchable;
        $title = $item->label;
        $subtitle = null;

        if ($watchable instanceof User) {
            $title ??= $watchable->name;
            $subtitle = $watchable->email;
        } elseif ($watchable instanceof Quest) {
            $title ??= $watchable->title;
            $subtitle = $watchable->reference_code;
        } elseif ($watchable instanceof QuestOffer) {
            $title ??= 'Proposal #'.$watchable->id;
        }

        return [
            'id' => $item->id,
            'watchable_type' => class_basename($item->watchable_type),
            'watchable_id' => $item->watchable_id,
            'title' => $title ?? 'Watchlist item',
            'subtitle' => $subtitle,
            'notes' => $item->notes,
            'priority' => $item->priority,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }
}
