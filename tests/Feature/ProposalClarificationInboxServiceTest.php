<?php

namespace Tests\Feature;

use App\Models\ProposalClarificationMessage;
use App\Models\ProposalClarificationThread;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Proposals\ProposalClarificationInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalClarificationInboxServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_freelancer_sees_action_required_when_client_question_unanswered(): void
    {
        [$client, $freelancer, $offer, $thread, $question] = $this->seedOpenThreadWithClientQuestion();

        $snapshot = app(ProposalClarificationInboxService::class)->forOffer($offer, $freelancer);

        $this->assertNotNull($snapshot);
        $this->assertTrue($snapshot['action_required']);
        $this->assertSame('action', $snapshot['tone']);
        $this->assertSame(1, $snapshot['unanswered_questions_count']);
        $this->assertStringContainsString('clarifying question', strtolower((string) $snapshot['headline']));
    }

    public function test_client_sees_action_required_when_freelancer_answers(): void
    {
        [$client, $freelancer, $offer, $thread, $question] = $this->seedOpenThreadWithClientQuestion();

        ProposalClarificationMessage::query()->create([
            'thread_id' => $thread->id,
            'author_user_id' => $freelancer->id,
            'role' => 'freelancer',
            'prompt_key' => 'reply:'.$question->id,
            'body' => 'Yes, I can start next Monday.',
        ]);

        $snapshot = app(ProposalClarificationInboxService::class)->forOffer($offer, $client);

        $this->assertNotNull($snapshot);
        $this->assertTrue($snapshot['action_required']);
        $this->assertStringContainsString('answer', strtolower((string) $snapshot['headline']));
    }

    public function test_client_inbox_lists_waiting_state_after_question(): void
    {
        [$client, $freelancer, $offer, $thread] = $this->seedOpenThreadWithClientQuestion();
        $quest = $offer->quest;

        $inbox = app(ProposalClarificationInboxService::class)->forQuestOwner($quest, $client);

        $this->assertCount(1, $inbox);
        $this->assertFalse($inbox[0]['action_required']);
        $this->assertSame('waiting', $inbox[0]['tone']);
    }

    public function test_freelancer_dashboard_inbox_surfaces_unanswered_questions_first(): void
    {
        [$client, $freelancer, $offer] = $this->seedOpenThreadWithClientQuestion();

        $inbox = app(ProposalClarificationInboxService::class)->inboxForUser($freelancer, 5);

        $this->assertCount(1, $inbox);
        $this->assertTrue($inbox[0]['action_required']);
    }

    /**
     * @return array{0: User, 1: User, 2: QuestOffer, 3: ProposalClarificationThread, 4: ProposalClarificationMessage}
     */
    private function seedOpenThreadWithClientQuestion(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $quest = Quest::factory()->create(['client_id' => $client->id]);
        $offer = QuestOffer::factory()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'submitted',
        ]);

        $thread = ProposalClarificationThread::query()->create([
            'quest_id' => $quest->id,
            'quest_offer_id' => $offer->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'open',
            'questions_asked' => 1,
        ]);

        $question = ProposalClarificationMessage::query()->create([
            'thread_id' => $thread->id,
            'author_user_id' => $client->id,
            'role' => 'client',
            'prompt_key' => 'custom',
            'body' => 'Can you share a sample of similar work?',
        ]);

        return [$client, $freelancer, $offer->fresh(['quest']), $thread, $question];
    }
}
