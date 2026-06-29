<?php

namespace App\Services\Disputes;

use App\Models\DisputeNegotiationOffer;
use App\Models\QuestDispute;
use App\Models\User;
use App\Notifications\QuestDisputeUpdatedNotification;

class DisputeNegotiationNotifier
{
    public function proposalSubmitted(QuestDispute $dispute, User $proposer, DisputeNegotiationOffer $offer): void
    {
        $dispute->loadMissing(['quest.client', 'quest.freelancer']);
        $other = $dispute->quest?->oppositeParty($proposer);
        if ($other === null) {
            return;
        }

        $warning = $offer->is_final_offer
            ? __('This is a final offer. Accept it or a staff mediator will decide.')
            : __('You have :hours hours to accept or counter.', ['hours' => config('disputes.negotiation.response_hours', 24)]);

        $other->notify(new QuestDisputeUpdatedNotification(
            $dispute,
            __('New resolution proposal'),
            __(':name proposed: :summary', [
                'name' => $proposer->first_name ?: $proposer->name,
                'summary' => $offer->summaryLabel(),
            ]),
            $warning,
            __('Review proposal'),
            'both',
        ));

        if ($dispute->assigned_staff_id) {
            $staff = User::query()->find($dispute->assigned_staff_id);
            if ($staff !== null) {
                app(DisputeStaffAlertService::class)->notifyNegotiationActivity(
                    $dispute,
                    $staff,
                    __(':party submitted negotiation attempt :n', [
                        'party' => $proposer->name,
                        'n' => $offer->attempt_number,
                    ]),
                );
            }
        }
    }

    public function mutualAgreementReached(QuestDispute $dispute, DisputeNegotiationOffer $offer): void
    {
        $dispute->loadMissing(['quest.client', 'quest.freelancer']);
        $headline = __('You agreed on a resolution');
        $body = __('Both parties accepted: :summary. Customer Support will review and approve before funds move.', [
            'summary' => $offer->summaryLabel(),
        ]);

        foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
            $party?->notify(new QuestDisputeUpdatedNotification(
                $dispute,
                $headline,
                $body,
                __('Appeal window opens only after Customer Support approves the settlement.'),
                __('View dispute'),
                'both',
            ));
        }

        if ($dispute->assigned_staff_id) {
            $staff = User::query()->find($dispute->assigned_staff_id);
            if ($staff !== null) {
                app(DisputeStaffAlertService::class)->notifyMutualAgreementPendingApproval($dispute, $staff, $offer);
            }
        }

        app(DisputeSuperAdminAlertService::class)->notifyMutualAgreementPendingApproval($dispute, $offer);
    }

    public function escalatedToMediation(QuestDispute $dispute, string $reason): void
    {
        $dispute->loadMissing(['quest.client', 'quest.freelancer']);
        $body = match ($reason) {
            'final_offer_rejected' => __('Negotiation ended because the final offer was rejected. A staff mediator will review all proposals.'),
            'response_deadline_missed' => __('A response deadline was missed. A staff mediator will review the case.'),
            default => __('Peer negotiation ended without agreement. A staff mediator will review all proposals.'),
        };

        foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
            $party?->notify(new QuestDisputeUpdatedNotification(
                $dispute,
                __('Negotiation ended — mediation starting'),
                $body,
                __('Before the final decision is enforced, you must acknowledge that mediation outcomes are binding on the platform.'),
                __('View dispute'),
                'both',
            ));
        }

        if ($dispute->assigned_staff_id) {
            $staff = User::query()->find($dispute->assigned_staff_id);
            if ($staff !== null) {
                app(DisputeStaffAlertService::class)->notifyEscalatedToMediation($dispute, $staff);
            }
        }
    }

    public function enforcementPending(QuestDispute $dispute, string $summary): void
    {
        $dispute->loadMissing(['quest.client', 'quest.freelancer']);
        $hours = (int) config('disputes.negotiation.enforcement_rejection_hours', 48);

        foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
            $party?->notify(new QuestDisputeUpdatedNotification(
                $dispute,
                __('Decision issued — review within :hours hours', ['hours' => $hours]),
                __('A decision was approved: :summary', ['summary' => $summary]),
                __('If you disagree, reject within the window and propose what you consider fair. After the window closes, the decision is enforced.'),
                __('View dispute'),
                'both',
            ));
        }
    }

    public function decisionFinalized(QuestDispute $dispute, string $summary): void
    {
        $dispute->loadMissing(['quest.client', 'quest.freelancer']);

        foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
            $party?->notify(new QuestDisputeUpdatedNotification(
                $dispute,
                __('Dispute finalized — binding'),
                __('The outcome is now permanent: :summary', ['summary' => $summary]),
                __('By using HustleSafe you agreed that platform dispute outcomes are binding. External mediation is not available for disputes resolved here.'),
                __('View dispute'),
                'both',
            ));
        }
    }
}
