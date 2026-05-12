<?php

namespace App\Enums;

enum UserVerificationStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
