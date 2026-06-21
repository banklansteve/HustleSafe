<?php

namespace App\Http\Controllers;

use App\Services\UserNotificationInboxService;
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

        $inbox = app(UserNotificationInboxService::class);
        $data = is_array($notification->data) ? $notification->data : [];

        if (($data['kind'] ?? '') === 'quest_thread_message') {
            $qid = (int) ($data['quest_id'] ?? 0);
            $sid = (int) ($data['sender_id'] ?? 0);
            if ($qid > 0) {
                $inbox->markQuestThreadForQuest($user, $qid, $sid > 0 ? $sid : null);
            }
        }

        if (($data['kind'] ?? '') === 'support_chat') {
            $ticketId = (int) ($data['ticket_id'] ?? 0);
            if ($ticketId > 0) {
                $inbox->markSupportChatForTicket($user, $ticketId);
            }
        }

        if (($data['kind'] ?? '') === 'quest_proposal_received') {
            $qid = (int) ($data['quest_id'] ?? 0);
            $oid = (int) ($data['offer_id'] ?? 0);
            if ($qid > 0 && $oid > 0) {
                $inbox->markQuestProposalForOffer($user, $qid, $oid);
            }
        }

        if (in_array($data['kind'] ?? '', ['proposal_clarification_question', 'proposal_clarification_answer'], true)) {
            $qid = (int) ($data['quest_id'] ?? 0);
            $oid = (int) ($data['offer_id'] ?? 0);
            if ($qid > 0 && $oid > 0) {
                $inbox->markProposalClarificationForOffer($user, $qid, $oid);
            }
        }

        if (($data['kind'] ?? '') === 'conversation_policy_warning') {
            $warningId = (int) ($data['warning_id'] ?? 0);
            if ($warningId > 0) {
                $inbox->markConversationPolicyWarning($user, $warningId);
            } else {
                $inbox->markConversationPolicyWarnings($user);
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
        if ((! is_string($href) || $href === '') && is_array($data) && ! empty($data['action_url'])) {
            $parts = parse_url((string) $data['action_url']);
            if (is_array($parts) && isset($parts['path'])) {
                $href = $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
            }
        }
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
