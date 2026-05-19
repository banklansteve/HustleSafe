<?php

namespace App\Enums;

enum UserVerificationStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case InReview = 'in_review';
    case Flagged = 'flagged';
    case Approved = 'approved';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function isVerified(): bool
    {
        return in_array($this, [self::Approved, self::Verified], true);
    }
}
