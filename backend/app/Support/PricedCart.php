<?php

namespace App\Support;

use App\Enums\FulfillmentType;
use App\Models\PromoCode;

/**
 * The result of pricing a cart: the priced lines and the money totals, all in
 * integer cents. Returned by CartPricer and consumed by both the checkout
 * (to persist an order) and the cart preview endpoint (to show the customer).
 */
final class PricedCart
{
    /**
     * @param  list<array{product_id: int, product_name: string, unit_price: string, original_unit_price: string|null, quantity: int, line_total: string}>  $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly int $subtotalCents,
        public readonly int $deliveryFeeCents,
        public readonly int $discountCents,
        public readonly FulfillmentType $fulfillment = FulfillmentType::Delivery,
        public readonly ?PromoCode $promoCode = null,
    ) {}

    public function totalCents(): int
    {
        return $this->subtotalCents + $this->deliveryFeeCents - $this->discountCents;
    }

    /** What the offers alone saved, before any promo code. */
    public function offerSavingsCents(): int
    {
        return array_reduce($this->lines, function (int $carry, array $line) {
            if ($line['original_unit_price'] === null) {
                return $carry;
            }

            $perUnit = Money::toCents($line['original_unit_price']) - Money::toCents($line['unit_price']);

            return $carry + ($perUnit * $line['quantity']);
        }, 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'quantity' => $line['quantity'],
                'unit_price' => (float) $line['unit_price'],
                'original_unit_price' => $line['original_unit_price'] === null ? null : (float) $line['original_unit_price'],
                'line_total' => (float) $line['line_total'],
            ], $this->lines),
            'fulfillment_type' => $this->fulfillment->value,
            'fulfillment_label' => $this->fulfillment->label(),
            'subtotal' => (float) Money::fromCents($this->subtotalCents),
            'delivery_fee' => (float) Money::fromCents($this->deliveryFeeCents),
            'discount_total' => (float) Money::fromCents($this->discountCents),
            'total' => (float) Money::fromCents($this->totalCents()),
            'offer_savings' => (float) Money::fromCents($this->offerSavingsCents()),
            'total_savings' => (float) Money::fromCents($this->offerSavingsCents() + $this->discountCents),
            'promo_code' => $this->promoCode === null ? null : [
                'code' => $this->promoCode->code,
                'description' => $this->promoCode->description,
                'type' => $this->promoCode->type->value,
                'summary' => $this->promoCode->summary(),
            ],
        ];
    }
}
