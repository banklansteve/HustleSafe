<?php

namespace App\Enums;

enum PortfolioStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case RevisionRequested = 'revision_requested';
    case Removed = 'removed';
}
