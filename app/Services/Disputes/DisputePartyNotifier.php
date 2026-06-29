<?php

namespace App\Services\Disputes;

use App\Models\QuestDispute;
use App\Models\User;
use App\Notifications\QuestDisputeUpdatedNotification;

class DisputePartyNotifier
{
    /**
     * Notify a dispute party in-app and/or by email.
     *
     * @param  'both'|'database'|'mail'  $delivery
     */
    public function notify(
        User $recipient,
        QuestDispute $dispute,
        string $headline,
        string $summary,
        ?string $detailMessage = null,
        ?string $ctaLabel = null,
        string $delivery = 'both',
    ): void {
        $recipient->notify(new QuestDisputeUpdatedNotification(
            dispute: $dispute,
            headline: $headline,
            body: $summary,
            detailMessage: $detailMessage,
            ctaLabel: $ctaLabel,
            delivery: $delivery,
        ));
    }

    /**
     * @param  list<User>  $recipients
     * @param  'both'|'database'|'mail'  $delivery
     */
    public function notifyMany(
        array $recipients,
        QuestDispute $dispute,
        string $headline,
        string $summary,
        ?string $detailMessage = null,
        ?string $ctaLabel = null,
        string $delivery = 'both',
    ): void {
        foreach ($recipients as $recipient) {
            if ($recipient instanceof User) {
                $this->notify($recipient, $dispute, $headline, $summary, $detailMessage, $ctaLabel, $delivery);
            }
        }
    }
}
