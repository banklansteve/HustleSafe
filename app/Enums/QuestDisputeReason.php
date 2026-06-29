<?php

namespace App\Enums;

/**
 * Role-based dispute reasons. Legacy values remain for existing dispute records.
 */
enum QuestDisputeReason: string
{
    // Client — delivery & quality
    case DeliverablesNotSubmitted = 'deliverables_not_submitted';
    case DeliverablesIncompletePartial = 'deliverables_incomplete_partial';
    case WorkNotMeetSpec = 'work_not_meet_spec';
    case QualityBelowExpectations = 'quality_below_expectations';
    case WorkCopiedPlagiarized = 'work_copied_plagiarized';
    case DeliverableFormatIncorrect = 'deliverable_format_incorrect';

    // Client — scope & communication
    case ScopeChangedWithoutApproval = 'scope_changed_without_approval';
    case FreelancerUnresponsive = 'freelancer_unresponsive';
    case FreelancerMissedDeadlineWithoutNotice = 'freelancer_missed_deadline_without_notice';
    case RequirementsNotFollowed = 'requirements_not_followed';
    case FreelancerRefusesRevisions = 'freelancer_refuses_revisions';

    // Client — conduct & professionalism
    case FreelancerUnprofessional = 'freelancer_unprofessional';
    case FreelancerOffPlatformContact = 'freelancer_off_platform_contact';
    case FreelancerThreateningAbusive = 'freelancer_threatening_abusive';
    case SuspiciousAccountActivity = 'suspicious_account_activity';

    // Client — payment & contract
    case PaymentReleasedWorkIncomplete = 'payment_released_work_incomplete';
    case WithholdPaymentPendingFix = 'withhold_payment_pending_fix';
    case ContractNeedsMediation = 'contract_needs_mediation';

    // Freelancer — scope & requirements
    case ScopeIncreasedAfterAward = 'scope_increased_after_award';
    case RequirementsUnclearContradictory = 'requirements_unclear_contradictory';
    case ClientWorkOutsideScope = 'client_work_outside_scope';
    case DeliverableRequirementsChanged = 'deliverable_requirements_changed';
    case SpecsImpossibleWithBudget = 'specs_impossible_with_budget';

    // Freelancer — client behavior
    case ClientNotResponding = 'client_not_responding';
    case ClientRejectingUnfairly = 'client_rejecting_unfairly';
    case ClientExcessiveRevisions = 'client_excessive_revisions';
    case ClientThreateningAbusive = 'client_threatening_abusive';
    case ClientAvoidingPayment = 'client_avoiding_payment';

    // Freelancer — payment & terms
    case PaymentNotReleasedAfterDeadline = 'payment_not_released_after_deadline';
    case EscrowHeldWithoutReason = 'escrow_held_without_reason';
    case PaymentAmountDisputed = 'payment_amount_disputed';
    case ClientDiscountAfterCompletion = 'client_discount_after_completion';
    case NonPaymentAfterDelivery = 'non_payment_after_delivery';

    // Freelancer — assessment & feedback
    case ClientDidntReviewProperly = 'client_didnt_review_properly';
    case FeedbackVagueNotActionable = 'feedback_vague_not_actionable';
    case ClientRatingUnfairlyLow = 'client_rating_unfairly_low';
    case EvaluationDoesntMatchQuality = 'evaluation_doesnt_match_quality';

    // Legacy (pre role-based intake)
    case ClientWontApproveDelivery = 'client_wont_approve_delivery';
    case PaidButUndelivered = 'paid_but_undelivered';
    case QualityMismatch = 'quality_mismatch';
    case ScopeCreep = 'scope_creep';
    case MilestoneRejectedUnfairly = 'milestone_rejected_unfairly';
    case RefundAfterWorkStarted = 'refund_after_work_started';
    case SilenceComms = 'silence_comms';
    case ContractViolation = 'contract_violation';

