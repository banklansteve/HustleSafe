<?php

namespace Tests\Feature;

use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Models\LocalGovernment;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\State;
use App\Models\User;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_freelancer_can_open_browse_page(): void
    {
        $freelancer = $this->makeFreelancer();
        $client = $this->makeClient();
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();
        $lga = LocalGovernment::query()->with('state')->first();

        $this->createOpenQuest($client, $leaf, $lga, 'browse-smoke-test', 'Browse smoke test');

        $response = $this->actingAs($freelancer)->get(route('quests.browse'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Quests/Browse')
            ->has('quests.data', 1)
            ->has('filter_options.locations')
            ->has('filter_options.category_tree')
        );
    }

    public function test_client_cannot_access_browse_page(): void
    {
        $client = $this->makeClient();

        $this->actingAs($client)->get(route('quests.browse'))->assertForbidden();
    }

    public function test_browse_filters_by_state(): void
    {
        $freelancer = $this->makeFreelancer();
        $client = $this->makeClient();
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();

        $states = State::query()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $states);

        $lagosLga = LocalGovernment::query()->where('state_id', $states[0]->id)->first();
        $otherLga = LocalGovernment::query()->where('state_id', $states[1]->id)->first();
        $this->assertNotNull($lagosLga);
        $this->assertNotNull($otherLga);

        $this->createOpenQuest($client, $leaf, $lagosLga, 'browse-lagos-quest', 'Lagos quest');
        $this->createOpenQuest($client, $leaf, $otherLga, 'browse-other-state-quest', 'Other state quest');

        $response = $this->actingAs($freelancer)->get(route('quests.browse', [
            'state_id' => $states[0]->id,
        ]));

        $response->assertOk();
        $titles = collect($response->viewData('page')['props']['quests']['data'] ?? [])->pluck('title')->all();
        $this->assertSame(['Lagos quest'], $titles);
    }

    public function test_browse_filters_by_parent_category(): void
    {
        $freelancer = $this->makeFreelancer();
        $client = $this->makeClient();
        $lga = LocalGovernment::query()->with('state')->first();

        $categories = QuestCategory::query()
            ->whereNotNull('parent_id')
            ->with('parent')
            ->take(2)
            ->get();

        $this->assertCount(2, $categories);

        $this->createOpenQuest($client, $categories[0], $lga, 'browse-cat-a', 'Category A quest');
        $this->createOpenQuest($client, $categories[1], $lga, 'browse-cat-b', 'Category B quest');

        $response = $this->actingAs($freelancer)->get(route('quests.browse', [
            'parent_category_id' => $categories[0]->parent_id,
        ]));

        $response->assertOk();
        $titles = collect($response->viewData('page')['props']['quests']['data'] ?? [])->pluck('title')->all();
        $this->assertContains('Category A quest', $titles);
        $this->assertNotContains('Category B quest', $titles);
    }

    public function test_browse_applies_smart_defaults_on_first_visit(): void
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);

        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();
        $this->assertNotNull($leaf);

        $otherState = State::query()->where('id', '<>', $lga->state_id)->first();
        $this->assertNotNull($otherState);
        $otherLga = LocalGovernment::query()->where('state_id', $otherState->id)->first();
        $this->assertNotNull($otherLga);

        $freelancer = User::factory()->create([
            'account_type' => 'hustler',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);
        $freelancer->questCategoryPreferences()->sync([$leaf->id]);

        $client = $this->makeClient();

        $this->createOpenQuest($client, $leaf, $lga, 'smart-default-local', 'Smart default local');
        $this->createOpenQuest($client, $leaf, $otherLga, 'smart-default-other-state', 'Smart default other state');

        $response = $this->actingAs($freelancer)->get(route('quests.browse'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertTrue($props['filters']['using_smart_defaults'] ?? false);
        $titles = collect($props['quests']['data'] ?? [])->pluck('title')->all();
        $this->assertSame(['Smart default local'], $titles);
    }

    public function test_browse_cleared_shows_all_open_quests(): void
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();
        $otherState = State::query()->where('id', '<>', $lga->state_id)->first();
        $otherLga = LocalGovernment::query()->where('state_id', $otherState->id)->first();

        $freelancer = User::factory()->create([
            'account_type' => 'hustler',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);
        $freelancer->questCategoryPreferences()->sync([$leaf->id]);

        $client = $this->makeClient();
        $this->createOpenQuest($client, $leaf, $lga, 'cleared-local', 'Cleared local');
        $this->createOpenQuest($client, $leaf, $otherLga, 'cleared-other', 'Cleared other');

        $response = $this->actingAs($freelancer)->get(route('quests.browse', ['cleared' => 1]));

        $response->assertOk();
        $titles = collect($response->viewData('page')['props']['quests']['data'] ?? [])->pluck('title')->all();
        $this->assertContains('Cleared local', $titles);
        $this->assertContains('Cleared other', $titles);
    }

    public function test_browse_paginates_results(): void
    {
        $freelancer = $this->makeFreelancer();
        $client = $this->makeClient();
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();
        $lga = LocalGovernment::query()->with('state')->first();

        for ($i = 1; $i <= 14; $i++) {
            $this->createOpenQuest($client, $leaf, $lga, "browse-page-quest-{$i}", "Browse page quest {$i}");
        }

        $response = $this->actingAs($freelancer)->get(route('quests.browse', ['cleared' => 1]));

        $response->assertOk();
        $meta = $response->viewData('page')['props']['quests']['meta'] ?? [];
        $this->assertSame(14, $meta['total'] ?? null);
        $this->assertSame(2, $meta['last_page'] ?? null);
        $this->assertCount(12, $response->viewData('page')['props']['quests']['data'] ?? []);
    }

    private function makeFreelancer(): User
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);

        return User::factory()->create([
            'account_type' => 'hustler',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);
    }

    private function makeClient(): User
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);

        return User::factory()->create([
            'account_type' => 'sponsor',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);
    }

    private function createOpenQuest(User $client, QuestCategory $leaf, LocalGovernment $lga, string $slug, string $title): Quest
    {
        return Quest::query()->create([
            'client_id' => $client->id,
            'slug' => $slug,
            'title' => $title,
            'description' => 'Open quest for browse testing.',
            'quest_category_id' => $leaf->id,
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'city' => 'Lagos',
            'status' => QuestStatus::Open,
            'visibility' => QuestVisibility::Public,
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
            'budget_amount_minor' => 500_000,
        ]);
    }
}
