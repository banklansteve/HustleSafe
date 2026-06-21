<?php

namespace App\Enums;

enum DeliveryDateAdjustmentType: string
{
    case Extension = 'extension';
    case Reduction = 'reduction';

    public function label(): string
    {
        return match ($this) {
            self::Extension => __('Need more time'),
            self::Reduction => __('Can finish sooner'),
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
