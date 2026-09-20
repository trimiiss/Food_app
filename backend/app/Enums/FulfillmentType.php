<?php

namespace App\Enums;

/**
 * How the customer gets their food: a rider brings it, or they collect it from
 * the shop.
 *
 * It decides two things — whether the delivery fee is charged and whether a
 * delivery address is required — so CartPricer, the checkout Form Request and
 * the order pages all read the rules from here instead of testing for the
 * string 'pickup' in three different places.
 */
enum FulfillmentType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    /** Pickup orders are never charged the delivery fee. */
    public function chargesDeliveryFee(): bool
    {
        return $this === self::Delivery;
    }

    /** Only a delivery has somewhere to be delivered to. */
    public function needsDeliveryAddress(): bool
    {
        return $this === self::Delivery;
    }

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Pickup => 'Pickup',
        };
    }

    /**
     * Value + label pairs for the UI, so the SPA never keeps its own copy of
     * this enum.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
