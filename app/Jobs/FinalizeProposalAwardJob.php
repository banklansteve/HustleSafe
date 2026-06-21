<?php

namespace App\Jobs;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Notifications\ProposalAcceptedFreelancerNotification;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\Contracts\ContractGenerationService;
use App\Services\Quest\QuestJourneySurveyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Post-award side effects (contract, notifications, surveys) — must run on a real queue
 * worker so confirm-award returns immediately.
 */
final class FinalizeProposalAwardJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  list<int>  $declinedOfferIds
     */
    public function __construct(
        public int $questId,
        public int $offerId,
        public array $declinedOfferIds,
    ) {}

    public function handle(): void
    {
        $quest = Quest::query()->with(['client', 'questCategory', 'stateModel'])->find($this->questId);
        $offer = QuestOffer::query()->with(['freelancer', 'quest'])->find($this->offerId);

        if ($quest === null || $offer === null) {
            return;
        }

        try {
            $offer->freelancer?->notify(new ProposalAcceptedFreelancerNotification($offer));

            app(ContractGenerationService::class)->generateFromAward($quest, $offer);

            $surveyService = app(QuestJourneySurveyService::class);
            if ($this->declinedOfferIds !== []) {
                QuestOffer::query()
                    ->whereIn('id', $this->declinedOfferIds)
                    ->with('freelancer')
                    ->each(fn (QuestOffer $declined) => $surveyService->onProposalRejected($quest, $declined, 'lost_award'));
            }

            app(AdminActivityFeedService::class)->record(
                'financial',
                'contract.started',
                'Contract started',
                "{$quest->client?->name} accepted {$offer->freelancer?->name} for {$quest->title}",
                app(AdminActivityFeedService::class)->entities([
                    ['type' => 'user', 'id' => $quest->client_id, 'label' => $quest->client?->name],
                    ['type' => 'user', 'id' => $offer->freelancer_id, 'label' => $offer->freelancer?->name],
                    ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
                ]),
                ['category' => $quest->questCategory?->name, 'state' => $quest->stateModel?->name],
                (int) ($offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0),
                $offer->freelancer,
                QuestOffer::class,
                $offer->id,
                $quest->state_id,
                $quest->local_government_id,
                $quest->quest_category_id,
                'info',
            );
        } catch (\Throwable $e) {
            Log::error('FinalizeProposalAwardJob failed', [
                'quest_id' => $this->questId,
                'offer_id' => $this->offerId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
