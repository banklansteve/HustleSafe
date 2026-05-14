<?php

namespace App\Jobs;

use App\Models\QuestOffer;
use App\Notifications\NewQuestProposalNotification;
use App\Notifications\ProposalUpdatedClientNotification;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Sends client notifications after the HTTP response is flushed (avoids browser timeouts on slow mail).
 */
final class DeliverQuestOfferClientNotification
{
    use Dispatchable;

    /**
     * @param  'new'|'updated'  $kind
     */
    public function __construct(
        public int $offerId,
        public string $kind,
    ) {}

    public function handle(): void
    {
        $offer = QuestOffer::query()->with(['quest.client', 'freelancer'])->find($this->offerId);
        if ($offer === null) {
            return;
        }

        $client = $offer->quest?->client;
        if ($client === null) {
            return;
        }

        if ($this->kind === 'updated') {
            $client->notify(new ProposalUpdatedClientNotification($offer));

            return;
        }

        $client->notify(new NewQuestProposalNotification($offer));
    }
}