    public function label(): string
    {
        return match ($this) {
            self::DeliverablesNotSubmitted => __('Deliverables not submitted'),
            self::DeliverablesIncompletePartial => __('Deliverables incomplete or partial'),
            self::WorkNotMeetSpec => __('Work doesn\'t meet specification/requirements'),
            self::QualityBelowExpectations => __('Quality significantly below expectations'),
            self::WorkCopiedPlagiarized => __('Work is copied/plagiarized'),
            self::DeliverableFormatIncorrect => __('Deliverable format/files incorrect'),
            self::ScopeChangedWithoutApproval => __('Scope was changed by freelancer without approval'),
            self::FreelancerUnresponsive => __('Freelancer unresponsive to clarifications'),
            self::FreelancerMissedDeadlineWithoutNotice => __('Freelancer missed deadline without notice'),
            self::RequirementsNotFollowed => __('Requirements were misunderstood/not followed'),
            self::FreelancerRefusesRevisions => __('Freelancer refuses to make revisions'),
            self::FreelancerUnprofessional => __('Freelancer behaved unprofessionally'),
            self::FreelancerOffPlatformContact => __('Freelancer attempted off-platform contact'),
            self::FreelancerThreateningAbusive => __('Freelancer threatening/abusive behavior'),
            self::SuspiciousAccountActivity => __('Suspicious account activity detected'),
            self::PaymentReleasedWorkIncomplete => __('Payment released but work incomplete'),
            self::WithholdPaymentPendingFix => __('Agree to withhold payment pending fix'),
            self::ContractNeedsMediation => __('Contract dispute needs mediation'),
            self::ScopeIncreasedAfterAward => __('Job scope significantly increased after award'),
            self::RequirementsUnclearContradictory => __('Requirements unclear/contradictory at start'),
            self::ClientWorkOutsideScope => __('Client requesting work outside contract scope'),
            self::DeliverableRequirementsChanged => __('Deliverable requirements changed mid-project'),
            self::SpecsImpossibleWithBudget => __('Specifications impossible to meet with budget/timeline'),
            self::ClientNotResponding => __('Client not responding to messages'),
            self::ClientRejectingUnfairly => __('Client rejecting work unfairly/without valid reason'),
            self::ClientExcessiveRevisions => __('Client requesting excessive revisions (>agreed limit)'),
            self::ClientThreateningAbusive => __('Client threatening/abusive behavior'),
            self::ClientAvoidingPayment => __('Client attempting to avoid payment'),
            self::PaymentNotReleasedAfterDeadline => __('Payment not released after deadline'),
            self::EscrowHeldWithoutReason => __('Escrow held without valid reason'),
            self::PaymentAmountDisputed => __('Payment amount disputed'),
            self::ClientDiscountAfterCompletion => __('Client requesting discount after completion'),
            self::NonPaymentAfterDelivery => __('Non-payment after work delivered'),
            self::ClientDidntReviewProperly => __('Client didn\'t review deliverable properly'),
            self::FeedbackVagueNotActionable => __('Feedback is vague/not actionable'),
            self::ClientRatingUnfairlyLow => __('Client rating unfairly low without reason'),
            self::EvaluationDoesntMatchQuality => __('Evaluation doesn\'t match work quality'),
            self::ClientWontApproveDelivery => __('Work delivered but client will not approve completion'),
            self::PaidButUndelivered => __('Payment released or committed but deliverables missing or incomplete'),
            self::QualityMismatch => __('Delivered work does not match the agreed proposal / spec'),
            self::ScopeCreep => __('Scope expanded beyond the agreed contract without fair adjustment'),
            self::MilestoneRejectedUnfairly => __('Milestone rejected without a valid, documented reason'),
            self::RefundAfterWorkStarted => __('Refund requested after substantive work has started'),
            self::SilenceComms => __('No meaningful communication for the agreed period'),
            self::ContractViolation => __('A material term in the written agreement was violated'),
        };
    }

    public function category(): QuestDisputeCategory
    {
        return match ($this) {
            self::DeliverablesNotSubmitted,
            self::DeliverablesIncompletePartial,
            self::WorkNotMeetSpec,
            self::QualityBelowExpectations,
            self::WorkCopiedPlagiarized,
            self::DeliverableFormatIncorrect,
            self::PaidButUndelivered,
            self::QualityMismatch => QuestDisputeCategory::DeliveryQuality,
            self::ScopeChangedWithoutApproval,
            self::FreelancerUnresponsive,
            self::FreelancerMissedDeadlineWithoutNotice,
            self::RequirementsNotFollowed,
            self::FreelancerRefusesRevisions,
            self::SilenceComms => QuestDisputeCategory::ScopeCommunication,
            self::FreelancerUnprofessional,
            self::FreelancerOffPlatformContact,
            self::FreelancerThreateningAbusive,
            self::SuspiciousAccountActivity => QuestDisputeCategory::ConductProfessionalism,
            self::PaymentReleasedWorkIncomplete,
            self::WithholdPaymentPendingFix,
            self::ContractNeedsMediation,
            self::RefundAfterWorkStarted,
            self::ContractViolation => QuestDisputeCategory::PaymentContract,
            self::ScopeIncreasedAfterAward,
            self::RequirementsUnclearContradictory,
            self::ClientWorkOutsideScope,
            self::DeliverableRequirementsChanged,
            self::SpecsImpossibleWithBudget,
            self::ScopeCreep => QuestDisputeCategory::ScopeRequirements,
            self::ClientNotResponding,
            self::ClientRejectingUnfairly,
            self::ClientExcessiveRevisions,
            self::ClientThreateningAbusive,
            self::ClientAvoidingPayment,
            self::MilestoneRejectedUnfairly => QuestDisputeCategory::ClientBehavior,
            self::PaymentNotReleasedAfterDeadline,
            self::EscrowHeldWithoutReason,
            self::PaymentAmountDisputed,
            self::ClientDiscountAfterCompletion,
            self::NonPaymentAfterDelivery,
            self::ClientWontApproveDelivery => QuestDisputeCategory::PaymentTerms,
            self::ClientDidntReviewProperly,
            self::FeedbackVagueNotActionable,
            self::ClientRatingUnfairlyLow,
            self::EvaluationDoesntMatchQuality => QuestDisputeCategory::AssessmentFeedback,
        };
    }

