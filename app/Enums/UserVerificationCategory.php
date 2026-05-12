<?php

namespace App\Enums;

enum UserVerificationCategory: string
{
    case Identity = 'identity';
    case Address = 'address';
    case Qualification = 'qualification';
}
