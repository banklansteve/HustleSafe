<?php

namespace App\Enums;

enum DisputeResolutionOption: string
{
    case AwardClientFull = 'award_client_full';
    case AwardFreelancerFull = 'award_freelancer_full';
    case SplitFund = 'split_fund';
    case ForceRevision = 'force_revision';
    case ExtendDeadline = 'extend_deadline';
    case ReviseRedo = 'revise_redo';
    case ExtendDelivery = 'extend_delivery';
    case AdjustTimeline = 'adjust_timeline';
    case ScopeAdjustment = 'scope_adjustment';
    case Other = 'other';
    case Mediation = 'mediation';
    case CustomSettlement = 'custom_settlement';
    case RefundCancel = 'refund_cancel';
    case PaymentHold = 'payment_hold';
    case BindingArbitration = 'binding_arbitration';

    public function label(): string
    {
        return match ($this) {
            self::AwardClientFull => __('Full refund to client'),
            self::AwardFreelancerFull => __('Full payment to freelancer'),
            self::SplitFund => __('Split the payment'),
            self::ForceRevision => __('Ask Customer Support to order a fix'),
            self::ExtendDeadline => __('Ask Customer Support for more time'),
            self::ReviseRedo => __('Freelancer will revise, redo, or repair'),
            self::ExtendDelivery => __('Agree to extend the delivery date'),
            self::AdjustTimeline => __('Agree a new completion timeline'),
            self::ScopeAdjustment => __('Agree changes to deliverables or scope'),
            self::Other => __('Other agreement'),
            self::Mediation => __('Talk with a mediator'),
            self::CustomSettlement => __('Custom agreement between both parties'),
            self::RefundCancel => __('Refund client and cancel the job'),
            self::PaymentHold => __('Hold payment during investigation'),
            self::BindingArbitration => __('Final binding decision'),
        };
    }

    public function partyHint(): string
    {
        return match ($this) {
            self::AwardClientFull => __('Ask Customer Support to refund you fully because the work was not usable or not delivered.'),
            self::AwardFreelancerFull => __('Ask Customer Support to release full payment because you met the agreement.'),
            self::SplitFund => __('Propose how much the client keeps and how much the freelancer gets.'),
            self::ForceRevision => __('Ask Customer Support to give the freelancer another chance to fix the work.'),
            self::ExtendDeadline => __('Ask Customer Support to approve extra days when you cannot meet the current deadline.'),
            self::ReviseRedo => __('Both agree the freelancer will fix, redo, or repair the work — payment can stay in escrow meanwhile.'),
            self::ExtendDelivery => __('Both agree to push the delivery date forward by a set number of days.'),
            self::AdjustTimeline => __('Both agree on new milestones or a new finish date — not necessarily about money.'),
            self::ScopeAdjustment => __('Both agree to add, remove, or change deliverables after the original contract.'),
            self::Other => __('Describe any other deal you both accept — be specific so Customer Support can record it.'),
            self::Mediation => __('Ask for a guided call so both sides can agree on a fair outcome.'),
            self::CustomSettlement => __('Describe a creative deal you and the other party can both accept.'),
            self::RefundCancel => __('Both parties agree to end the job and refund the client.'),
            self::PaymentHold => __('Payment paused while fraud or chargeback is investigated.'),
            self::BindingArbitration => __('Customer Support makes a final decision after all other steps fail.'),
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::AwardClientFull, self::AwardFreelancerFull, self::SplitFund, self::RefundCancel => 'payment',
            self::ForceRevision, self::ExtendDeadline, self::ReviseRedo, self::ExtendDelivery, self::AdjustTimeline, self::ScopeAdjustment => 'delivery',
            self::Mediation, self::CustomSettlement, self::Other => 'negotiation',
            self::PaymentHold, self::BindingArbitration => 'special',
        };
    }

    public function defaultClientSharePercent(): ?int
    {
        return match ($this) {
            self::AwardClientFull, self::RefundCancel => 100,
            self::AwardFreelancerFull => 0,
            self::SplitFund => 50,
            default => null,
        };
    }

    public function requiresClientShare(): bool
    {
        return $this === self::SplitFund;
    }

    public function requiresDays(): bool
    {
        return in_array($this, [self::ExtendDeadline, self::ExtendDelivery], true);
    }

    public function optionalRevisionDays(): bool
    {
        return $this === self::ReviseRedo;
    }

    public function requiresTargetDate(): bool
    {
        return $this === self::AdjustTimeline;
    }

    public function requiresTermsNote(): bool
    {
        return in_array($this, [
            self::CustomSettlement,
            self::Mediation,
            self::ForceRevision,
            self::ExtendDeadline,
            self::ReviseRedo,
            self::ExtendDelivery,
            self::AdjustTimeline,
            self::ScopeAdjustment,
            self::Other,
        ], true);
    }

    public function isMutual(): bool
    {
        return in_array($this, [
            self::CustomSettlement,
            self::RefundCancel,
            self::SplitFund,
            self::ReviseRedo,
            self::ExtendDelivery,
            self::AdjustTimeline,
            self::ScopeAdjustment,
            self::Other,
        ], true);
    }
}
