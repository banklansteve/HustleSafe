<?php

namespace App\Enums;

enum QuestInstallmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case AwaitingReview = 'awaiting_review';
    case RevisionRequested = 'revision_requested';
    case Approved = 'approved';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Upcoming'),
            self::Active => __('In progress'),
            self::AwaitingReview => __('Awaiting review'),
            self::RevisionRequested => __('Fixes requested'),
            self::Approved => __('Approved'),
            self::Released => __('Paid'),
        };
    }
}
