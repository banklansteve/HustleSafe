<?php

namespace App\Enums;

enum EscrowDeliveryStage: string
{
    case WorkInProgress = 'work_in_progress';
    case AwaitingReview = 'awaiting_review';
    case RevisionRequested = 'revision_requested';
    case ApprovedReleasing = 'approved_releasing';
    case CompletedPaid = 'completed_paid';

    public function label(): string
    {
        return match ($this) {
            self::WorkInProgress => __('Job in progress'),
            self::AwaitingReview => __('Waiting for you to check the work'),
            self::RevisionRequested => __('You asked for changes'),
            self::ApprovedReleasing => __('Work approved — paying out'),
            self::CompletedPaid => __('Done and paid'),
        };
    }
}
