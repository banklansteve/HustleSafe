<?php

namespace App\Enums;

enum PremiumPatrolFlagType: string
{
    case NewAccountPremium = 'new_account_premium';
    case BulkPremiumFraud = 'bulk_premium_fraud';
    case InactivePremium = 'inactive_premium';
    case DisputeFraud = 'dispute_fraud';
    case LocationSpoofing = 'location_spoofing';
    case SuspiciousBudget = 'suspicious_budget';
    case BelowMarketValue = 'below_market_value';
    case ExcessiveBoost = 'excessive_boost';
    case NewAccountBoost = 'new_account_boost';
    case BoostSpam = 'boost_spam';
    case IneffectiveBoost = 'ineffective_boost';
    case ExtremePremium = 'extreme_premium';
    case BelowMarket = 'below_market';
    case ExcessiveBoostCost = 'excessive_boost_cost';
    case WatchlistPremium = 'watchlist_premium';
    case WatchlistBoostClient = 'watchlist_boost_client';

    public function label(): string
    {
        return match ($this) {
            self::NewAccountPremium => 'New Account Premium',
            self::BulkPremiumFraud => 'Bulk Premium Fraud',
            self::InactivePremium => 'Inactive Premium',
            self::DisputeFraud => 'Dispute Fraud',
            self::LocationSpoofing => 'Location Spoofing',
            self::SuspiciousBudget => 'Suspicious Budget',
            self::BelowMarketValue => 'Below Market Value',
            self::ExcessiveBoost => 'Excessive Boost',
            self::NewAccountBoost => 'New Account Boost',
            self::BoostSpam => 'Boost Spam',
            self::IneffectiveBoost => 'Ineffective Boost',
            self::ExtremePremium => 'Extreme Premium',
            self::BelowMarket => 'Below Market',
            self::ExcessiveBoostCost => 'Excessive Boost Cost',
            self::WatchlistPremium => 'Watchlist Premium',
            self::WatchlistBoostClient => 'Watchlist Boost Client',
        };
    }

    public function defaultSeverity(): string
    {
        return match ($this) {
            self::BulkPremiumFraud, self::DisputeFraud, self::SuspiciousBudget, self::BoostSpam => 'high',
            self::NewAccountPremium, self::NewAccountBoost, self::ExcessiveBoost, self::ExtremePremium, self::LocationSpoofing => 'medium',
            default => 'low',
        };
    }

    public function autoResolvable(): bool
    {
        return in_array($this, [self::InactivePremium, self::IneffectiveBoost], true);
    }
}
