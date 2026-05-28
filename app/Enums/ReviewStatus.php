<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case RevisionRequested = 'revision_requested';
    case AmendmentPending = 'amendment_pending';
    case Removed = 'removed';
    case Locked = 'locked';
}
