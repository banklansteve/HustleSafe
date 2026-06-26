<?php

namespace App\Enums;

enum ContractPatrolFlagType: string
{
    case OverdueDelivery = 'overdue_delivery';
    case ActiveDispute = 'active_dispute';
    case PendingEscrowStale = 'pending_escrow_stale';
    case AmendmentPending = 'amendment_pending';
    case DeliveryAwaitingReview = 'delivery_awaiting_review';
    case FlaggedForReview = 'flagged_for_review';
    case EscrowHoldActive = 'escrow_hold_active';
    case OverdueDeliveryMedium = 'overdue_delivery_medium';
    case OverdueDeliveryCritical = 'overdue_delivery_critical';
    case RefundBeforeWorkStarts = 'refund_before_work_starts';
    case FreelancerInactiveAfterAward = 'freelancer_inactive_after_award';

    public function label(): string
    {
        return match ($this) {
            self::OverdueDelivery => 'Overdue delivery',
            self::ActiveDispute => 'Active dispute',
            self::PendingEscrowStale => 'Pending escrow (stale)',
            self::AmendmentPending => 'Amendment pending',
            self::DeliveryAwaitingReview => 'Delivery awaiting review',
            self::FlaggedForReview => 'Flagged for staff review',
            self::EscrowHoldActive => 'Escrow hold active',
            self::OverdueDeliveryMedium => 'Overdue delivery (24h+)',
            self::OverdueDeliveryCritical => 'Overdue delivery (72h+)',
            self::RefundBeforeWorkStarts => 'Refund requested before work starts',
            self::FreelancerInactiveAfterAward => 'Freelancer inactive after award',
        };
    }

    public function defaultSeverity(): string
    {
        return match ($this) {
            self::ActiveDispute, self::OverdueDelivery, self::OverdueDeliveryCritical => 'critical',
            self::PendingEscrowStale, self::DeliveryAwaitingReview, self::EscrowHoldActive,
            self::RefundBeforeWorkStarts => 'high',
            self::AmendmentPending, self::FlaggedForReview, self::OverdueDeliveryMedium,
            self::FreelancerInactiveAfterAward => 'medium',
        };
    }
}
