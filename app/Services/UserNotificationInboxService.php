<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Marks Laravel database notifications read when the user has seen the related surface.
 */
class UserNotificationInboxService
{
    public function markReadWhere(User $user, callable $matches): int
    {
        $count = 0;

        $user->unreadNotifications()->get()->each(function (DatabaseNotification $notification) use ($matches, &$count): void {
            $data = is_array($notification->data) ? $notification->data : [];
            if ($matches($notification, $data)) {
                $notification->markAsRead();
                $count++;
            }
        });

        return $count;
    }

    public function markSupportChatForTicket(User $user, int $ticketId): int
    {
        $count = $this->markReadWhere($user, function (DatabaseNotification $notification, array $data) use ($ticketId): bool {
            return (int) ($data['ticket_id'] ?? 0) === $ticketId;
        });

        $count += $this->markReadWhere($user, function (DatabaseNotification $notification, array $data): bool {
            return ($data['source'] ?? '') === 'admin_team'
                && empty($data['ticket_id'])
                && str_contains(strtolower((string) ($data['title'] ?? '')), 'support');
        });

        return $count;
    }

    public function markQuestThreadForQuest(User $user, int $questId, ?int $senderId = null): int
    {
        return $this->markReadWhere($user, function (DatabaseNotification $notification, array $data) use ($questId, $senderId): bool {
            if (($data['kind'] ?? '') !== 'quest_thread_message') {
                return false;
            }

            if ((int) ($data['quest_id'] ?? 0) !== $questId) {
                return false;
            }

            if ($senderId === null) {
                return true;
            }

            return (int) ($data['sender_id'] ?? 0) === $senderId;
        });
    }

    public function markQuestProposalForOffer(User $user, int $questId, int $offerId): int
    {
        return $this->markReadWhere($user, function (DatabaseNotification $notification, array $data) use ($questId, $offerId): bool {
            if (($data['kind'] ?? '') !== 'quest_proposal_received') {
                return false;
            }

            if ((int) ($data['quest_id'] ?? 0) === $questId && (int) ($data['offer_id'] ?? 0) === $offerId) {
                return true;
            }

            $href = (string) ($data['href'] ?? '');

            return $href !== '' && str_contains($href, "/proposals/{$offerId}");
        });
    }

    public function markProposalClarificationForOffer(User $user, int $questId, int $offerId): int
    {
        return $this->markReadWhere($user, function (DatabaseNotification $notification, array $data) use ($questId, $offerId): bool {
            $kind = (string) ($data['kind'] ?? '');
            if (! in_array($kind, ['proposal_clarification_question', 'proposal_clarification_answer'], true)) {
                return false;
            }

            if ((int) ($data['quest_id'] ?? 0) === $questId && (int) ($data['offer_id'] ?? 0) === $offerId) {
                return true;
            }

            $href = (string) ($data['href'] ?? '');

            return $href !== '' && str_contains($href, "/proposals/{$offerId}/clarify");
        });
    }

    public function markConversationPolicyWarnings(User $user): int
    {
        return $this->markReadWhere($user, fn (DatabaseNotification $notification, array $data): bool => ($data['kind'] ?? '') === 'conversation_policy_warning');
    }

    public function markConversationPolicyWarning(User $user, int $warningId): int
    {
        return $this->markReadWhere($user, function (DatabaseNotification $notification, array $data) use ($warningId): bool {
            if (($data['kind'] ?? '') !== 'conversation_policy_warning') {
                return false;
            }

            $id = (int) ($data['warning_id'] ?? 0);

            return $id === 0 || $id === $warningId;
        });
    }
}
