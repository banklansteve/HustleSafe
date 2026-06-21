<?php

namespace Tests\Feature;

use App\Models\ConversationMessageFlag;
use App\Models\ConversationThreadReview;
use App\Models\ProposalClarificationMessage;
use App\Models\ProposalClarificationThread;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\Role;
use App\Models\User;
use App\Services\ConversationMonitoring\ConversationMonitoringAdminService;
use App\Services\ConversationMonitoring\ConversationMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalClarificationMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_freelancer_clarification_answer_scanned_for_off_platform_language(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('conversation_message_flags')) {
            $this->markTestSkipped('Conversation monitoring tables are not migrated.');
        }

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
        ]);

        $message = ProposalClarificationMessage::query()->create([
            'thread_id' => $thread->id,
            'author_user_id' => $freelancer->id,
            'role' => 'freelancer',
            'prompt_key' => 'reply:1',
            'body' => 'Please pay me directly outside the platform using my account number 0123456789.',
        ]);

        app(ConversationMonitoringService::class)->processClarificationMessage($message);

        $message->refresh();
        $this->assertTrue($message->is_redacted);
        $this->assertNotNull($message->redaction_label);
        $this->assertStringNotContainsString('0123456789', (string) $message->body);

        $this->assertDatabaseHas('conversation_message_flags', [
            'proposal_clarification_message_id' => $message->id,
            'proposal_clarification_thread_id' => $thread->id,
            'sender_user_id' => $freelancer->id,
            'trigger_category' => 'off_platform_payment',
        ]);

        $this->assertDatabaseHas('conversation_thread_reviews', [
            'proposal_clarification_thread_id' => $thread->id,
            'quest_id' => $quest->id,
            'status' => 'pending',
        ]);
    }

    public function test_client_typed_clarification_question_scanned_for_abusive_terms(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('conversation_message_flags')) {
            $this->markTestSkipped('Conversation monitoring tables are not migrated.');
        }

        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $quest = Quest::factory()->create(['client_id' => $client->id]);
        $offer = QuestOffer::factory()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'shortlisted',
        ]);

        $thread = ProposalClarificationThread::query()->create([
            'quest_id' => $quest->id,
            'quest_offer_id' => $offer->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'open',
        ]);

        $message = ProposalClarificationMessage::query()->create([
            'thread_id' => $thread->id,
            'author_user_id' => $client->id,
            'role' => 'client',
            'prompt_key' => 'custom',
            'body' => 'This looks like a scam proposal and I want a threat-free answer please.',
        ]);

        app(ConversationMonitoringService::class)->processClarificationMessage($message);

        $this->assertTrue(
            ConversationMessageFlag::query()
                ->where('proposal_clarification_message_id', $message->id)
                ->whereIn('trigger_category', ['abusive_language', 'blacklisted_keyword'])
                ->exists()
        );
    }

    public function test_platform_clarification_prompt_is_not_flagged_or_redacted(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('conversation_message_flags')) {
            $this->markTestSkipped('Conversation monitoring tables are not migrated.');
        }

        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $quest = Quest::factory()->create(['client_id' => $client->id]);
        $offer = QuestOffer::factory()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'shortlisted',
        ]);

        $thread = ProposalClarificationThread::query()->create([
            'quest_id' => $quest->id,
            'quest_offer_id' => $offer->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'open',
        ]);

        $body = 'Before I award, can you confirm your proposal covers everything in my quest brief — including deliverables, revisions, and anything you called out in your pitch?';

        $message = ProposalClarificationMessage::query()->create([
            'thread_id' => $thread->id,
            'author_user_id' => $client->id,
            'role' => 'client',
            'prompt_key' => 'scope_alignment',
            'body' => $body,
        ]);

        app(ConversationMonitoringService::class)->processClarificationMessage($message->fresh());

        $message->refresh();

        $this->assertFalse($message->is_redacted);
        $this->assertDatabaseMissing('conversation_message_flags', [
            'proposal_clarification_message_id' => $message->id,
        ]);
        $this->assertDatabaseMissing('conversation_thread_reviews', [
            'proposal_clarification_thread_id' => $thread->id,
        ]);
    }

    public function test_super_admin_thread_detail_reveals_flagged_message_with_trigger_highlights(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('conversation_message_flags')) {
            $this->markTestSkipped('Conversation monitoring tables are not migrated.');
        }

        $superAdminRoleId = Role::query()->where('slug', 'super_admin')->value('id');
        if (! $superAdminRoleId) {
            $this->markTestSkipped('super_admin role is not seeded.');
        }

        $superAdmin = User::factory()->create(['role_id' => $superAdminRoleId]);
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
        ]);

        $message = ProposalClarificationMessage::query()->create([
            'thread_id' => $thread->id,
            'author_user_id' => $freelancer->id,
            'role' => 'freelancer',
            'prompt_key' => 'reply:1',
            'body' => 'Please pay me directly outside the platform using my account number 0123456789.',
        ]);

        app(ConversationMonitoringService::class)->processClarificationMessage($message);

        $review = ConversationThreadReview::query()
            ->where('proposal_clarification_thread_id', $thread->id)
            ->firstOrFail();

        $payload = app(ConversationMonitoringAdminService::class)
            ->threadDetail($review, revealFull: true, viewer: $superAdmin);

        $flagged = collect($payload['flagged_messages'])->first();
        $this->assertNotNull($flagged);
        $this->assertTrue($flagged['is_revealed']);
        $this->assertStringContainsString('0123456789', (string) $flagged['body']);
        $this->assertNotEmpty($flagged['trigger_highlights']);
        $this->assertTrue($payload['reveal_full']);
        $this->assertNotEmpty($payload['conversation_links']);
    }
}
