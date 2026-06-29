<?php

namespace App\Enums;

enum QuestDisputeManagementStatus: string
{
    case Open = 'open';
    case PendingResponse = 'pending_response';
    case UnderReview = 'under_review';
    case ReadyForDecision = 'ready_for_decision';
    case AwaitingMutualApproval = 'awaiting_mutual_approval';
    case Mediation = 'mediation';
    case AwaitingEnforcement = 'awaiting_enforcement';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::PendingResponse => __('Pending response'),
            self::UnderReview => __('Under review'),
            self::ReadyForDecision => __('Ready for decision'),
            self::AwaitingMutualApproval => __('Awaiting mutual approval'),
            self::Mediation => __('Mediation'),
            self::AwaitingEnforcement => __('Awaiting enforcement'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed'),
            self::Finalized => __('Finalized'),
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Open => 'rose',
            self::PendingResponse => 'amber',
            self::UnderReview => 'orange',
            self::ReadyForDecision => 'violet',
            self::AwaitingMutualApproval => 'emerald',
            self::Mediation => 'orange',
            self::AwaitingEnforcement => 'amber',
            self::Resolved => 'sky',
            self::Closed => 'slate',
            self::Finalized => 'emerald',
        };
    }

    public function isStaffActive(): bool
    {
        return in_array($this, [
            self::Open,
            self::PendingResponse,
            self::UnderReview,
        ], true);
    }

    public function isSuperAdminActionable(): bool
    {
        return in_array($this, [
            self::ReadyForDecision,
            self::AwaitingMutualApproval,
            self::Mediation,
            self::AwaitingEnforcement,
            self::Resolved,
            self::Closed,
        ], true);
    }
}
