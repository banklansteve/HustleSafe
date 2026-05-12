<?php

namespace App\Enums;

enum ReviewType: string
{
    /** Star rating + written feedback */
    case Full = 'full';
    /** Mutual withdrawal / cancellation — narrative only, no stars */
    case Partial = 'partial';
}
