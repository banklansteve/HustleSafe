<?php

namespace App\Enums;

enum QuestPatrolFlagStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Resolved = 'resolved';
}
