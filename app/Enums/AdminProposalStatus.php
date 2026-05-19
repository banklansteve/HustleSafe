<?php

namespace App\Enums;

enum AdminProposalStatus: string
{
    case Clear = 'clear';
    case Flagged = 'flagged';
    case UnderReview = 'under_review';
    case Referred = 'referred';
    case ActionRequired = 'action_required';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Clear => 'Clear',
            self::Flagged => 'Flagged',
            self::UnderReview => 'Under Review',
            self::Referred => 'Referred',
            self::ActionRequired => 'Action Required',
            self::Restricted => 'Restricted',
            self::Suspended => 'Suspended',
            self::Resolved => 'Resolved',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Clear => 'gray',
            self::Flagged => 'orange',
            self::UnderReview => 'indigo',
            self::Referred => 'purple',
            self::ActionRequired => 'amber',
            self::Restricted => 'yellow',
            self::Suspended => 'dark_red',
            self::Resolved => 'green',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
