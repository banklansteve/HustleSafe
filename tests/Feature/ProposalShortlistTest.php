<?php

namespace Tests\Feature;

use App\Enums\QuestStatus;
use App\Models\LocalGovernment;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\ProposalShortlistedFreelancerNotification;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProposalShortlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_client_can_toggle_shortlist_without_confirmation(): void
    {
        Notification::fake();

        [$client, $quest, $offer] = $this->seedOpenProposal();

        $this->actingAs($client)
            ->post(route('quests.proposals.toggle-shortlist', [$quest, $offer]))
            ->assertRedirect();

        $offer->refresh();
        $this->assertSame('shortlisted', $offer->status);
        Notification::assertSentTo($offer->freelancer, ProposalShortlistedFreelancerNotification::class);

        $this->actingAs($client)
            ->post(route('quests.proposals.toggle-shortlist', [$quest, $offer]))
            ->assertRedirect();

        $offer->refresh();
        $this->assertSame('submitted', $offer->status);
    }

    public function test_shortlist_cap_is_enforced(): void
    {
        Notification::fake();

        [$client, $quest] = $this->seedOpenProposal(returnOffer: false);

        for ($i = 0; $i < 5; $i++) {
            $freelancer = User::factory()->create(['account_type' => 'hustler']);
            $offer = QuestOffer::query()->create([
                'quest_id' => $quest->id,
                'freelancer_id' => $freelancer->id,
                'status' => 'submitted',
                'pitch' => 'Strong pitch with enough detail to pass completeness checks easily.',
                'scope_detail' => str_repeat('Detailed scope. ', 20),
                'quoted_amount_minor' => 400_000,
            ]);

            $this->actingAs($client)
                ->post(route('quests.proposals.toggle-shortlist', [$quest, $offer]))
                ->assertRedirect();
        }

        $extraFreelancer = User::factory()->create(['account_type' => 'hustler']);
        $extra = QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $extraFreelancer->id,
            'status' => 'submitted',
            'pitch' => 'Another pitch.',
            'scope_detail' => str_repeat('Scope. ', 10),
            'quoted_amount_minor' => 350_000,
        ]);

        $this->actingAs($client)
            ->post(route('quests.proposals.toggle-shortlist', [$quest, $extra]))
            ->assertSessionHasErrors('shortlist');
    }

    /**
     * @return array{0: User, 1: Quest, 2?: QuestOffer}
     */
    private function seedOpenProposal(bool $returnOffer = true): array
    {
        $lga = LocalGovernment::query()->with('state')->first();
        $leaf = QuestCategory::query()->whereNotNull('parent_id')->first();

        $client = User::factory()->create([
            'account_type' => 'sponsor',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
        ]);

        $quest = Quest::query()->create([
            'client_id' => $client->id,
            'slug' => 'shortlist-test-'.uniqid(),
            'title' => 'Shortlist test quest',
            'description' => 'Test quest for shortlist behaviour.',
            'quest_category_id' => $leaf->id,
            'state_id' => $client->state_id,
            'local_government_id' => $client->local_government_id,
            'city' => 'Lagos',
            'status' => QuestStatus::Open,
            'budget_amount_minor' => 500_000,
            'offers_count' => 1,
            'estimated_completion_days' => 14,
            'terms_accepted_at' => now(),
        ]);

        if (! $returnOffer) {
            return [$client, $quest];
        }

        $freelancer = User::factory()->create(['account_type' => 'hustler']);
        $offer = QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'submitted',
            'pitch' => 'I can deliver this on time with clear milestones.',
            'scope_detail' => str_repeat('Scope detail. ', 15),
            'quoted_amount_minor' => 400_000,
        ]);

        return [$client, $quest, $offer];
    }
}
