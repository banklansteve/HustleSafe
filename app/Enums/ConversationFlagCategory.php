<?php

namespace App\Enums;

enum ConversationFlagCategory: string
{
    case OffPlatformPayment = 'off_platform_payment';
    case ExternalContact = 'external_contact';
    case AbusiveLanguage = 'abusive_language';
    case BlacklistedKeyword = 'blacklisted_keyword';

    public function label(): string
    {
        return match ($this) {
            self::OffPlatformPayment => 'Off-platform payment',
            self::ExternalContact => 'External contact',
            self::AbusiveLanguage => 'Abusive / threatening',
            self::BlacklistedKeyword => 'Blacklisted keyword',
        };
    }
}
