<?php

namespace App\Enums;

enum PromoCodeType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeDelivery = 'free_delivery';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage off',
            self::Fixed => 'Amount off',
            self::FreeDelivery => 'Free delivery',
        };
    }

    /** Does `value` mean anything for this type? */
    public function usesValue(): bool
    {
        return $this !== self::FreeDelivery;
    }
}
