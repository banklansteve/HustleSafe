<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserNotificationPresenter
{
    /**
     * Unread count where multiple quest thread messages from the same sender on the same quest count as one.
     */
    public function groupedUnreadCount(User $user): int
    {
        return $this->countGrouped($user->unreadNotifications()->get());
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     */
    public function countGrouped(Collection $notifications): int
    {
        $threadKeys = [];
        $other = 0;
        foreach ($notifications as $n) {
            $key = $this->threadGroupKey($n);
            if ($key !== null) {
                $threadKeys[$key] = true;
            } else {
                $other++;
            }
        }

        return $other + count($threadKeys);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentForNav(User $user, int $limit = 8): array
    {
        $items = $user->notifications()->latest()->limit(80)->get();

        $buckets = [];
        foreach ($items as $n) {
            $key = $this->threadGroupKey($n);
            if ($key !== null) {
                $buckets[$key] ??= [];
                $buckets[$key][] = $n;
            }
        }

        $rows = [];
        foreach ($buckets as $bucket) {
            $rows[] = $this->formatThreadBucket($bucket);
        }

        foreach ($items as $n) {
            if ($this->threadGroupKey($n) !== null) {
                continue;
            }
            $rows[] = $this->formatSingleNotification($n);
        }

        usort($rows, fn (array $a, array $b) => ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0));

        return array_map(
            static fn (array $r) => Arr::except($r, ['sort_ts']),
            array_slice($rows, 0, $limit)
        );
    }

    public function threadGroupKey(object $notification): ?string
    {
        $d = is_array($notification->data) ? $notification->data : [];
        if (($d['kind'] ?? '') !== 'quest_thread_message') {
            return null;
        }
        if (! isset($d['quest_id'], $d['sender_id'])) {
            return null;
        }

        return (int) $d['quest_id'].':'.(int) $d['sender_id'];
    }

    /**
     * @param  list<DatabaseNotification>  $bucket
     * @return array<string, mixed>
     */
    protected function formatThreadBucket(array $bucket): array
    {
        $rep = $bucket[0];
        $d = is_array($rep->data) ? $rep->data : [];
        $unread = collect($bucket)->whereNull('read_at')->count();
        $preview = isset($d['preview']) && is_string($d['preview']) ? $d['preview'] : null;
        $questTitle = isset($d['quest_title']) && is_string($d['quest_title']) ? $d['quest_title'] : '';

        $href = isset($d['href']) && is_string($d['href']) && str_starts_with($d['href'], '/') ? $d['href'] : null;

        return [
            'sort_ts' => $rep->created_at?->getTimestamp() ?? 0,
            'id' => $rep->id,
            'read' => $unread === 0,
            'label' => $preview ?: (string) ($d['headline'] ?? $d['title'] ?? __('New quest message')),
            'line' => $questTitle !== '' ? $questTitle : (string) ($d['body'] ?? ''),
            'preview' => $unread > 1 ? __(':count unread from this sender', ['count' => $unread]) : null,
            'href' => $href,
            'created_at' => $rep->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'related_ids' => array_values(array_map(static fn ($x) => $x->id, $bucket)),
            'stacked_unread' => $unread,
            'data' => $d,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatSingleNotification(object $n): array
    {
        $d = is_array($n->data) ? $n->data : [];
        $href = isset($d['href']) && is_string($d['href']) && str_starts_with($d['href'], '/') ? $d['href'] : null;

        return [
            'sort_ts' => $n->created_at?->getTimestamp() ?? 0,
            'id' => $n->id,
            'read' => $n->read_at !== null,
            'label' => (string) ($d['headline'] ?? $d['title'] ?? Str::of(class_basename($n->type))->headline()),
            'line' => (string) ($d['quest_title'] ?? $d['body'] ?? $d['message'] ?? ''),
            'preview' => isset($d['preview']) && is_string($d['preview']) ? $d['preview'] : null,
            'href' => $href,
            'created_at' => $n->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'related_ids' => [$n->id],
            'stacked_unread' => $n->read_at === null ? 1 : 0,
            'data' => $d,
        ];
    }
}
