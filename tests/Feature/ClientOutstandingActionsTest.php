<?php

namespace Tests\Feature;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\Role;
use App\Models\User;
use App\Services\ClientOutstandingActionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientOutstandingActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unseen_proposals_nudge_ignores_viewed_offers(): void
    {
        $client = User::factory()->create([
            'role_id' => Role::CLIENT_ID,
            'account_type' => 'sponsor',
        ]);

        $quest = Quest::query()->create([
            'client_id' => $client->id,
            'slug' => 'client-nudge-test',
            'title' => 'Client nudge test',
            'description' => 'Test quest',
            'status' => QuestStatus::Open,
            'budget_amount_minor' => 100_000,
        ]);

        QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => User::factory()->create(['role_id' => Role::FREELANCER_ID])->id,
            'status' => 'submitted',
            'pitch' => 'Ready to help.',
            'client_view_count' => 0,
        ]);

        $service = app(ClientOutstandingActionsService::class);
        $items = $service->items($client->fresh('role'));

        $this->assertCount(1, $items);
        $this->assertSame('unseen_proposals', $items[0]['key']);

        QuestOffer::query()->where('quest_id', $quest->id)->update(['client_view_count' => 1]);

        $this->assertSame([], $service->items($client->fresh('role')));
    }

    public function test_notification_nav_endpoint_returns_json_without_full_page_reload(): void
    {
        $client = User::factory()->create([
            'role_id' => Role::CLIENT_ID,
            'account_type' => 'sponsor',
        ]);

        $this->actingAs($client)
            ->getJson(route('api.notifications.nav'))
            ->assertOk()
            ->assertJsonStructure(['recentNotifications', 'unreadNotificationsCount']);
    }
}
