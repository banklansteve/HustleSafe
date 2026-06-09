<?php

namespace Tests\Feature;

use App\Enums\QuestStatus;
use App\Models\LocalGovernment;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Quest\QuestListingExpiryService;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuestListingExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_client_can_extend_listing_once_with_reason(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $freelancer = User::factory()->create(['account_type' => 'hustler']);
        $quest = $this->openQuest($client, now()->addDays(3));

        QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'submitted',
            'pitch' => 'I can deliver this on time.',
            'quoted_amount_minor' => 400_000,
        ]);

        $previous = $quest->listing_expires_at->copy();

        $this->actingAs($client)
            ->post(route('quests.extend-listing', $quest), [
                'additional_days' => 7,
                'reason' => 'Need more time to review all incoming proposals.',
            ])
            ->assertRedirect();

        $quest->refresh();
        $this->assertSame(1, (int) $quest->listing_extension_count);
        $this->assertTrue($quest->listing_expires_at->greaterThan($previous));
        $this->assertSame('Need more time to review all incoming proposals.', $quest->listing_extension_reason);

        $this->actingAs($client)
            ->post(route('quests.extend-listing', $quest), [
                'additional_days' => 3,
                'reason' => 'Trying to extend again should fail.',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_client_can_extend_after_edit_window_and_past_proposal_deadline(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $quest = $this->openQuest($client, now()->subHours(2), now()->subDay());

        $this->actingAs($client)
            ->post(route('quests.extend-listing', $quest), [
                'additional_days' => 5,
                'reason' => 'Still reviewing proposals and need a few more days.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $quest->refresh();
        $this->assertSame(1, (int) $quest->listing_extension_count);
        $this->assertTrue($quest->listing_expires_at->greaterThan(now()));
    }

    public function test_expire_command_closes_unawarded_quests(): void
    {
        $client = $this->makeClient();
        $quest = $this->openQuest($client, now()->subHour());

        $this->artisan('quests:expire-listings')->assertSuccessful();

        $quest->refresh();
        $this->assertSame(QuestStatus::ClosedUnawarded, $quest->status);
        $this->assertSame('unawarded', $quest->closure_type);
    }

    public function test_client_can_repost_closed_unawarded_quest(): void
    {
        $client = $this->makeClient();
        $quest = $this->openQuest($client, now()->subDay());
        $quest->update([
            'status' => QuestStatus::ClosedUnawarded,
            'closure_type' => 'unawarded',
        ]);

        $response = $this->actingAs($client)->post(route('quests.repost', $quest));

        $fresh = Quest::query()->where('reposted_from_quest_id', $quest->id)->first();
        $this->assertNotNull($fresh);
        $this->assertSame(QuestStatus::Open, $fresh->status);
        $this->assertSame($quest->title, $fresh->title);
        $response->assertRedirect(route('quests.show', $fresh));
    }

    public function test_awarded_quest_is_not_expired_by_listing_command(): void
    {
        $client = $this->makeClient();
        $quest = $this->openQuest($client, now()->subHour());
        $quest->update([
            'freelancer_id' => User::factory()->create(['account_type' => 'hustler'])->id,
            'status' => QuestStatus::Assigned,
        ]);

        $this->artisan('quests:expire-listings')->assertSuccessful();

        $quest->refresh();
        $this->assertSame(QuestStatus::Assigned, $quest->status);
    }

    public function test_resolve_days_for_create_clamps_to_platform_bounds(): void
    {
        $service = app(QuestListingExpiryService::class);
        $bounds = $service->bounds();

        $this->assertSame($bounds['default'], $service->resolveDaysForCreate(null));
        $this->assertSame($bounds['max'], $service->resolveDaysForCreate(999));
        $this->assertSame($bounds['min'], $service->resolveDaysForCreate(0));
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

    private function openQuest(User $client, \DateTimeInterface $listingExpiresAt, ?\DateTimeInterface $clientEditUntil = null): Quest
    {
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();
        $this->assertNotNull($leaf);

        return Quest::query()->create([
            'client_id' => $client->id,
            'slug' => 'test-quest-'.uniqid(),
            'title' => 'Listing expiry test quest',
            'description' => 'Description for listing expiry tests.',
            'quest_category_id' => $leaf->id,
            'state_id' => $client->state_id,
            'local_government_id' => $client->local_government_id,
            'city' => 'Lagos',
            'status' => QuestStatus::Open,
            'budget_amount_minor' => 500_000,
            'auto_listing_expiry_days' => 14,
            'listing_expires_at' => $listingExpiresAt,
            'client_edit_until' => $clientEditUntil,
            'listing_extension_count' => 0,
            'estimated_completion_days' => 14,
            'due_at' => now()->addDays(14),
            'terms_accepted_at' => now(),
        ]);
    }
}
