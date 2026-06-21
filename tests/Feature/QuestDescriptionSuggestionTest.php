<?php

namespace Tests\Feature;

use App\Models\QuestCategory;
use App\Models\User;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestDescriptionSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_description_suggestions_work_without_anthropic_api_key(): void
    {
        config(['services.anthropic.api_key' => null]);

        $client = User::factory()->create(['account_type' => 'sponsor']);
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();
        $this->assertNotNull($leaf);

        $response = $this->actingAs($client)->postJson(route('quests.description-suggestions'), [
            'title' => 'Deep clean my 3-bedroom flat',
            'quest_category_id' => $leaf->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('powered_by_claude', false)
            ->assertJsonCount(3, 'suggestions')
            ->assertJsonStructure([
                'suggestions' => [
                    ['label', 'text'],
                ],
            ]);
    }

    public function test_guest_cannot_request_description_suggestions(): void
    {
        $this->postJson(route('quests.description-suggestions'), [
            'title' => 'Test quest',
        ])->assertUnauthorized();
    }
}