    /**
     * @return list<string> client|freelancer
     */
    public function raisedByParties(): array
    {
        return match ($this) {
            self::DeliverablesNotSubmitted,
            self::DeliverablesIncompletePartial,
            self::WorkNotMeetSpec,
            self::QualityBelowExpectations,
            self::WorkCopiedPlagiarized,
            self::DeliverableFormatIncorrect,
            self::ScopeChangedWithoutApproval,
            self::FreelancerUnresponsive,
            self::FreelancerMissedDeadlineWithoutNotice,
            self::RequirementsNotFollowed,
            self::FreelancerRefusesRevisions,
            self::FreelancerUnprofessional,
            self::FreelancerOffPlatformContact,
            self::FreelancerThreateningAbusive,
            self::SuspiciousAccountActivity,
            self::PaymentReleasedWorkIncomplete,
            self::WithholdPaymentPendingFix,
            self::ContractNeedsMediation,
            self::PaidButUndelivered,
            self::QualityMismatch,
            self::RefundAfterWorkStarted => ['client'],
            self::ScopeIncreasedAfterAward,
            self::RequirementsUnclearContradictory,
            self::ClientWorkOutsideScope,
            self::DeliverableRequirementsChanged,
            self::SpecsImpossibleWithBudget,
            self::ClientNotResponding,
            self::ClientRejectingUnfairly,
            self::ClientExcessiveRevisions,
            self::ClientThreateningAbusive,
            self::ClientAvoidingPayment,
            self::PaymentNotReleasedAfterDeadline,
            self::EscrowHeldWithoutReason,
            self::PaymentAmountDisputed,
            self::ClientDiscountAfterCompletion,
            self::NonPaymentAfterDelivery,
            self::ClientDidntReviewProperly,
            self::FeedbackVagueNotActionable,
            self::ClientRatingUnfairlyLow,
            self::EvaluationDoesntMatchQuality,
            self::ClientWontApproveDelivery,
            self::ScopeCreep,
            self::MilestoneRejectedUnfairly => ['freelancer'],
            self::SilenceComms,
            self::ContractViolation => ['client', 'freelancer'],
        };
    }

    public function isLegacy(): bool
    {
        return in_array($this, [
            self::ClientWontApproveDelivery,
            self::PaidButUndelivered,
            self::QualityMismatch,
            self::ScopeCreep,
            self::MilestoneRejectedUnfairly,
            self::RefundAfterWorkStarted,
            self::SilenceComms,
            self::ContractViolation,
        ], true);
    }

    public function requiresSilenceDays(): bool
    {
        return in_array($this, [
            self::FreelancerUnresponsive,
            self::ClientNotResponding,
            self::SilenceComms,
        ], true);
    }

    /**
     * @return list<self>
     */
    public static function forParty(string $party, bool $includeLegacy = false): array
    {
        $party = strtolower($party);

        return array_values(array_filter(
            self::cases(),
            fn (self $r): bool => $r->allowedForParty($party) && ($includeLegacy || ! $r->isLegacy()),
        ));
    }

    /**
     * @return list<array{category: string, category_label: string, reasons: list<array{value: string, label: string}>}>
     */
    public static function groupedForParty(string $party): array
    {
        $groups = [];

        foreach (self::forParty($party) as $reason) {
            $category = $reason->category()->value;
            if (! isset($groups[$category])) {
                $groups[$category] = [
                    'category' => $category,
                    'category_label' => $reason->category()->label(),
                    'reasons' => [],
                ];
            }
            $groups[$category]['reasons'][] = [
                'value' => $reason->value,
                'label' => $reason->label(),
            ];
        }

        return array_values($groups);
    }

    public function allowedForParty(string $party): bool
    {
        return in_array(strtolower($party), $this->raisedByParties(), true);
    }
}
