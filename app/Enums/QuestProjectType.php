<?php

namespace App\Enums;

enum QuestProjectType: string
{
    case FixedPrice = 'fixed_price';
    case Hourly = 'hourly';
}
