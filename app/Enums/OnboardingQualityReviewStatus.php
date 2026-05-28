<?php

namespace App\Enums;

enum OnboardingQualityReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Nudged = 'nudged';
    case Escalated = 'escalated';
    case SuspendedPendingReview = 'suspended_pending_review';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Nudged => 'Nudged',
            self::Escalated => 'Escalated',
            self::SuspendedPendingReview => 'Suspended (pending review)',
        };
    }

    public function blocksPosting(): bool
    {
        return in_array($this, [self::Escalated, self::SuspendedPendingReview], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
