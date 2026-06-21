<?php

namespace App\Enums;

enum QuestInstallmentFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => __('Weekly'),
            self::Monthly => __('Monthly'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ])->all();
    }
}
