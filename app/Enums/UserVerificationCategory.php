<?php

namespace App\Enums;

enum UserVerificationCategory: string
{
    case Identity = 'identity';
    case Address = 'address';
    case Qualification = 'qualification';

    /** Selfie holding an approved ID — required for high-value quest proposals after document ID is approved. */
    case LivePresence = 'live_presence';
}
