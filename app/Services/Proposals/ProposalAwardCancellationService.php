<?php

namespace App\Services\Proposals;

use App\Enums\ContractStatus;
use App\Enums\QuestStatus;
use App\Models\ModerationCase;
use App\Models\ModerationCaseTrigger;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\ProposalAwardCancelledFreelancerNotification;
use App\Services\Admin\UserActivityPatrol\UserActivityPatrolAnomalyService;
use App\Services\Admin\AdminActivityFeedService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProposalAwardCancellationService
{
    public function __construct(
        private readonly ProposalTrustBehaviourService $trustBehaviour,
        private readonly UserActivityPatrolAnomalyService $activityPatrol,
        private readonly AdminActivityFeedService $activityFeed,
    ) {}

    public function cancel(Quest $quest, QuestOffer $offer, User $client, ?string $reason = null): void
    {
        if ((int) $quest->client_id !== (int) $client->id) {
            abort(403);
        }

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->assertCancellable($quest, $offer);

        $snapshot = is_array($offer->award_terms_snapshot) ? $offer->award_terms_snapshot : [];
        $wasMutuallyConfirmed = $offer->isAwardMutuallyConfirmed();
        $declinedOfferIds = collect($snapshot['declined_offer_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $restoreStatus = (string) ($snapshot['prior_status'] ?? 'submitted');
        if (! in_array($restoreStatus, ['submitted', 'shortlisted'], true)) {
            $restoreStatus = 'submitted';
        }

        DB::transaction(function () use ($quest, $offer, $restoreStatus, $wasMutuallyConfirmed, $declinedOfferIds): void {
            if ($wasMutuallyConfirmed) {
                if ($declinedOfferIds !== []) {
                    QuestOffer::query()
                        ->where('quest_id', $quest->id)
                        ->whereIn('id', $declinedOfferIds)
                        ->update([
                            'status' => 'submitted',
                            'declined_at' => null,
                        ]);
                }

                QuestContract::query()
                    ->where('quest_id', $quest->id)
                    ->where('quest_offer_id', $offer->id)
                    ->where('status', ContractStatus::PendingEscrow)
                    ->update([
                        'status' => ContractStatus::Cancelled,
                        'cancelled_at' => now(),
                    ]);

                $quest->update([
                    'status' => QuestStatus::Open,
                    'freelancer_id' => null,
                    'accepted_quest_offer_id' => null,
                    'pending_award_offer_id' => null,
                    'escrow_status' => null,
                ]);
            } else {
                $quest->update(['pending_award_offer_id' => null]);
            }

            $offer->update([
                'status' => $restoreStatus,
                'accepted_at' => null,
                'award_client_confirmed_at' => null,
                'award_freelancer_confirmed_at' => null,
                'award_terms_snapshot' => null,
            ]);
        });

        $offer->refresh()->loadMissing(['freelancer', 'quest']);
        $quest->refresh();

        $offer->freelancer?->notify(new ProposalAwardCancelledFreelancerNotification($offer, $reason));

        $this->trustBehaviour->recordClientAwardCancellation($quest, $offer, $client, $reason, $wasMutuallyConfirmed);
        $this->activityPatrol->flagClientAwardCancellation($client, $offer, $quest, $reason);
        $this->openModerationCase($client, $quest, $offer, $reason);

        $this->activityFeed->record(
            'trust',
            'award.cancelled_before_escrow',
            'Award cancelled before escrow',
            "{$client->name} withdrew the award on “{$quest->title}” before escrow was funded",
            $this->activityFeed->entities([
                ['type' => 'user', 'id' => $client->id, 'label' => $client->name],
                ['type' => 'user', 'id' => $offer->freelancer_id, 'label' => $offer->freelancer?->name],
                ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
            ]),
            [
                'reason' => $reason,
                'offer_id' => $offer->id,
            ],
            (int) ($offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0),
            $client,
            QuestOffer::class,
            $offer->id,
            $quest->state_id,
            $quest->local_government_id,
            $quest->quest_category_id,
            'warning',
        );
    }

    public function canCancel(Quest $quest, QuestOffer $offer): bool
    {
        try {
            $this->assertCancellable($quest, $offer);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function assertCancellable(Quest $quest, QuestOffer $offer): void
    {
        if ($quest->escrow_status === 'funded' || $quest->escrow_status === 'partially_released' || $quest->escrow_status === 'released') {
            throw ValidationException::withMessages([
                'proposal' => [__('This award can no longer be cancelled because escrow has already been funded.')],
            ]);
        }

        $pendingAward = (int) ($quest->pending_award_offer_id ?? 0) === (int) $offer->id
            && $offer->status === 'pending_award';

        $awaitingFunding = (int) ($quest->accepted_quest_offer_id ?? 0) === (int) $offer->id
            && $offer->status === 'accepted'
            && $quest->escrow_status === 'awaiting_funding'
            && in_array($quest->status?->value ?? (string) $quest->status, [
                QuestStatus::Assigned->value,
                QuestStatus::Open->value,
            ], true);

        if (! $pendingAward && ! $awaitingFunding) {
            throw ValidationException::withMessages([
                'proposal' => [__('This award cannot be cancelled in its current state.')],
            ]);
        }
    }

    private function openModerationCase(User $client, Quest $quest, QuestOffer $offer, ?string $reason): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('moderation_cases')) {
            return;
        }

        $excerpt = trim((string) ($reason ?: __('Client cancelled the award before escrow was funded.')));

        $case = ModerationCase::query()->create([
            'moderatable_type' => QuestOffer::class,
            'moderatable_id' => $offer->id,
            'subject_user_id' => $client->id,
            'content_type' => 'quest_offer',
            'queue' => 'quest',
            'status' => 'open',
            'severity' => 'warning',
            'visibility_state' => 'live_under_review',
            'source' => 'automated',
            'confidence' => 90,
            'title' => __('Award cancelled before escrow'),
            'excerpt' => \App\Support\PlainText::from($excerpt, 240),
            'snapshot' => [
                'quest_id' => $quest->id,
                'quest_title' => $quest->title,
                'offer_id' => $offer->id,
                'freelancer_id' => $offer->freelancer_id,
                'reason' => $reason,
            ],
            'entered_queue_at' => now(),
        ]);

        ModerationCaseTrigger::query()->create([
            'moderation_case_id' => $case->id,
            'rule_key' => 'client_award_cancelled_before_escrow',
            'rule_type' => 'behaviour',
            'severity' => 'warning',
            'confidence' => 90,
            'message' => __('Client withdrew an award before escrow funding.'),
        ]);
    }
}
