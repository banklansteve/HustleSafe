<?php

namespace App\Services\Disputes;

use App\Enums\DisputeNegotiationOfferStatus;
use App\Enums\DisputeNegotiationPhase;
use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputeStatus;
use App\Models\DisputeEvent;
use App\Models\DisputeNegotiationOffer;
use App\Models\QuestDispute;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeEnforcementService
{
    public function __construct(
        private readonly DisputeOutcomeExecutionService $outcomeExecution,
        private readonly DisputeSanctionExecutionService $sanctionExecution,
        private readonly DisputeNegotiationNotifier $notifier,
    ) {}

    public function approveMutualAgreement(QuestDispute $dispute, User $staff, Request $request): QuestDispute
    {
        if ($dispute->management_status !== QuestDisputeManagementStatus::AwaitingMutualApproval) {
            throw ValidationException::withMessages(['dispute' => __('This dispute is not awaiting mutual agreement approval.')]);
        }

        $offer = DisputeNegotiationOffer::query()
            ->where('quest_dispute_id', $dispute->id)
            ->where('status', DisputeNegotiationOfferStatus::Accepted)
            ->latest('id')
            ->first();

        if ($offer === null) {
            throw ValidationException::withMessages(['offer' => __('No accepted negotiation offer found.')]);
        }

        $clientShare = (int) ($offer->terms['client_share_percent'] ?? $dispute->final_client_share_percent ?? 50);
        $appealDays = (int) config('disputes.negotiation.mutual_approval_appeal_days', 4);

        return DB::transaction(function () use ($dispute, $staff, $request, $offer, $clientShare, $appealDays): QuestDispute {
            $payout = $this->outcomeExecution->execute(
                $dispute,
                $staff,
                $clientShare,
                __('Mutual negotiation settlement approved by Customer Support on :ref', ['ref' => $dispute->displayReference()]),
            );

            $dispute->forceFill([
                'negotiation_phase' => DisputeNegotiationPhase::Final->value,
                'management_status' => QuestDisputeManagementStatus::Resolved,
                'status' => QuestDisputeStatus::Resolved,
                'resolved_at' => now(),
                'management_resolved_at' => now(),
                'resolution_outcome' => 'mutual_negotiation_approved',
                'final_client_share_percent' => $clientShare,
                'appeal_window_ends_at' => now()->addDays($appealDays),
            ])->save();

            $this->recordEvent($dispute, $staff, 'negotiation.mutual_approved', [
                'offer_id' => $offer->id,
                'client_share_percent' => $clientShare,
                'payout' => $payout,
            ]);

            $dispute->loadMissing(['quest.client', 'quest.freelancer']);
            $summary = $offer->summaryLabel();
            foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
                $party?->notify(new \App\Notifications\QuestDisputeUpdatedNotification(
                    $dispute,
                    __('Settlement approved'),
                    __('Customer Support approved your agreement: :summary. Funds are being transferred.', ['summary' => $summary]),
                    __('You may appeal within :days days if you believe the approval was incorrect.', ['days' => $appealDays]),
                    __('View dispute'),
                    'both',
                ));
            }

            return $dispute->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $sanctions
     */
    public function issuePendingEnforcement(
        QuestDispute $dispute,
        User $superAdmin,
        int $clientSharePercent,
        array $sanctions,
        ?string $decisionNotes,
        string $outcomeAction = 'standard_payout',
    ): QuestDispute {
        $hours = (int) config('disputes.negotiation.enforcement_rejection_hours', 48);
        $summary = __('Client :client% / Freelancer :freelancer%', [
            'client' => $clientSharePercent,
            'freelancer' => 100 - $clientSharePercent,
        ]);

        $dispute->forceFill([
            'negotiation_phase' => DisputeNegotiationPhase::AwaitingEnforcement->value,
            'management_status' => QuestDisputeManagementStatus::AwaitingEnforcement,
            'status' => QuestDisputeStatus::AwaitingRuling,
            'final_client_share_percent' => $clientSharePercent,
            'super_admin_decided_by' => $superAdmin->id,
            'super_admin_decided_at' => now(),
            'super_admin_decision_notes' => $decisionNotes,
            'sanction_payload' => $sanctions,
            'outcome_action' => $outcomeAction,
            'enforcement_pending_at' => now(),
            'rejection_window_ends_at' => now()->addHours($hours),
            'resolution_outcome' => 'mediation_decision_pending',
        ])->save();

        $this->recordEvent($dispute, $superAdmin, 'super_admin.decision_pending_enforcement', [
            'client_share_percent' => $clientSharePercent,
            'rejection_hours' => $hours,
        ]);

        $this->notifier->enforcementPending($dispute->fresh(), $summary);

        return $dispute->fresh();
    }

    public function enforcePendingDecision(QuestDispute $dispute, ?User $actor = null): QuestDispute
    {
        if ($dispute->management_status !== QuestDisputeManagementStatus::AwaitingEnforcement) {
            throw ValidationException::withMessages(['dispute' => __('No pending enforcement on this dispute.')]);
        }

        if ($dispute->appeals()->whereIn('status', ['filed', 'counter_pending', 'under_review'])->exists()) {
            throw ValidationException::withMessages(['appeal' => __('An appeal is open on this dispute.')]);
        }

        $clientShare = (int) ($dispute->final_client_share_percent ?? 50);
        $sanctions = $dispute->sanction_payload ?? [];
        $superAdmin = User::query()->find($dispute->super_admin_decided_by) ?? $actor;

        if ($superAdmin === null) {
            throw ValidationException::withMessages(['dispute' => __('Super Admin decision record missing.')]);
        }

        return DB::transaction(function () use ($dispute, $superAdmin, $clientShare, $sanctions, $actor): QuestDispute {
            $payout = $this->outcomeExecution->execute(
                $dispute,
                $superAdmin,
                $clientShare,
                $dispute->super_admin_decision_notes,
                $sanctions,
            );

            $this->sanctionExecution->execute($dispute, $superAdmin, $sanctions);

            $dispute->forceFill([
                'negotiation_phase' => DisputeNegotiationPhase::Final->value,
                'management_status' => QuestDisputeManagementStatus::Finalized,
                'status' => QuestDisputeStatus::Resolved,
                'resolved_at' => now(),
                'management_resolved_at' => now(),
                'final_binding_at' => now(),
                'finalized_at' => now(),
                'rejection_window_ends_at' => null,
                'resolution_outcome' => 'mediation_decision_enforced',
            ])->save();

            $this->recordEvent($dispute, $actor, 'negotiation.enforcement_executed', [
                'client_share_percent' => $clientShare,
                'payout' => $payout,
            ]);

            $summary = __('Client :client% / Freelancer :freelancer%', [
                'client' => $clientShare,
                'freelancer' => 100 - $clientShare,
            ]);
            $this->notifier->decisionFinalized($dispute->fresh(), $summary);

            return $dispute->fresh();
        });
    }

    public function processExpiredEnforcementWindows(): int
    {
        $processed = 0;

        QuestDispute::query()
            ->where('management_status', QuestDisputeManagementStatus::AwaitingEnforcement)
            ->whereNotNull('rejection_window_ends_at')
            ->where('rejection_window_ends_at', '<', now())
            ->whereDoesntHave('appeals', fn ($q) => $q->whereIn('status', ['filed', 'counter_pending', 'under_review']))
            ->each(function (QuestDispute $dispute) use (&$processed): void {
                try {
                    $this->enforcePendingDecision($dispute);
                    $processed++;
                } catch (\Throwable) {
                    // skip invalid rows
                }
            });

        return $processed;
    }

    public function finalizeAfterAppealWindow(QuestDispute $dispute): QuestDispute
    {
        if ($dispute->appeal_window_ends_at === null || now()->lessThan($dispute->appeal_window_ends_at)) {
            throw ValidationException::withMessages(['appeal' => __('Appeal window has not ended.')]);
        }

        if ($dispute->appeals()->whereIn('status', ['filed', 'counter_pending', 'under_review'])->exists()) {
            throw ValidationException::withMessages(['appeal' => __('An appeal is still open.')]);
        }

        $dispute->forceFill([
            'negotiation_phase' => DisputeNegotiationPhase::Final->value,
            'management_status' => QuestDisputeManagementStatus::Finalized,
            'final_binding_at' => now(),
            'finalized_at' => now(),
            'appeal_window_ends_at' => null,
        ])->save();

        $this->recordEvent($dispute, null, 'negotiation.finalized', []);

        return $dispute->fresh();
    }

    public function processExpiredMutualAppealWindows(): int
    {
        $processed = 0;

        QuestDispute::query()
            ->where('management_status', QuestDisputeManagementStatus::Resolved)
            ->where('resolution_outcome', 'mutual_negotiation_approved')
            ->whereNotNull('appeal_window_ends_at')
            ->where('appeal_window_ends_at', '<', now())
            ->whereNull('finalized_at')
            ->whereDoesntHave('appeals', fn ($q) => $q->whereIn('status', ['filed', 'counter_pending', 'under_review']))
            ->each(function (QuestDispute $dispute) use (&$processed): void {
                $this->finalizeAfterAppealWindow($dispute);
                $processed++;
            });

        return $processed;
    }

    public function requiresEnforcementWindow(QuestDispute $dispute): bool
    {
        if ($dispute->resolution_outcome === 'mutual_negotiation_accepted') {
            return false;
        }

        return $dispute->negotiationOffers()->exists()
            || in_array((string) $dispute->negotiation_phase, [
                DisputeNegotiationPhase::Mediation->value,
                DisputeNegotiationPhase::EscalatingToMediation->value,
            ], true);
    }

    private function recordEvent(QuestDispute $dispute, ?User $actor, string $action, array $properties = []): void
    {
        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
