<?php

namespace App\Enums;

enum QuestStartTiming: string
{
    case Urgent48h = 'urgent_48h';
    case ThisWeek = 'this_week';
    case NextTwoWeeks = 'next_two_weeks';
    case Flexible = 'flexible';
    case Scheduled = 'scheduled';
    /** Browsing / planning — no firm start yet */
    case WindowShopping = 'window_shopping';
}
