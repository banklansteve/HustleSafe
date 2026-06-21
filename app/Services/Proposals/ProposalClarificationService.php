<?php

namespace App\Services\Proposals;

use App\Models\ProposalClarificationMessage;
use App\Models\ProposalClarificationThread;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\ConversationMonitoring\ConversationMessageRedactionService;
use App\Support\ConversationScanDispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalClarificationService
{
    public function __construct(
        private readonly ProposalClarificationPromptService $prompts,
    ) {}

    public function threadForOffer(QuestOffer $offer): ProposalClarificationThread
    {
        $offer->loadMissing('quest');

        return ProposalClarificationThread::query()->firstOrCreate(
            ['quest_offer_id' => $offer->id],
            [
                'quest_id' => $offer->quest_id,
                'client_id' => $offer->quest->client_id,
                'freelancer_id' => $offer->freelancer_id,
                'status' => 'open',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadFor(QuestOffer $offer, User $viewer): array
    {
        $offer->loadMissing(['quest.client:id,first_name,name,avatar_url', 'freelancer:id,first_name,name,slug,avatar_url']);
        $quest = $offer->quest;
        $thread = $this->threadForOffer($offer);
        $thread->load([
            'messages' => fn ($query) => $query
                ->orderByDesc('created_at')
                ->with('author:id,first_name,name,avatar_url'),
        ]);

        $isClient = (int) $viewer->id === (int) $quest->client_id;
        $isFreelancer = (int) $viewer->id === (int) $offer->freelancer_id;

        return [
            'is_client' => $isClient,
            'is_freelancer' => $isFreelancer,
            'thread' => [
                'id' => $thread->id,
                'status' => $thread->status,
                'questions_asked' => $thread->questions_asked,
                'max_questions' => ProposalClarificationPromptService::MAX_QUESTIONS,
                'can_ask' => $isClient && $thread->isOpen() && $thread->questions_asked < ProposalClarificationPromptService::MAX_QUESTIONS
                    && in_array($offer->status, ['submitted', 'shortlisted'], true)
                    && $quest->status->value === 'open',
                'can_answer' => $isFreelancer && $thread->isOpen(),
                'messages' => $thread->messages->map(fn (ProposalClarificationMessage $m) => $this->formatMessage($m))->values()->all(),
            ],
            'suggested_prompts' => $isClient ? $this->prompts->suggestedPrompts($quest, $offer) : [],
            'offer' => [
                'id' => $offer->id,
                'uuid' => $offer->uuid,
                'reference_code' => $offer->reference_code,
                'route_key' => $offer->getRouteKey(),
                'status' => $offer->status,
                'freelancer' => [
                    'name' => $offer->freelancer?->name ?: $offer->freelancer?->first_name,
                    'slug' => $offer->freelancer?->slug,
                ],
            ],
            'client' => [
                'name' => $quest->client?->name ?: $quest->client?->first_name,
            ],
            'quest' => [
                'title' => $quest->title,
                'route_key' => $quest->getRouteKey(),
                'status' => $quest->status->value,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatMessage(ProposalClarificationMessage $message): array
    {
        $message->loadMissing('author:id,first_name,name,avatar_url');
        $redaction = app(ConversationMessageRedactionService::class)->publicMessagePayload(
            (string) $message->body,
            (bool) $message->is_redacted,
            $message->redaction_label,
        );

        return [
            'id' => $message->id,
            'role' => $message->role,
            'prompt_key' => $message->prompt_key,
            'prompt_category' => $message->prompt_category,
            ...$redaction,
            'author' => [
                'name' => $message->author?->name ?: $message->author?->first_name,
                'avatar_url' => $message->author?->avatar_url,
            ],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function threadMetaFor(ProposalClarificationThread $thread, QuestOffer $offer, Quest $quest): array
    {
        $thread->refresh();

        return [
            'questions_asked' => $thread->questions_asked,
            'can_ask' => $thread->isOpen()
                && $thread->questions_asked < ProposalClarificationPromptService::MAX_QUESTIONS
                && in_array($offer->status, ['submitted', 'shortlisted'], true)
                && $quest->status->value === 'open',
            'can_answer' => $thread->isOpen(),
        ];
    }

    public function askQuestion(QuestOffer $offer, User $client, string $body, ?string $promptKey = null, ?string $promptCategory = null): ProposalClarificationMessage
    {
        $offer->loadMissing('quest');
        if ((int) $offer->quest->client_id !== (int) $client->id) {
            abort(403);
        }

        if (! in_array($offer->status, ['submitted', 'shortlisted'], true) || $offer->quest->status->value !== 'open') {
            throw ValidationException::withMessages(['body' => __('Clarifications are only available before you award a proposal.')]);
        }

        $body = trim($body);
        if (strlen($body) < 20) {
            throw ValidationException::withMessages(['body' => __('Please ask a complete question (at least 20 characters).')]);
        }

        return DB::transaction(function () use ($offer, $client, $body, $promptKey, $promptCategory): ProposalClarificationMessage {
            $thread = $this->threadForOffer($offer);
            if (! $thread->isOpen()) {
                throw ValidationException::withMessages(['body' => __('This clarification thread is closed.')]);
            }
            if ($thread->questions_asked >= ProposalClarificationPromptService::MAX_QUESTIONS) {
                throw ValidationException::withMessages(['body' => __('You have reached the maximum number of pre-award questions for this proposal.')]);
            }

            $message = ProposalClarificationMessage::query()->create([
                'thread_id' => $thread->id,
                'author_user_id' => $client->id,
                'role' => 'client',
                'prompt_key' => $promptKey,
                'prompt_category' => $promptCategory,
                'body' => $body,
            ]);

            $thread->increment('questions_asked');
            $thread->refresh();

            ConversationScanDispatch::clarificationMessage($message->id);

            return $message->fresh();
        });
    }

    public function postAnswer(QuestOffer $offer, User $freelancer, string $body, int $replyToMessageId): ProposalClarificationMessage
    {
        $offer->loadMissing('quest');
        if ((int) $offer->freelancer_id !== (int) $freelancer->id) {
            abort(403);
        }

        $body = trim($body);
        if (strlen($body) < 15) {
            throw ValidationException::withMessages(['body' => __('Please give a helpful answer (at least 15 characters).')]);
        }

        $thread = $this->threadForOffer($offer);
        if (! $thread->isOpen()) {
            throw ValidationException::withMessages(['body' => __('This clarification thread is closed.')]);
        }

        $question = ProposalClarificationMessage::query()
            ->where('thread_id', $thread->id)
            ->where('id', $replyToMessageId)
            ->where('role', 'client')
            ->firstOrFail();

        $alreadyAnswered = ProposalClarificationMessage::query()
            ->where('thread_id', $thread->id)
            ->where('role', 'freelancer')
            ->where('prompt_key', 'reply:'.$question->id)
            ->exists();

        if ($alreadyAnswered) {
            throw ValidationException::withMessages(['body' => __('You have already answered this question.')]);
        }

        $message = ProposalClarificationMessage::query()->create([
            'thread_id' => $thread->id,
            'author_user_id' => $freelancer->id,
            'role' => 'freelancer',
            'prompt_key' => 'reply:'.$question->id,
            'prompt_category' => $question->prompt_category,
            'body' => $body,
        ]);

        ConversationScanDispatch::clarificationMessage($message->id);

        return $message->fresh();
    }

    public function closeForOffer(QuestOffer $offer): void
    {
        ProposalClarificationThread::query()
            ->where('quest_offer_id', $offer->id)
            ->where('status', 'open')
            ->update(['status' => 'closed', 'closed_at' => now()]);
    }
}
