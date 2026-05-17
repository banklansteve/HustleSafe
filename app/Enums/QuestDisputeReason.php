<?php

namespace App\Enums;

/**
 * Who may raise each reason (client / freelancer / either).
 */
enum QuestDisputeReason: string
{
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

    /**
     * @return list<string> client|freelancer|either
     */
    public function raisedByParties(): array
    {
        return match ($this) {
            self::ClientWontApproveDelivery => ['freelancer'],
            self::PaidButUndelivered, self::QualityMismatch, self::RefundAfterWorkStarted => ['client'],
            self::ScopeCreep, self::MilestoneRejectedUnfairly => ['freelancer'],
            self::SilenceComms, self::ContractViolation => ['client', 'freelancer'],
        };
    }

    /**
     * @return list<self>
     */
    public static function forParty(string $party): array
    {
        $party = strtolower($party);

        return array_values(array_filter(self::cases(), fn (self $r): bool => $r->allowedForParty($party)));
    }

    public function allowedForParty(string $party): bool
    {
        return in_array(strtolower($party), $this->raisedByParties(), true);
    }
}
