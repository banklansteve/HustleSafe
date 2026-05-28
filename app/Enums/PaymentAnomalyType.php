<?php

namespace App\Enums;

enum PaymentAnomalyType: string
{
    case EscrowOverFunding = 'escrow_over_funding';
    case SmurfingPattern = 'smurfing_pattern';
    case PayoutVelocitySpike = 'payout_velocity_spike';
    case RapidEscrowRelease = 'rapid_escrow_release';
    case ContractMarketRateOutlier = 'contract_market_rate_outlier';

    public function label(): string
    {
        return match ($this) {
            self::EscrowOverFunding => 'Escrow over-funding',
            self::SmurfingPattern => 'Smurfing pattern',
            self::PayoutVelocitySpike => 'Payout velocity spike',
            self::RapidEscrowRelease => 'Rapid escrow release',
            self::ContractMarketRateOutlier => 'Contract vs market-rate outlier',
        };
    }
}
