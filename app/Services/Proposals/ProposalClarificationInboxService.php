<?php

namespace App\Services\Proposals;

use App\Http\Controllers\QuestClientProposalsController;
use App\Models\ProposalClarificationMessage;
use App\Models\ProposalClarificationThread;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\ConversationMonitoring\ConversationMessageRedactionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ProposalClarificationInboxService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function inboxForUser(User $user, int $limit = 10): array
    {
        $threads = $this->baseQuery($user)
            ->latest('updated_at')
            ->limit(40)
            ->get();

        return $this->mapAndSort($threads, $user, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forQuestOwner(Quest $quest, User $client, int $limit = 12): array
    {
        if ((int) $quest->client_id !== (int) $client->id) {
            return [];
        }

        $threads = ProposalClarificationThread::query()
            ->where('quest_id', $quest->id)
            ->where('questions_asked', '>', 0)
            ->with($this->eagerLoads())
            ->latest('updated_at')
            ->limit(30)
            ->get();

        return $this->mapAndSort($threads, $client, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forOffer(QuestOffer $offer, User $viewer): ?array
    {
        $thread = ProposalClarificationThread::query()
            ->where('quest_offer_id', $offer->id)
            ->with($this->eagerLoads())
            ->first();

        if ($thread === null || (int) $thread->questions_asked < 1) {
            return null;
        }

        return $this->snapshotForThread($thread, $viewer);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function badgeForOffer(QuestOffer $offer, User $viewer): ?array
    {
        $snapshot = $this->forOffer($offer, $viewer);
        if ($snapshot === null) {
            return null;
        }

        return [
            'message_count' => $snapshot['message_count'],
            'unanswered_questions_count' => $snapshot['unanswered_questions_count'],
            'action_required' => $snapshot['action_required'],
            'headline' => $snapshot['headline'],
            'preview' => $snapshot['preview'],
            'tone' => $snapshot['tone'],
            'clarify_url' => $snapshot['clarify_url'],
            'latest_at' => $snapshot['latest_at'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshotForThread(ProposalClarificationThread $thread, User $viewer): ?array
    {
        $thread->loadMissing($this->eagerLoads());

        if ((int) $thread->questions_asked < 1 && $thread->messages->isEmpty()) {
            return null;
        }

        $offer = $thread->offer;
        $quest = $thread->quest ?? $offer?->quest;
        if ($offer === null || $quest === null) {
            return null;
        }

        $isClient = (int) $viewer->id === (int) $thread->client_id;
        $isFreelancer = (int) $viewer->id === (int) $thread->freelancer_id;
        if (! $isClient && ! $isFreelancer) {
            return null;
        }

        /** @var Collection<int, ProposalClarificationMessage> $messages */
        $messages = $thread->messages->sortBy('created_at')->values();
        if ($messages->isEmpty()) {
            return null;
        }

        $unansweredQuestions = $this->unansweredClientQuestions($messages);
        $latestMessage = $messages->last();
        $preview = $this->previewForMessage($latestMessage);

        $freelancerName = QuestClientProposalsController::freelancerDisplayName($offer->freelancer ?? new User);
        $actionRequired = false;
        $tone = 'info';
        $headline = __('Clarification thread');

        if ($isFreelancer && $unansweredQuestions->isNotEmpty()) {
            $actionRequired = true;
            $tone = 'action';
            $count = $unansweredQuestions->count();
            $headline = $count === 1
                ? __('The client asked a clarifying question')
                : __('The client asked :count clarifying questions', ['count' => $count]);
        } elseif ($isClient && $latestMessage->role === 'freelancer') {
            $actionRequired = true;
            $tone = 'action';
            $headline = __('New answer from :name', ['name' => $freelancerName]);
        } elseif ($isClient && $unansweredQuestions->isNotEmpty()) {
            $tone = 'waiting';
            $count = $unansweredQuestions->count();
            $headline = $count === 1
                ? __('Waiting for :name to answer your question', ['name' => $freelancerName])
                : __('Waiting for :name to answer :count questions', ['name' => $freelancerName, 'count' => $count]);
        } elseif ($isFreelancer) {
            $headline = __('Clarification thread with the client');
        } else {
            $headline = __('Clarification thread with :name', ['name' => $freelancerName]);
        }

        return [
            'thread_id' => $thread->id,
            'offer_id' => $offer->id,
            'quest_id' => $quest->id,
            'quest_title' => $quest->title,
            'quest_route_key' => $quest->getRouteKey(),
            'counterparty_name' => $isClient ? $freelancerName : ($quest->client?->first_name ?: $quest->client?->name ?: __('Client')),
            'message_count' => $messages->count(),
            'unanswered_questions_count' => $unansweredQuestions->count(),
            'action_required' => $actionRequired,
            'headline' => $headline,
            'preview' => $preview,
            'latest_at' => $latestMessage->created_at?->toIso8601String(),
            'clarify_url' => route('quests.proposals.clarify', [$quest, $offer]),
            'proposal_url' => route('quests.proposals.show', [$quest->getRouteKey(), $offer->id]),
            'tone' => $tone,
            'thread_status' => $thread->status,
        ];
    }

    /**
     * @return list<string>
     */
    private function eagerLoads(): array
    {
        return [
            'messages' => fn ($query) => $query->orderBy('created_at'),
            'offer.quest:id,uuid,slug,title,status,client_id',
            'offer.freelancer:id,first_name,last_name,name,slug,avatar_url',
            'quest:id,uuid,slug,title,status,client_id',
            'quest.client:id,first_name,name',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ProposalClarificationThread>
     */
    private function baseQuery(User $user)
    {
        return ProposalClarificationThread::query()
            ->where('questions_asked', '>', 0)
            ->where(function ($query) use ($user): void {
                $query->where('client_id', $user->id)
                    ->orWhere('freelancer_id', $user->id);
            })
            ->with($this->eagerLoads());
    }

    /**
     * @param  Collection<int, ProposalClarificationThread>  $threads
     * @return list<array<string, mixed>>
     */
    private function mapAndSort(Collection $threads, User $viewer, int $limit): array
    {
        return $threads
            ->map(fn (ProposalClarificationThread $thread) => $this->snapshotForThread($thread, $viewer))
            ->filter()
            ->sortByDesc(function (array $row): string {
                return ($row['action_required'] ? '1' : '0').($row['latest_at'] ?? '');
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ProposalClarificationMessage>  $messages
     * @return Collection<int, ProposalClarificationMessage>
     */
    private function unansweredClientQuestions(Collection $messages): Collection
    {
        return $messages
            ->where('role', 'client')
            ->filter(function (ProposalClarificationMessage $question) use ($messages): bool {
                return ! $messages->contains(
                    fn (ProposalClarificationMessage $message) => $message->role === 'freelancer'
                        && $message->prompt_key === 'reply:'.$question->id,
                );
            })
            ->values();
    }

    private function previewForMessage(ProposalClarificationMessage $message): string
    {
        $payload = app(ConversationMessageRedactionService::class)->publicMessagePayload(
            (string) $message->body,
            (bool) $message->is_redacted,
            $message->redaction_label,
        );

        return Str::limit(trim(strip_tags((string) ($payload['body'] ?? ''))), 160);
    }
}
