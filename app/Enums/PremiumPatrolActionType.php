<?php

namespace App\Enums;

enum PremiumPatrolActionType: string
{
    case SuspendPremium = 'suspend_premium';
    case RefundPremium = 'refund_premium';
    case Investigate = 'investigate';
    case FlagManualReview = 'flag_manual_review';
    case AddWatchlist = 'add_watchlist';
    case GrantPremium = 'grant_premium';
    case DemoteBoost = 'demote_boost';
    case RefundBoost = 'refund_boost';
    case SuspendQuest = 'suspend_quest';
    case FlagSuspiciousClient = 'flag_suspicious_client';
    case RequestVerification = 'request_verification';
    case GrantBoost = 'grant_boost';
    case UnsuspendQuest = 'unsuspend_quest';

    public function label(): string
    {
        return match ($this) {
            self::SuspendPremium => 'Suspend premium',
            self::RefundPremium => 'Refund premium',
            self::Investigate => 'Open investigation',
            self::FlagManualReview => 'Flag for manual review',
            self::AddWatchlist => 'Add to watchlist',
            self::GrantPremium => 'Grant premium',
            self::DemoteBoost => 'Demote boost',
            self::RefundBoost => 'Refund boost',
            self::SuspendQuest => 'Suspend quest',
            self::FlagSuspiciousClient => 'Flag suspicious client',
            self::RequestVerification => 'Request verification',
            self::GrantBoost => 'Grant boost',
            self::UnsuspendQuest => 'Unsuspend quest',
        };
    }
}
