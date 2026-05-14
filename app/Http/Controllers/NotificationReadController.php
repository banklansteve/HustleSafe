<?php

namespace App\Http\Controllers;

use App\Notifications\QuestThreadMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationReadController extends Controller
{
    public function __invoke(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $notification = $user->notifications()->whereKey($id)->first();
        if ($notification === null) {
            abort(404);
        }

        $notification->markAsRead();

        $also = (string) $request->query('also', '');
        if ($also !== '') {
            foreach (array_filter(array_map('trim', explode(',', $also))) as $aid) {
                if ($aid === '' || $aid === $id) {
                    continue;
                }
                $user->notifications()->whereKey($aid)->first()?->markAsRead();
            }
        }

        $data = $notification->data;
        if (is_array($data) && ($data['kind'] ?? '') === 'quest_thread_message') {
            $qid = (int) ($data['quest_id'] ?? 0);
            $sid = (int) ($data['sender_id'] ?? 0);
            if ($qid > 0 && $sid > 0) {
                $user->unreadNotifications()
                    ->where('type', QuestThreadMessageNotification::class)
                    ->get()
                    ->each(function ($n) use ($qid, $sid, $notification): void {
                        if ((string) $n->getKey() === (string) $notification->getKey()) {
                            return;
                        }
                        $d = is_array($n->data) ? $n->data : [];
                        if (($d['kind'] ?? '') === 'quest_thread_message'
                            && (int) ($d['quest_id'] ?? 0) === $qid
                            && (int) ($d['sender_id'] ?? 0) === $sid) {
                            $n->markAsRead();
                        }
                    });
            }
        }

        $target = $this->resolveTargetUrl($notification->data);

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => $target,
            ]);
        }

        return redirect()->to($target);
    }

    protected function resolveTargetUrl(mixed $data): string
    {
        $href = is_array($data) ? ($data['href'] ?? null) : null;
        if (is_string($href) && $href !== '') {
            if (str_starts_with($href, '/')) {
                return $href;
            }
            $parts = parse_url($href);
            if (is_array($parts) && isset($parts['path'])) {
                $path = $parts['path'];
                $query = isset($parts['query']) ? '?'.$parts['query'] : '';

                return $path.$query;
            }
        }

        return route('dashboard').'#notifications';
    }
}
