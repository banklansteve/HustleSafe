<?php

namespace Tests\Feature;

use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestStatus;
use App\Models\LocalGovernment;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\UserTrustMetric;
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

    public function test_client_can_open_quest_explore(): void
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);

        $client = User::factory()->create([
            'account_type' => 'sponsor',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);

        $this->actingAs($client)->get(route('quests.explore'))->assertOk();
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
            'headline' => 'Product designer focused on conversion',
            'bio' => str_repeat('I build trustworthy interfaces with measurable lift. ', 5),
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
            'slug' => 'design-landing-page-test',
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

        $payload = [
            'pitch' => 'I can ship this in one week with two revision rounds and clear handover docs for your team.',
            'scope_detail' => str_repeat('We will align on brand tokens, build responsive sections, and run accessibility checks before launch. ', 3),
            'warranty_terms' => '30-day bugfix window for implementation defects.',
            'planned_start_date' => now()->addDays(3)->toDateString(),
            'planned_finish_date' => now()->addDays(12)->toDateString(),
            'corrections_included' => false,
            'progress_report_frequency' => 'weekly',
            'materials' => [
                ['label' => 'Stock imagery pack', 'quantity' => '1', 'cost_ngn' => 25000],
            ],
            'pricing' => [
                'professional_fee_ngn' => 350000,
                'withholding_tax_percent' => 0,
                'travel_cost_ngn' => 0,
                'stamp_duty_ngn' => 0,
                'platform_fee_ngn' => 0,
                'discount_ngn' => 0,
                'grand_total_ngn' => 403125,
            ],
            'accepted_terms' => true,
        ];

        $response = $this->actingAs($freelancer)
            ->post(route('quests.proposals.store', $quest), $payload);

        $offer = QuestOffer::query()->where('quest_id', $quest->id)->where('freelancer_id', $freelancer->id)->first();
        $this->assertNotNull($offer);
        $response->assertRedirect(route('quests.proposals.show', [$quest, $offer]));

        $this->assertDatabaseHas('quest_offers', [
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
        ]);

        $this->actingAs($freelancer)
            ->get(route('quests.proposals.show', [$quest, $offer]))
            ->assertOk();
    }

    public function test_explore_hides_send_proposal_for_quests_with_existing_offer(): void
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
            'headline' => 'Product designer focused on conversion',
            'bio' => str_repeat('I build trustworthy interfaces with measurable lift. ', 5),
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
            'slug' => 'explore-proposal-sent-test',
            'title' => 'Explore proposal sent test',
            'description' => 'Need a responsive landing page.',
            'quest_category_id' => $leaf->id,
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'city' => 'Lagos',
            'status' => QuestStatus::Open,
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
            'budget_amount_minor' => 500_000,
        ]);

        $offer = QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'submitted',
            'pitch' => 'I can deliver this with clear milestones and weekly updates.',
            'scope_detail' => str_repeat('Scope covers responsive layout, QA, and handover documentation. ', 3),
            'quoted_amount_minor' => 450_000,
        ]);

        $response = $this->actingAs($freelancer)->get(route('quests.explore'));
        $response->assertOk();

        $quests = collect($response->viewData('page')['props']['quests'] ?? []);
        $row = $quests->firstWhere('id', $quest->id);
        $this->assertNotNull($row);
        $this->assertSame('submitted', $row['my_proposal']['status'] ?? null);
        $this->assertSame(
            route('quests.proposals.show', [$quest, $offer]),
            $row['my_proposal']['show_url'] ?? null,
        );
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
            'headline' => 'Conversion copywriter',
            'bio' => str_repeat('I write crisp landing copy with measurable lift. ', 5),
        ]);

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
            'slug' => 'copywriting-sprint-test',
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
            ->post(route('quests.proposals.store', $quest), [
                'pitch' => 'I can ship this in one week with two revision rounds and clear handover docs for your team.',
                'scope_detail' => str_repeat('We will align on brand voice, tighten headlines, and validate messaging with your analytics stack. ', 3),
                'warranty_terms' => '14-day tweak window for copy defects.',
                'planned_start_date' => now()->addDays(2)->toDateString(),
                'planned_finish_date' => now()->addDays(10)->toDateString(),
                'corrections_included' => false,
                'progress_report_frequency' => 'weekly',
                'materials' => [
                    ['label' => 'Research pack', 'quantity' => '1', 'cost_ngn' => 5000],
                ],
                'pricing' => [
                    'professional_fee_ngn' => 200000,
                    'withholding_tax_percent' => 0,
                    'travel_cost_ngn' => 0,
                    'stamp_duty_ngn' => 0,
                    'platform_fee_ngn' => 0,
                    'discount_ngn' => 0,
                    'grand_total_ngn' => 220375,
                ],
                'accepted_terms' => true,
            ])
            ->assertInvalid(['proposal']);
    }
}
