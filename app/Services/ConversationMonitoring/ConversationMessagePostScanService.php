<?php

namespace App\Services\ConversationMonitoring;

use App\Events\ProposalClarificationMessageSent;
use App\Events\ProposalClarificationMessageUpdated;
use App\Events\QuestConversationMessageSent;
use App\Events\QuestConversationMessageUpdated;
use App\Models\ProposalClarificationMessage;
use App\Models\Quest;
use App\Models\QuestConversationMessage;
use App\Models\QuestConversationThread;
use App\Models\User;
use App\Notifications\ProposalClarificationAnswerNotification;
use App\Notifications\ProposalClarificationQuestionNotification;
use App\Notifications\QuestThreadMessageNotification;
use App\Services\Proposals\ProposalClarificationService;
use App\Support\MessagingViewPresence;

class ConversationMessagePostScanService
{
    public function __construct(
        private readonly ConversationMessageRedactionService $redaction,
        private readonly ProposalClarificationService $clarifications,
    ) {}

    public function deliverQuestMessage(int $messageId): void
    {
        $message = QuestConversationMessage::query()
            ->with(['user:id,first_name,name,slug,avatar_url,role_id,account_type', 'user.role:id,slug', 'thread'])
            ->find($messageId);

        if ($message === null || $message->thread === null) {
            return;
        }

        $thread = $message->thread;
        $quest = Quest::query()->find($thread->quest_id);
        if ($quest === null) {
            return;
        }

        $payload = $this->questBroadcastPayload($message);

        broadcast(new QuestConversationMessageSent($thread->id, $payload));

        if ((bool) $message->is_redacted) {
            broadcast(new QuestConversationMessageUpdated($thread->id, $payload));
        }

        $this->notifyQuestRecipient($quest, $thread, $message);
    }

    public function deliverClarificationMessage(int $messageId): void
    {
        $message = ProposalClarificationMessage::query()
            ->with(['thread.offer.quest', 'thread.offer.freelancer', 'author'])
            ->find($messageId);

        if ($message === null || $message->thread === null) {
            return;
        }

        $thread = $message->thread;
        $offer = $thread->offer;
        if ($offer === null) {
            return;
        }

        $offer->loadMissing('quest');
        $message = $message->fresh();
        $formatted = $this->clarifications->formatMessage($message);
        $meta = $this->clarifications->threadMetaFor($thread, $offer, $offer->quest);

        ProposalClarificationMessageSent::dispatch($thread->id, $formatted, $meta);

        if ((bool) $message->is_redacted) {
            broadcast(new ProposalClarificationMessageUpdated($thread->id, $formatted, $meta));
        }

        $this->notifyClarificationRecipient($offer, $message);
    }

    private function notifyQuestRecipient(Quest $quest, QuestConversationThread $thread, QuestConversationMessage $message): void
    {
        $sender = $message->user;
        if ($sender === null) {
            return;
        }

        $recipient = (int) $message->user_id === (int) $thread->freelancer_id
            ? User::query()->find($thread->client_id)
            : User::query()->find($thread->freelancer_id);

        if ($recipient === null) {
            return;
        }

        $recipient->unreadNotifications()
            ->where('type', QuestThreadMessageNotification::class)
            ->get()
            ->each(function ($notification) use ($quest, $sender): void {
                $data = is_array($notification->data) ? $notification->data : [];
                if (($data['kind'] ?? '') === 'quest_thread_message'
                    && (int) ($data['quest_id'] ?? 0) === (int) $quest->id
                    && (int) ($data['sender_id'] ?? 0) === (int) $sender->id) {
                    $notification->delete();
                }
            });

        if (! MessagingViewPresence::isViewing(
            MessagingViewPresence::SCOPE_QUEST_THREAD,
            (int) $thread->id,
            (int) $recipient->id,
        )) {
            $recipient->notify(new QuestThreadMessageNotification($quest, $sender, $message));
        }
    }

    private function notifyClarificationRecipient($offer, ProposalClarificationMessage $message): void
    {
        if ($message->role === 'client') {
            $offer->freelancer?->notify(new ProposalClarificationQuestionNotification($offer, $message));

            return;
        }

        if ($message->role === 'freelancer') {
            $offer->quest?->client?->notify(new ProposalClarificationAnswerNotification($offer, $message));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function questBroadcastPayload(QuestConversationMessage $message): array
    {
        $user = $message->user;
        $redaction = $this->redaction->publicMessagePayload(
            (string) $message->body,
            (bool) $message->is_redacted,
            $message->redaction_label,
        );

        return [
            'id' => $message->id,
            ...$redaction,
            'created_at' => $message->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'sender' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'first_name' => $user?->first_name,
                'slug' => $user?->slug,
                'avatar_url' => $user?->avatar_url,
                'profile_url' => $this->freelancerPublicProfileUrl($user),
                'is_me' => false,
            ],
        ];
    }

    private function freelancerPublicProfileUrl(?User $user): ?string
    {
        if ($user === null || ! $this->userActsAsFreelancerAccount($user) || ! is_string($user->slug) || $user->slug === '') {
            return null;
        }

        return route('freelancers.public', $user->slug, absolute: false);
    }

    private function userActsAsFreelancerAccount(User $user): bool
    {
        $user->loadMissing('role');

        return $user->role?->slug === 'freelancer'
            || ($user->role?->slug === null && $user->account_type === 'hustler');
    }
}
