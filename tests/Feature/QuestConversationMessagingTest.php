<?php

namespace Tests\Feature;

use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestStatus;
use App\Models\LocalGovernment;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestConversationThread;
use App\Models\User;
use App\Models\UserTrustMetric;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestConversationMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_freelancer_without_role_id_but_hustler_account_can_open_messages_when_thread_exists(): void
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
            'role_id' => null,
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'address_line' => '15 Admiralty Way, Lekki Phase 1',
            'city' => 'Lagos',
            'headline' => 'Product designer',
            'bio' => str_repeat('I build trustworthy interfaces. ', 5),
        ]);
        $freelancer->questCategoryPreferences()->sync([$leaf->id]);
        UserTrustMetric::query()->updateOrCreate(
            ['user_id' => $freelancer->id],
            [
                'freelancer_trust_score' => 50,
                'client_trust_score' => 50,
                'profile_completion_percent' => 72,
                'avg_rating_as_freelancer' => 0,
                'avg_rating_as_client' => 0,
                'ratings_count_as_freelancer' => 0,
                'ratings_count_as_client' => 0,
            ]
        );

        $quest = Quest::query()->create([
            'client_id' => $client->id,
            'slug' => 'thread-access-test-quest',
            'title' => 'Thread access test',
            'description' => 'Description for messaging test.',
            'quest_category_id' => $leaf->id,
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'city' => 'Lagos',
            'status' => QuestStatus::Open,
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
            'budget_amount_minor' => 500_000,
        ]);

        QuestConversationThread::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'client_id' => $client->id,
            'messages_count' => 0,
        ]);

        $this->actingAs($freelancer)
            ->get(route('quests.messages.show', [$quest->getRouteKey()]))
            ->assertOk();
    }
}
