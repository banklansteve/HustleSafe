<?php

namespace App\Enums;

enum ReviewAuthenticityFlag: string
{
    case Clean = 'clean';
    case Suspicious = 'suspicious';
    case HighRisk = 'high_risk';

    public function label(): string
    {
        return match ($this) {
            self::Clean => 'Clean',
            self::Suspicious => 'Suspicious',
            self::HighRisk => 'High Risk',
        };
    }
}
