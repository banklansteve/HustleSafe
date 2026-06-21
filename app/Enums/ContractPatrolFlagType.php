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
        };
    }

    public function defaultSeverity(): string
    {
        return match ($this) {
            self::ActiveDispute, self::OverdueDelivery => 'critical',
            self::PendingEscrowStale, self::DeliveryAwaitingReview, self::EscrowHoldActive => 'high',
            self::AmendmentPending, self::FlaggedForReview => 'medium',
        };
    }
}
