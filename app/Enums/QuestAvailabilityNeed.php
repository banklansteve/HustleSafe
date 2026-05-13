<?php

namespace App\Enums;

enum QuestAvailabilityNeed: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case AsNeeded = 'as_needed';
}
