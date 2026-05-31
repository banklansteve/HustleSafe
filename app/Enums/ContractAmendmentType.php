<?php

namespace App\Enums;

enum ContractAmendmentType: string
{
    case Scope = 'scope';
    case Price = 'price';
    case DeliveryDate = 'delivery_date';

    public function label(): string
    {
        return match ($this) {
            self::Scope => 'Scope change',
            self::Price => 'Price adjustment',
            self::DeliveryDate => 'Delivery date extension',
        };
    }
}
