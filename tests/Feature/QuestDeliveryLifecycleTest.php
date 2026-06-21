<?php

namespace Tests\Feature;

use App\Enums\EscrowDeliveryStage;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Models\LocalGovernment;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Quest\QuestDeliveryLifecycleService;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestDeliveryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_client_cannot_acknowledge_before_freelancer_submits(): void
    {
        [$client, $freelancer, $quest] = $this->fundedQuest();

        $this->actingAs($client)
            ->post(route('quests.acknowledge-delivery', $quest), ['confirm' => true])
            ->assertSessionHasErrors('quest');
    }

    public function test_freelancer_can_submit_and_client_sees_awaiting_review(): void
    {
        [$client, $freelancer, $quest] = $this->fundedQuest();

        $this->actingAs($freelancer)
            ->post(route('quests.delivery-submissions.store', $quest), [
                'summary' => 'Delivered the complete website with admin panel and documentation for handover.',
                'confirm' => true,
            ])
            ->assertRedirect();

        $quest->refresh();
        $this->assertNotNull($quest->delivered_at);
        $this->assertNotNull($quest->delivery_review_deadline_at);

        $stage = app(QuestDeliveryLifecycleService::class)->stage($quest);
        $this->assertSame(EscrowDeliveryStage::AwaitingReview, $stage);
        $this->assertTrue(\App\Support\EscrowReleasePolicy::canAcknowledgeDelivery($quest->fresh(), $client));
    }

    public function test_client_can_request_revision(): void
    {
        [$client, $freelancer, $quest] = $this->fundedQuest();

        app(QuestDeliveryLifecycleService::class)->submitDeliverable($quest, $freelancer, [
            'summary' => 'First delivery with landing page and basic styling applied across breakpoints.',
        ]);

        $this->actingAs($client)
            ->post(route('quests.delivery.request-revision', $quest->fresh()), [
                'note' => 'Please fix mobile navigation and update the hero section copy as discussed.',
                'confirm' => true,
            ])
            ->assertRedirect();

        $quest->refresh();
        $this->assertNotNull($quest->delivery_revision_requested_at);
        $this->assertNull($quest->delivered_at);
        $this->assertSame(EscrowDeliveryStage::RevisionRequested, app(QuestDeliveryLifecycleService::class)->stage($quest));
    }

    /**
     * @return array{0: User, 1: User, 2: Quest}
     */
    private function fundedQuest(): array
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();

        $client = User::factory()->create([
            'account_type' => 'sponsor',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);

        $freelancer = User::factory()->create([
            'account_type' => 'hustler',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);

        $quest = Quest::query()->create([
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'slug' => 'delivery-lifecycle-test',
            'title' => 'Delivery lifecycle test',
            'description' => 'Test quest for delivery lifecycle.',
            'quest_category_id' => $leaf->id,
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'status' => QuestStatus::InProgress,
            'visibility' => QuestVisibility::Public,
            'escrow_status' => 'funded',
            'escrow_funded_at' => now()->subDay(),
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
            'budget_amount_minor' => 500_000,
            'delivery_deadline' => now()->addDays(7)->toDateString(),
        ]);

        $offer = QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'accepted',
            'pitch' => str_repeat('Professional delivery with clear milestones. ', 5),
            'scope_detail' => str_repeat('Scope covers design, build, and QA. ', 5),
            'quoted_amount_minor' => 450_000,
        ]);

        $quest->update(['accepted_quest_offer_id' => $offer->id]);

        return [$client, $freelancer, $quest->fresh()];
    }
}
