<?php

namespace App\Enums;

enum UserActivityRiskLevel: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 75 => self::Critical,
            $score >= 50 => self::High,
            $score >= 30 => self::Medium,
            default => self::Low,
        };
    }
}
