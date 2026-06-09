<?php

namespace Tests\Feature;

use App\Enums\QuestStatus;
use App\Models\LocalGovernment;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\QuestAudienceNotification;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuestFreelancerInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_sponsor_can_tag_freelancer_after_edit_window_closes(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $freelancer = User::factory()->create(['account_type' => 'hustler']);
        $quest = $this->openQuest($client, now()->addDays(5), now()->subDay());

        $this->actingAs($client)
            ->post(route('quests.invites.store', $quest), [
                'freelancer_ids' => [$freelancer->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($quest->fresh()->isInvitedFreelancer($freelancer));

        Notification::assertSentTo($freelancer, QuestAudienceNotification::class, function (QuestAudienceNotification $notification) use ($quest): bool {
            return $notification->kind === 'tag' && $notification->quest->is($quest);
        });
    }

    public function test_sponsor_cannot_tag_freelancer_who_already_proposed(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $freelancer = User::factory()->create(['account_type' => 'hustler']);
        $quest = $this->openQuest($client, now()->addDays(5));

        QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'submitted',
            'pitch' => 'Already submitted my proposal.',
            'quoted_amount_minor' => 400_000,
        ]);

        $this->actingAs($client)
            ->post(route('quests.invites.store', $quest), [
                'freelancer_ids' => [$freelancer->id],
            ])
            ->assertSessionHasErrors('freelancer_ids');

        Notification::assertNothingSent();
    }

    public function test_sponsor_cannot_tag_when_listing_expired(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $freelancer = User::factory()->create(['account_type' => 'hustler']);
        $quest = $this->openQuest($client, now()->subHour());

        $this->actingAs($client)
            ->post(route('quests.invites.store', $quest), [
                'freelancer_ids' => [$freelancer->id],
            ])
            ->assertForbidden();

        Notification::assertNothingSent();
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
            'slug' => 'invite-test-'.uniqid(),
            'title' => 'Invite test quest',
            'description' => 'Description for invite tests.',
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
