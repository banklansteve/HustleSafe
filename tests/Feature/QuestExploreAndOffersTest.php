<?php

namespace Tests\Feature;

use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestStatus;
use App\Models\LocalGovernment;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\User;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestExploreAndOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_non_freelancer_is_redirected_from_quest_explore(): void
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);

        $client = User::factory()->create([
            'account_type' => 'sponsor',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);

        $this->actingAs($client)->get(route('quests.explore'))->assertRedirect(route('dashboard'));
    }

    public function test_freelancer_can_submit_offer_when_ready(): void
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();
        $this->assertNotNull($leaf);

        $client = User::factory()->create([
            'account_type' => 'sponsor',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);

        $freelancer = User::factory()->create([
            'account_type' => 'hustler',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'address_line' => '15 Admiralty Way, Lekki Phase 1',
            'city' => 'Lagos',
        ]);
        $freelancer->questCategoryPreferences()->sync([$leaf->id]);

        $quest = Quest::query()->create([
            'client_id' => $client->id,
            'title' => 'Design landing page',
            'description' => 'Need a responsive landing page.',
            'quest_category_id' => $leaf->id,
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'city' => 'Lagos',
            'status' => QuestStatus::Open,
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
            'budget_amount_minor' => 500_000,
        ]);

        $this->actingAs($freelancer)
            ->post(route('quests.offers.store', $quest), [
                'pitch' => 'I can ship this in one week with two revision rounds.',
                'quoted_amount_minor' => 400_000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quest_offers', [
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
        ]);
    }

    public function test_freelancer_without_categories_cannot_submit_offer(): void
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();
        $this->assertNotNull($leaf);

        $client = User::factory()->create([
            'account_type' => 'sponsor',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);

        $freelancer = User::factory()->create([
            'account_type' => 'hustler',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'address_line' => '15 Admiralty Way, Lekki Phase 1',
            'city' => 'Lagos',
        ]);

        $quest = Quest::query()->create([
            'client_id' => $client->id,
            'title' => 'Copywriting sprint',
            'description' => 'Short copy refresh.',
            'quest_category_id' => $leaf->id,
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'city' => 'Lagos',
            'status' => QuestStatus::Open,
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
            'budget_amount_minor' => 300_000,
        ]);

        $this->actingAs($freelancer)
            ->from(route('quests.explore'))
            ->post(route('quests.offers.store', $quest), [
                'pitch' => 'I write conversion-first copy.',
            ])
            ->assertInvalid(['offer']);
    }
}
