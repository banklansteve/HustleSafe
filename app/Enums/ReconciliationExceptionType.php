<?php

namespace App\Enums;

enum ReconciliationExceptionType: string
{
    case UnmatchedInboundPayment = 'unmatched_inbound_payment';
    case UnconfirmedEscrowFunding = 'unconfirmed_escrow_funding';
    case LedgerImbalance = 'ledger_imbalance';
    case EscrowPositionVariance = 'escrow_position_variance';

    public function label(): string
    {
        return match ($this) {
            self::UnmatchedInboundPayment => 'Unmatched Inbound Payment',
            self::UnconfirmedEscrowFunding => 'Unconfirmed Escrow Funding',
            self::LedgerImbalance => 'Ledger Imbalance',
            self::EscrowPositionVariance => 'Escrow Position Variance',
        };
    }
}
