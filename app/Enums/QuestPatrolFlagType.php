<?php

namespace App\Enums;

enum QuestPatrolFlagType: string
{
    case BudgetAnomalyHigh = 'budget_anomaly_high';
    case BudgetAnomalyLow = 'budget_anomaly_low';
    case TierMismatch = 'tier_mismatch';
    case BoostSpam = 'boost_spam';
    case DuplicateBoost = 'duplicate_boost';
    case RapidBoostAfterAward = 'rapid_boost_after_award';
    case InstantCompletion = 'instant_completion';
    case DuplicateQuest = 'duplicate_quest';
    case CategoryShift = 'category_shift';
    case NewAccountUnfamiliarCategory = 'new_account_unfamiliar_category';
    case LocationMismatch = 'location_mismatch';
    case PriceMismatch = 'price_mismatch';
    case ScopeMismatch = 'scope_mismatch';
    case VelocitySpike = 'velocity_spike';
    case TemplateSpam = 'template_spam';
    case WinRateAnomaly = 'win_rate_anomaly';
    case InstantAward = 'instant_award';
    case PriceAnomaly = 'price_anomaly';
    case SuspiciousEscrowRelease = 'suspicious_escrow_release';
    case RepeatCounterpartyTransactions = 'repeat_counterparty_transactions';
    case CircularPayment = 'circular_payment';

    public function label(): string
    {
        return match ($this) {
            self::BudgetAnomalyHigh => 'Budget Anomaly (High)',
            self::BudgetAnomalyLow => 'Budget Anomaly (Low)',
            self::TierMismatch => 'Tier Mismatch',
            self::BoostSpam => 'Boost Spam',
            self::DuplicateBoost => 'Duplicate Boost',
            self::RapidBoostAfterAward => 'Rapid Boost After Award',
            self::InstantCompletion => 'Instant Completion',
            self::DuplicateQuest => 'Duplicate Quest',
            self::CategoryShift => 'Category Shift',
            self::NewAccountUnfamiliarCategory => 'New Account, Unfamiliar Category',
            self::LocationMismatch => 'Location Mismatch',
            self::PriceMismatch => 'Price Mismatch',
            self::ScopeMismatch => 'Scope Mismatch',
            self::VelocitySpike => 'Velocity Spike',
            self::TemplateSpam => 'Template Spam',
            self::WinRateAnomaly => 'Win Rate Anomaly',
            self::InstantAward => 'Instant Award',
            self::PriceAnomaly => 'Price Anomaly',
            self::SuspiciousEscrowRelease => 'Suspicious Escrow Release',
            self::RepeatCounterpartyTransactions => 'Repeat Counterparty Transactions',
            self::CircularPayment => 'Circular Payment Pattern',
        };
    }

    public function defaultSeverity(): string
    {
        return match ($this) {
            self::BudgetAnomalyHigh, self::TierMismatch, self::InstantCompletion, self::WinRateAnomaly, self::InstantAward,
            self::SuspiciousEscrowRelease, self::CircularPayment => 'high',
            self::BoostSpam, self::DuplicateBoost, self::RapidBoostAfterAward, self::DuplicateQuest, self::CategoryShift,
            self::NewAccountUnfamiliarCategory, self::LocationMismatch, self::PriceMismatch, self::ScopeMismatch,
            self::VelocitySpike, self::TemplateSpam, self::PriceAnomaly, self::BudgetAnomalyLow,
            self::RepeatCounterpartyTransactions => 'medium',
        };
    }
}
