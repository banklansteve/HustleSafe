<?php

namespace App\Enums;

enum QuestDisputePhase: string
{
    case SelfResolution = 'self_resolution';
    case FormalReview = 'formal_review';
    case Appeal = 'appeal';
    case Closed = 'closed';
}
