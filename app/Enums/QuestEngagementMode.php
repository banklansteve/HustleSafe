<?php

namespace App\Enums;

enum QuestEngagementMode: string
{
    case OneTime = 'one_time';
    case RecurringInstallment = 'recurring_installment';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => __('One-time job'),
            self::RecurringInstallment => __('Ongoing job — paid in installments'),
        };
    }
}
