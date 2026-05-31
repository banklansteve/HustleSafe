<?php

namespace App\Services\Disputes;

use App\Enums\DisputeMessageKind;
use App\Enums\DisputeSettlementOfferStatus;
use App\Enums\QuestDisputePhase;
use App\Enums\QuestDisputeReason;
use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Models\DisputeEvent;
use App\Models\DisputeMessage;
use App\Models\DisputeSettlementOffer;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\User;
use App\Notifications\QuestDisputeUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestDisputeWorkflowService
{
    public function assertCanOpen(User $user, Quest $quest): void
    {
        if (! $quest->isParty($user)) {
            throw ValidationException::withMessages(['quest' => [__('Only the client and assigned freelancer can open a dispute on this quest.')]]);
        }

        $offer = $quest->acceptedOffer;
        if ($offer === null || $offer->status !== 'accepted') {
            throw ValidationException::withMessages(['quest' => [__('Disputes unlock once a proposal is accepted.')]]);
        }

        if ($quest->freelancer_id === null) {
            throw ValidationException::withMessages(['quest' => [__('Assign a freelancer before opening a dispute.')]]);
        }

        if (! in_array($quest->status, [
            QuestStatus::Assigned,
            QuestStatus::InProgress,
            QuestStatus::PendingReview,
            QuestStatus::InDispute,
            QuestStatus::Completed,
        ], true)) {
            throw ValidationException::withMessages(['quest' => [__('Disputes are only available during or shortly after an active engagement.')]]);
        }

        if ($quest->escrow_status !== 'funded' && ! in_array($quest->status, [QuestStatus::Completed, QuestStatus::PendingReview], true)) {
            throw ValidationException::withMessages(['quest' => [__('Fund escrow first — disputes unlock once funds are in place or the job is awaiting your approval.')]]);
        }

        $min = (int) config('disputes.minimum_disputed_amount_minor', 500_000);
        $amount = (int) ($offer->quoted_amount_minor ?? 0);
        if ($amount < $min) {
            throw ValidationException::withMessages(['quest' => [__('This contract is below the minimum value for a formal dispute file — contact support for help.')]]);
        }

        $maxDays = (int) config('disputes.max_days_after_completion_to_open', 14);
        if ($quest->completed_at !== null && $quest->completed_at->lt(now()->subDays($maxDays))) {
            throw ValidationException::withMessages(['quest' => [__('The dispute window after completion has closed.')]]);
        }

        if ($this->hasBlockingOpenDispute($quest)) {
            throw ValidationException::withMessages(['quest' => [__('This quest already has an open dispute.')]]);
        }
    }

    public function hasBlockingOpenDispute(Quest $quest): bool
    {
        return QuestDispute::query()
            ->where('quest_id', $quest->id)
            ->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $structuredIntake
     */
    public function open(
        User $opener,
        Quest $quest,
        QuestOffer $offer,
        QuestDisputeReason $reason,
        array $structuredIntake,
        string $openingSummary,
    ): QuestDispute {
        $this->assertCanOpen($opener, $quest);

        if ((int) $offer->id !== (int) $quest->accepted_quest_offer_id) {
            throw ValidationException::withMessages(['offer' => [__('Use the accepted proposal for this quest.')]]);
        }

        $party = $this->partyFor($opener, $quest);
        if ($party === null || ! $reason->allowedForParty($party)) {
            throw ValidationException::withMessages(['reason' => [__('You cannot raise the dispute under this reason code.')]]);
        }

        if ($reason === QuestDisputeReason::SilenceComms) {
            $days = (int) ($structuredIntake['silence_days_observed'] ?? 0);
            $minDays = (int) config('disputes.silence_comms_min_days', 5);
            if ($days < $minDays) {
                throw ValidationException::withMessages(['silence_days_observed' => [__('Document at least :n days without meaningful replies.', ['n' => $minDays])]]);
            }
        }

        $other = $quest->oppositeParty($opener);
        if ($other === null) {
            throw ValidationException::withMessages(['quest' => [__('The other party is not assigned yet.')]]);
        }

        $hours = (int) config('disputes.self_resolution_response_hours', 48);

        return DB::transaction(function () use ($opener, $quest, $offer, $reason, $structuredIntake, $openingSummary, $other, $hours): QuestDispute {
            $dispute = QuestDispute::query()->create([
                'quest_id' => $quest->id,
                'quest_offer_id' => $offer->id,
                'opened_by_user_id' => $opener->id,
                'reason' => $reason->value,
                'structured_intake' => $structuredIntake,
                'phase' => QuestDisputePhase::SelfResolution,
                'status' => QuestDisputeStatus::SelfResolving,
                'tier' => 0,
                'appeals_used' => 0,
                'disputed_amount_minor' => (int) ($offer->quoted_amount_minor ?? 0),
                'response_required_by' => now()->addHours($hours),
                'ruling_required_by' => null,
                'awaiting_user_id' => $other->id,
                'opening_summary' => $openingSummary,
            ]);

            $this->log($dispute, $opener, 'dispute.opened', [
                'reason' => $reason->value,
            ]);

            $this->systemMessage($dispute, __(
                'Self-resolution window is active. :name has :hours hours to acknowledge and respond with evidence or a settlement position.',
                ['name' => $other->first_name ?: $other->name, 'hours' => $hours]
            ));

            $dispute->messages()->create([
                'user_id' => $opener->id,
                'kind' => DisputeMessageKind::Narrative,
                'body' => $openingSummary,
                'structured_payload' => $structuredIntake,
            ]);

            if ($quest->status !== QuestStatus::InDispute) {
                $quest->update([
                    'status' => QuestStatus::InDispute,
                    'dispute_opened' => true,
                ]);
            }

            $contract = \App\Models\QuestContract::query()
                ->where('quest_id', $quest->id)
                ->where('quest_offer_id', $offer->id)
                ->first();
            if ($contract !== null) {
                app(\App\Services\Contracts\ContractLifecycleService::class)->markDisputed($contract, $dispute, $opener);
            }

            $other->notify(new QuestDisputeUpdatedNotification($dispute, __('New dispute opened'), __('A structured dispute was opened on “:title”. Review the case and respond before the countdown expires.', ['title' => $quest->title])));

            return $dispute->fresh();
        });
    }

    public function addPartyMessage(User $author, QuestDispute $dispute, DisputeMessageKind $kind, ?string $body, ?string $structuredKey, ?array $structuredPayload): DisputeMessage
    {
        $this->assertParty($author, $dispute);

        if (! $dispute->isOpen()) {
            throw ValidationException::withMessages(['dispute' => [__('This dispute is already closed.')]]);
        }

        return DB::transaction(function () use ($author, $dispute, $kind, $body, $structuredKey, $structuredPayload): DisputeMessage {
            $msg = $dispute->messages()->create([
                'user_id' => $author->id,
                'kind' => $kind,
                'body' => $body,
                'structured_key' => $structuredKey,
                'structured_payload' => $structuredPayload,
            ]);

            $this->log($dispute, $author, 'dispute.message_added', ['kind' => $kind->value]);

            $quest = $dispute->quest;
            $other = $quest?->oppositeParty($author);
            if ($other !== null) {
                $hours = (int) config('disputes.self_resolution_response_hours', 48);
                $dispute->forceFill([
                    'awaiting_user_id' => $other->id,
                    'response_required_by' => now()->addHours($hours),
                ])->save();

                $other->notify(new QuestDisputeUpdatedNotification(
                    $dispute,
                    __('Dispute thread updated'),
                    __(':name added an update on “:title”. The timer refreshed — please respond.', ['name' => $author->first_name ?: $author->name, 'title' => $quest->title])
                ));
            }

            return $msg;
        });
    }

    public function submitSettlementOffer(User $author, QuestDispute $dispute, int $clientSharePercent, ?string $note): DisputeSettlementOffer
    {
        $this->assertParty($author, $dispute);
        if (! $dispute->isOpen()) {
            throw ValidationException::withMessages(['dispute' => [__('This dispute is already closed.')]]);
        }
        if ($clientSharePercent < 0 || $clientSharePercent > 100) {
            throw ValidationException::withMessages(['client_share_percent' => [__('Client share must be between 0 and 100.')]]);
        }

        return DB::transaction(function () use ($author, $dispute, $clientSharePercent, $note): DisputeSettlementOffer {
            $dispute->settlementOffers()
                ->where('status', DisputeSettlementOfferStatus::Pending)
                ->update(['status' => DisputeSettlementOfferStatus::Superseded]);

            $offer = $dispute->settlementOffers()->create([
                'offered_by_user_id' => $author->id,
                'client_share_percent' => $clientSharePercent,
                'note' => $note,
                'status' => DisputeSettlementOfferStatus::Pending,
            ]);

            $this->log($dispute, $author, 'dispute.settlement_offered', ['client_share_percent' => $clientSharePercent]);

            $quest = $dispute->quest;
            $other = $quest?->oppositeParty($author);
            if ($other !== null) {
                $hours = (int) config('disputes.self_resolution_response_hours', 48);
                $dispute->forceFill([
                    'awaiting_user_id' => $other->id,
                    'response_required_by' => now()->addHours($hours),
                ])->save();

                $other->notify(new QuestDisputeUpdatedNotification(
                    $dispute,
                    __('Settlement offer received'),
                    __('A proposed fund split was shared on “:title”. Accept, decline, or counter.', ['title' => $quest->title])
                ));
            }

            return $offer;
        });
    }

    public function acceptSettlementOffer(User $actor, DisputeSettlementOffer $offer): void
    {
        $dispute = $offer->dispute;
        $this->assertParty($actor, $dispute);
        if ($offer->status !== DisputeSettlementOfferStatus::Pending) {
            throw ValidationException::withMessages(['offer' => [__('This offer is no longer active.')]]);
        }
        if ((int) $offer->offered_by_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['offer' => [__('You cannot accept your own offer.')]]);
        }

        DB::transaction(function () use ($actor, $offer, $dispute): void {
            $offer->update([
                'status' => DisputeSettlementOfferStatus::Accepted,
                'responded_at' => now(),
            ]);

            $dispute->update([
                'status' => QuestDisputeStatus::Resolved,
                'phase' => QuestDisputePhase::Closed,
                'resolved_at' => now(),
                'resolution_outcome' => 'settlement_accepted',
                'final_client_share_percent' => $offer->client_share_percent,
                'awaiting_user_id' => null,
                'response_required_by' => null,
                'ruling_required_by' => null,
            ]);

            $this->log($dispute, $actor, 'dispute.settlement_accepted', ['offer_id' => $offer->id]);
            app(DisputeEscrowSettlementService::class)->executeAcceptedSettlement($offer->fresh());
            $this->reconcileContractAfterDisputeClosed($dispute->fresh());
            $this->notifyBoth($dispute, __('Dispute resolved by settlement'), __('Parties agreed to a split. Escrow movement has been applied where funds were available.'));
        });
    }

    public function declineSettlementOffer(User $actor, DisputeSettlementOffer $offer): void
    {
        $dispute = $offer->dispute;
        $this->assertParty($actor, $dispute);
        if ($offer->status !== DisputeSettlementOfferStatus::Pending) {
            throw ValidationException::withMessages(['offer' => [__('This offer is no longer active.')]]);
        }
        if ((int) $offer->offered_by_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['offer' => [__('Use a counter-offer instead of declining your own proposal.')]]);
        }

        DB::transaction(function () use ($actor, $offer, $dispute): void {
            $offer->update([
                'status' => DisputeSettlementOfferStatus::Declined,
                'responded_at' => now(),
            ]);
            $this->log($dispute, $actor, 'dispute.settlement_declined', ['offer_id' => $offer->id]);

            $quest = $dispute->quest;
            $other = $quest?->oppositeParty($actor);
            if ($other !== null) {
                $hours = (int) config('disputes.self_resolution_response_hours', 48);
                $dispute->forceFill([
                    'awaiting_user_id' => $other->id,
                    'response_required_by' => now()->addHours($hours),
                ])->save();

                $other->notify(new QuestDisputeUpdatedNotification($dispute, __('Settlement offer declined'), __('The other party declined the proposed split on “:title”.', ['title' => $quest->title])));
            }
        });
    }

    public function recordMutualResolveAck(User $actor, QuestDispute $dispute): void
    {
        $this->assertParty($actor, $dispute);
        if (! $dispute->isOpen()) {
            throw ValidationException::withMessages(['dispute' => [__('This dispute is already closed.')]]);
        }

        $quest = $dispute->quest;
        if ($quest === null) {
            return;
        }

        DB::transaction(function () use ($actor, $dispute, $quest): void {
            if ((int) $actor->id === (int) $quest->client_id) {
                $dispute->forceFill(['client_agrees_resolve_at' => now()])->save();
            } elseif ((int) $quest->freelancer_id === (int) $actor->id) {
                $dispute->forceFill(['freelancer_agrees_resolve_at' => now()])->save();
            }

            $this->log($dispute, $actor, 'dispute.mutual_resolve_ack', []);

            $dispute->refresh();

            if ($dispute->client_agrees_resolve_at !== null && $dispute->freelancer_agrees_resolve_at !== null) {
                $dispute->update([
                    'status' => QuestDisputeStatus::Resolved,
                    'phase' => QuestDisputePhase::Closed,
                    'resolved_at' => now(),
                    'resolution_outcome' => 'mutual_resolve',
                    'awaiting_user_id' => null,
                    'response_required_by' => null,
                    'ruling_required_by' => null,
                ]);
                $this->reconcileContractAfterDisputeClosed($dispute->fresh());
                $this->notifyBoth($dispute, __('Dispute closed mutually'), __('Both parties agreed to resolve without a ruling. Outstanding money movement still follows escrow activation.'));
            } else {
                $other = $quest->oppositeParty($actor);
                $other?->notify(new QuestDisputeUpdatedNotification(
                    $dispute,
                    __('Partner agreed to resolve'),
                    __('The other party pressed “I agree to resolve” on “:title”. Confirm if you are aligned.', ['title' => $quest->title])
                ));
            }
        });
    }

    public function processDeadlines(): int
    {
        $processed = 0;

        $disputes = QuestDispute::query()
            ->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn])
            ->whereNull('resolved_at')
            ->with(['quest'])
            ->get();

        foreach ($disputes as $dispute) {
            if ($dispute->status === QuestDisputeStatus::SelfResolving
                && $dispute->response_required_by !== null
                && now()->greaterThan($dispute->response_required_by)
                && $dispute->awaiting_user_id !== null) {
                $this->escalateAfterSilence($dispute);
                $processed++;
            } elseif ($dispute->status === QuestDisputeStatus::Escalated
                && $dispute->ruling_required_by !== null
                && now()->greaterThan($dispute->ruling_required_by)) {
                $this->autoTimedSplit($dispute);
                $processed++;
            }
        }

        return $processed;
    }

    protected function escalateAfterSilence(QuestDispute $dispute): void
    {
        DB::transaction(function () use ($dispute): void {
            $missedBy = $dispute->awaiting_user_id;
            $rulingHours = (int) config('disputes.formal_no_response_ruling_hours', 72);

            $dispute->update([
                'status' => QuestDisputeStatus::Escalated,
                'phase' => QuestDisputePhase::FormalReview,
                'tier' => max(1, (int) $dispute->tier),
                'escalated_at' => now(),
                'response_required_by' => null,
                'awaiting_user_id' => null,
                'ruling_required_by' => now()->addHours($rulingHours),
            ]);

            $this->log($dispute, null, 'dispute.escalated_silence', ['missed_user_id' => $missedBy]);

            $this->systemMessage($dispute, __(
                'Tier escalation: a response timer expired. Both parties now have :hours hours to post a final evidence summary before the platform applies a timed default.',
                ['hours' => $rulingHours]
            ));

            $this->notifyBoth($dispute, __('Dispute escalated'), __('A countdown expired without a required update. Please upload final evidence within the new window.'));

            app(\App\Services\Platform\PlatformSlaService::class)->start(
                'dispute_resolution',
                $dispute,
                null,
                null,
                [
                    'subject_label' => $dispute->quest?->title ?? "Dispute #{$dispute->id}",
                    'dispute_uuid' => $dispute->uuid,
                ],
                now(),
            );
        });
    }

    protected function autoTimedSplit(QuestDispute $dispute): void
    {
        DB::transaction(function () use ($dispute): void {
            $dispute->update([
                'status' => QuestDisputeStatus::Resolved,
                'phase' => QuestDisputePhase::Closed,
                'resolved_at' => now(),
                'resolution_outcome' => 'auto_timed_split',
                'final_client_share_percent' => 50,
                'ruling_favoured_user_id' => null,
                'ruling_required_by' => null,
            ]);

            $this->log($dispute, null, 'dispute.auto_timed_split', []);

            $this->systemMessage($dispute, __('Formal window closed without a settlement. Default 50 / 50 split recorded — disbursement awaits live escrow tooling.'));

            $this->reconcileContractAfterDisputeClosed($dispute->fresh());
            $this->notifyBoth($dispute, __('Dispute auto-resolved'), __('The review window ended. A neutral 50 / 50 split was logged for audit purposes.'));
        });
    }

    protected function systemMessage(QuestDispute $dispute, string $body): void
    {
            $dispute->messages()->create([
                'user_id' => null,
                'kind' => DisputeMessageKind::System,
                'body' => $body,
            ]);
    }

    protected function notifyBoth(QuestDispute $dispute, string $headline, string $body): void
    {
        $dispute->loadMissing(['quest.client', 'quest.freelancer']);
        $quest = $dispute->quest;
        if ($quest === null) {
            return;
        }

        foreach (array_filter([$quest->client, $quest->freelancer]) as $u) {
            $u?->notify(new QuestDisputeUpdatedNotification($dispute, $headline, $body));
        }
    }

    protected function reconcileContractAfterDisputeClosed(QuestDispute $dispute): void
    {
        $contract = \App\Models\QuestContract::query()
            ->where('quest_id', $dispute->quest_id)
            ->where('active_dispute_id', $dispute->id)
            ->first();

        if ($contract !== null) {
            app(\App\Services\Contracts\ContractLifecycleService::class)->resolveDispute($contract);
        }
    }

    public function log(QuestDispute $dispute, ?User $actor, string $action, array $properties = []): void
    {
        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }

    protected function assertParty(User $user, QuestDispute $dispute): void
    {
        if (! $dispute->isParty($user)) {
            throw ValidationException::withMessages(['dispute' => [__('You are not a party on this dispute.')]]);
        }
    }

    public function partyFor(User $user, Quest $quest): ?string
    {
        if ((int) $quest->client_id === (int) $user->id) {
            return 'client';
        }
        if ($quest->freelancer_id !== null && (int) $quest->freelancer_id === (int) $user->id) {
            return 'freelancer';
        }

        return null;
    }
}
