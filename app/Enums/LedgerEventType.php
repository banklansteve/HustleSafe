<?php

namespace App\Enums;

enum LedgerEventType: string
{
    case EscrowFunded = 'escrow_funded';
    case EscrowReleased = 'escrow_released';
    case DisputeRefund = 'dispute_refund';
    case WithdrawalInitiated = 'withdrawal_initiated';
    case WithdrawalConfirmed = 'withdrawal_confirmed';
    case WithdrawalReversed = 'withdrawal_reversed';
    case FeeRecognised = 'fee_recognised';
    case VatAccrued = 'vat_accrued';
    case GatewayUnmatched = 'gateway_unmatched';
    case Reversal = 'reversal';
    case BoostInvestment = 'boost_investment';
    case FreelancerProSubscription = 'freelancer_pro_subscription';

    public function label(): string
    {
        return str_replace('_', ' ', ucfirst($this->value));
    }
}
