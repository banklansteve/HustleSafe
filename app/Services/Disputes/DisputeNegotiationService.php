<?php

namespace App\Services\Disputes;

use App\Enums\DisputeNegotiationOfferStatus;
use App\Enums\DisputeNegotiationPhase;
use App\Enums\DisputeResolutionOption;
use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputePhase;
use App\Enums\QuestDisputeStatus;
use App\Models\DisputeNegotiationOffer;
use App\Models\QuestDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeNegotiationService
{
    public function __construct(
        private readonly DisputeResolutionMatrixService $matrix,
        private readonly QuestDisputeWorkflowService $workflow,
        private readonly DisputeNegotiationNotifier $notifier,
    ) {}

    public function initializePeerNegotiation(QuestDispute $dispute): QuestDispute
    {
        $hours = (int) config('disputes.negotiation.response_hours', 24);
        $dispute->forceFill([
            'negotiation_phase' => DisputeNegotiationPhase::PeerNegotiation->value,
            'client_negotiation_attempts' => 0,
            'freelancer_negotiation_attempts' => 0,
            'response_required_by' => now()->addHours($hours),
        ])->save();

        $this->workflow->log($dispute, null, 'negotiation.started', [
            'max_attempts' => config('disputes.negotiation.max_attempts_per_party', 2),
            'response_hours' => $hours,
        ]);

        return $dispute->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function propose(User $actor, QuestDispute $dispute, array $data): DisputeNegotiationOffer
    {
        $this->assertPeerPhase($dispute);
        $partyRole = $this->assertParty($actor, $dispute);
        $this->assertCanPropose($dispute, $partyRole);

        $option = DisputeResolutionOption::from((string) $data['option']);
        $this->matrix->assertActorCanUse($partyRole, $option->value);
        $terms = $this->normalizeTerms($option, $data);
        $attempt = $this->nextAttemptNumber($dispute, $partyRole);
        $isFinal = $attempt >= (int) config('disputes.negotiation.max_attempts_per_party', 2);

        return DB::transaction(function () use ($actor, $dispute, $partyRole, $option, $terms, $attempt, $isFinal, $data): DisputeNegotiationOffer {
            $parentId = $dispute->active_negotiation_offer_id;
            if ($parentId) {
                DisputeNegotiationOffer::query()
                    ->where('id', $parentId)
                    ->where('status', DisputeNegotiationOfferStatus::Pending)
                    ->update([
                        'status' => DisputeNegotiationOfferStatus::Countered,
                        'responded_at' => now(),
                        'responded_by_user_id' => $actor->id,
                        'response_action' => 'counter',
                    ]);
            }

            $this->incrementAttempts($dispute, $partyRole);

            $otherRole = $partyRole === 'client' ? 'freelancer' : 'client';
            $hours = (int) config('disputes.negotiation.response_hours', 24);

            $offer = DisputeNegotiationOffer::query()->create([
                'quest_dispute_id' => $dispute->id,
                'parent_offer_id' => $parentId,
                'offered_by_user_id' => $actor->id,
                'party_role' => $partyRole,
                'attempt_number' => $attempt,
                'option' => $option->value,
                'terms' => $terms,
                'status' => DisputeNegotiationOfferStatus::Pending,
                'is_final_offer' => $isFinal,
                'awaiting_party_role' => $otherRole,
                'response_required_by' => now()->addHours($hours),
            ]);

            $dispute->forceFill([
                'active_negotiation_offer_id' => $offer->id,
                'awaiting_user_id' => $this->userIdForRole($dispute, $otherRole),
                'response_required_by' => $offer->response_required_by,
            ])->save();

            $this->workflow->log($dispute, $actor, 'negotiation.proposed', [
                'offer_id' => $offer->id,
                'option' => $option->value,
                'attempt' => $attempt,
                'is_final_offer' => $isFinal,
            ]);

            $this->notifier->proposalSubmitted($dispute->fresh(), $actor, $offer);

            return $offer;
        });
    }

    public function accept(User $actor, DisputeNegotiationOffer $offer): QuestDispute
    {
        $dispute = $offer->dispute;
        $this->assertPeerPhase($dispute);
        $partyRole = $this->assertParty($actor, $dispute);

        if ($offer->status !== DisputeNegotiationOfferStatus::Pending) {
            throw ValidationException::withMessages(['offer' => [__('This offer is no longer active.')]]);
        }

        if ($offer->awaiting_party_role !== $partyRole) {
            throw ValidationException::withMessages(['offer' => [__('It is not your turn to respond.')]]);
        }

        if ((int) $offer->offered_by_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['offer' => [__('You cannot accept your own offer.')]]);
        }

        return DB::transaction(function () use ($actor, $offer, $dispute): QuestDispute {
            $offer->update([
                'status' => DisputeNegotiationOfferStatus::Accepted,
                'responded_at' => now(),
                'responded_by_user_id' => $actor->id,
                'response_action' => 'accept',
            ]);

            $dispute->forceFill([
                'negotiation_phase' => DisputeNegotiationPhase::AwaitingMutualApproval->value,
                'management_status' => QuestDisputeManagementStatus::AwaitingMutualApproval,
                'mutual_agreement_submitted_at' => now(),
                'resolution_outcome' => 'mutual_negotiation_accepted',
                'final_client_share_percent' => $offer->terms['client_share_percent'] ?? null,
                'awaiting_user_id' => null,
                'response_required_by' => null,
            ])->save();

            $this->workflow->log($dispute, $actor, 'negotiation.accepted', [
                'offer_id' => $offer->id,
                'option' => $offer->option,
            ]);

            $this->notifier->mutualAgreementReached($dispute->fresh(), $offer);

            return $dispute->fresh();
        });
    }

    public function reject(User $actor, DisputeNegotiationOffer $offer): QuestDispute
    {
        $dispute = $offer->dispute;
        $this->assertPeerPhase($dispute);
        $partyRole = $this->assertParty($actor, $dispute);

        if ($offer->status !== DisputeNegotiationOfferStatus::Pending) {
            throw ValidationException::withMessages(['offer' => [__('This offer is no longer active.')]]);
        }

        if ($offer->awaiting_party_role !== $partyRole) {
            throw ValidationException::withMessages(['offer' => [__('It is not your turn to respond.')]]);
        }

        if (! $offer->is_final_offer) {
            throw ValidationException::withMessages(['offer' => [__('You can only reject a final offer. Counter-propose instead.')]]);
        }

        return DB::transaction(function () use ($actor, $offer, $dispute): QuestDispute {
            $offer->update([
                'status' => DisputeNegotiationOfferStatus::Rejected,
                'responded_at' => now(),
                'responded_by_user_id' => $actor->id,
                'response_action' => 'reject',
            ]);

            return $this->escalateToMediation($dispute, $actor, 'final_offer_rejected');
        });
    }

    public function acknowledgeBindingMediation(User $actor, QuestDispute $dispute): QuestDispute
    {
        $partyRole = $this->assertParty($actor, $dispute);
        $quest = $dispute->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['dispute' => [__('Quest not found.')]]);
        }

        if ((int) $actor->id === (int) $quest->client_id) {
            $dispute->forceFill(['binding_mediation_ack_client_at' => now()])->save();
        } elseif ((int) $quest->freelancer_id === (int) $actor->id) {
            $dispute->forceFill(['binding_mediation_ack_freelancer_at' => now()])->save();
        }

        $dispute->refresh();
        $this->workflow->log($dispute, $actor, 'negotiation.binding_mediation_ack', ['party' => $partyRole]);

        if ($dispute->binding_mediation_ack_client_at && $dispute->binding_mediation_ack_freelancer_at) {
            $this->workflow->log($dispute, null, 'negotiation.binding_mediation_complete', []);
        }

        return $dispute->fresh();
    }

    public function escalateToMediation(QuestDispute $dispute, ?User $actor, string $reason): QuestDispute
    {
        $dispute->forceFill([
            'negotiation_phase' => DisputeNegotiationPhase::Mediation->value,
            'management_status' => QuestDisputeManagementStatus::Mediation,
            'status' => QuestDisputeStatus::Escalated,
            'phase' => QuestDisputePhase::FormalReview,
            'escalated_at' => now(),
            'awaiting_user_id' => null,
            'response_required_by' => null,
        ])->save();

        $this->workflow->log($dispute, $actor, 'negotiation.escalated_mediation', ['reason' => $reason]);
        $this->notifier->escalatedToMediation($dispute->fresh(), $reason);

        return $dispute->fresh();
    }

    public function expireStaleOffer(DisputeNegotiationOffer $offer): void
    {
        if ($offer->status !== DisputeNegotiationOfferStatus::Pending) {
            return;
        }

        if ($offer->response_required_by === null || now()->lessThan($offer->response_required_by)) {
            return;
        }

        DB::transaction(function () use ($offer): void {
            $offer->update(['status' => DisputeNegotiationOfferStatus::Expired]);
            $dispute = $offer->dispute;
            $this->escalateToMediation($dispute, null, 'response_deadline_missed');
        });
    }

    public function attemptsExhausted(QuestDispute $dispute): bool
    {
        $max = (int) config('disputes.negotiation.max_attempts_per_party', 2);

        return (int) $dispute->client_negotiation_attempts >= $max
            && (int) $dispute->freelancer_negotiation_attempts >= $max;
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForParty(QuestDispute $dispute, User $viewer): array
    {
        $dispute->loadMissing(['negotiationOffers.offeredBy:id,name,first_name', 'quest']);
        $role = $this->workflow->partyFor($viewer, $dispute->quest);
        $active = $dispute->activeNegotiationOffer;
        $maxAttempts = (int) config('disputes.negotiation.max_attempts_per_party', 2);

        return [
            'phase' => $dispute->negotiation_phase,
            'phase_label' => DisputeNegotiationPhase::tryFrom((string) $dispute->negotiation_phase)?->label(),
            'headline' => DisputeNegotiationPhase::tryFrom((string) $dispute->negotiation_phase)?->partyHeadline(),
            'max_attempts_per_party' => $maxAttempts,
            'client_attempts' => (int) $dispute->client_negotiation_attempts,
            'freelancer_attempts' => (int) $dispute->freelancer_negotiation_attempts,
            'viewer_attempts_used' => $role === 'client'
                ? (int) $dispute->client_negotiation_attempts
                : ($role === 'freelancer' ? (int) $dispute->freelancer_negotiation_attempts : 0),
            'viewer_attempts_remaining' => $role
                ? max(0, $maxAttempts - ($role === 'client' ? (int) $dispute->client_negotiation_attempts : (int) $dispute->freelancer_negotiation_attempts))
                : 0,
            'viewer_role' => $role,
            'awaiting_viewer' => $active && $role && $active->awaiting_party_role === $role,
            'active_offer' => $active ? $this->offerPayload($active) : null,
            'history' => $dispute->negotiationOffers
                ->sortBy('id')
                ->values()
                ->map(fn (DisputeNegotiationOffer $o) => $this->offerPayload($o))
                ->all(),
            'binding_mediation_ack_client' => $dispute->binding_mediation_ack_client_at?->toIso8601String(),
            'binding_mediation_ack_freelancer' => $dispute->binding_mediation_ack_freelancer_at?->toIso8601String(),
            'viewer_binding_acknowledged' => $role === 'client'
                ? (bool) $dispute->binding_mediation_ack_client_at
                : ($role === 'freelancer' ? (bool) $dispute->binding_mediation_ack_freelancer_at : false),
            'both_binding_acknowledged' => $dispute->binding_mediation_ack_client_at !== null
                && $dispute->binding_mediation_ack_freelancer_at !== null,
            'response_required_by' => $dispute->response_required_by?->timezone('Africa/Lagos')->toIso8601String(),
            'rejection_window_ends_at' => $dispute->rejection_window_ends_at?->timezone('Africa/Lagos')->toIso8601String(),
            'can_propose' => $this->canPropose($dispute, $role),
            'can_accept' => $active && $role && $active->awaiting_party_role === $role && $active->status === DisputeNegotiationOfferStatus::Pending,
            'can_counter' => $active && $role && $active->awaiting_party_role === $role
                && $active->status === DisputeNegotiationOfferStatus::Pending
                && $this->attemptsRemaining($dispute, $role) > 0,
            'can_reject_final' => $active && $role && $active->awaiting_party_role === $role
                && $active->is_final_offer && $active->status === DisputeNegotiationOfferStatus::Pending
                && $this->attemptsRemaining($dispute, $role) === 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function offerPayload(DisputeNegotiationOffer $offer): array
    {
        $dispute = $offer->dispute;

        return [
            'id' => $offer->id,
            'party_role' => $offer->party_role,
            'offered_by' => $offer->offeredBy?->name,
            'attempt_number' => $offer->attempt_number,
            'option' => $offer->option,
            'option_label' => $offer->optionEnum()?->label() ?? $offer->option,
            'summary' => $offer->summaryLabel(),
            'terms' => $offer->terms ?? [],
            'status' => $offer->status?->value ?? (string) $offer->status,
            'is_final_offer' => $offer->is_final_offer,
            'awaiting_party_role' => $offer->awaiting_party_role,
            'response_required_by' => $offer->response_required_by?->timezone('Africa/Lagos')->toIso8601String(),
            'created_at' => $offer->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'accept_url' => route('disputes.negotiation.accept', ['dispute' => $dispute, 'offer' => $offer->id]),
            'reject_url' => route('disputes.negotiation.reject', ['dispute' => $dispute, 'offer' => $offer->id]),
        ];
    }

    protected function assertPeerPhase(QuestDispute $dispute): void
    {
        if ($dispute->negotiation_phase !== DisputeNegotiationPhase::PeerNegotiation->value) {
            throw ValidationException::withMessages(['dispute' => [__('Peer negotiation is not active on this dispute.')]]);
        }
    }

    protected function assertParty(User $actor, QuestDispute $dispute): string
    {
        $quest = $dispute->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['dispute' => [__('Quest not found.')]]);
        }

        $role = $this->workflow->partyFor($actor, $quest);
        if ($role === null || ! $dispute->isParty($actor)) {
            throw ValidationException::withMessages(['dispute' => [__('You are not a party on this dispute.')]]);
        }

        return $role;
    }

    protected function assertCanPropose(QuestDispute $dispute, string $partyRole): void
    {
        if ($this->attemptsRemaining($dispute, $partyRole) <= 0) {
            throw ValidationException::withMessages(['attempts' => [__('You have used all negotiation attempts.')]]);
        }

        $active = $dispute->activeNegotiationOffer;
        if ($active === null) {
            if ((int) $dispute->opened_by_user_id !== (int) $this->userIdForRole($dispute, $partyRole)) {
                throw ValidationException::withMessages(['turn' => [__('Wait for the other party to open negotiation with a proposal, or counter their offer.')]]);
            }

            return;
        }

        if ($active->awaiting_party_role !== $partyRole) {
            throw ValidationException::withMessages(['turn' => [__('It is not your turn to propose.')]]);
        }
    }

    protected function canPropose(?QuestDispute $dispute, ?string $role): bool
    {
        if ($dispute === null || $role === null || $dispute->negotiation_phase !== DisputeNegotiationPhase::PeerNegotiation->value) {
            return false;
        }

        if ($this->attemptsRemaining($dispute, $role) <= 0) {
            return false;
        }

        $active = $dispute->activeNegotiationOffer;
        if ($active === null) {
            return (int) $dispute->opened_by_user_id === (int) $this->userIdForRole($dispute, $role);
        }

        return $active->awaiting_party_role === $role && $active->status === DisputeNegotiationOfferStatus::Pending;
    }

    protected function attemptsRemaining(QuestDispute $dispute, string $partyRole): int
    {
        $max = (int) config('disputes.negotiation.max_attempts_per_party', 2);
        $used = $partyRole === 'client'
            ? (int) $dispute->client_negotiation_attempts
            : (int) $dispute->freelancer_negotiation_attempts;

        return max(0, $max - $used);
    }

    protected function nextAttemptNumber(QuestDispute $dispute, string $partyRole): int
    {
        return $partyRole === 'client'
            ? (int) $dispute->client_negotiation_attempts + 1
            : (int) $dispute->freelancer_negotiation_attempts + 1;
    }

    protected function incrementAttempts(QuestDispute $dispute, string $partyRole): void
    {
        if ($partyRole === 'client') {
            $dispute->increment('client_negotiation_attempts');
        } else {
            $dispute->increment('freelancer_negotiation_attempts');
        }
        $dispute->refresh();
    }

    protected function userIdForRole(QuestDispute $dispute, string $role): ?int
    {
        $quest = $dispute->quest;
        if ($quest === null) {
            return null;
        }

        return $role === 'client' ? $quest->client_id : $quest->freelancer_id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeTerms(DisputeResolutionOption $option, array $data): array
    {
        return app(DisputeResolutionRequestService::class)
            ->normalizeTermsForNegotiation($option, $data);
    }
}
