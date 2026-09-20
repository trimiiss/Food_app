<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PromoCode;
use App\Support\Money;
use App\Support\PricedCart;
use Illuminate\Validation\ValidationException;

/**
 * Prices a cart: line prices (honouring offers), delivery fee and any promo
 * code discount.
 *
 * This is the only place money is worked out. The checkout and the cart
 * preview endpoint both call it, so what the customer is quoted and what they
 * are charged cannot drift apart. The client never sends prices — only
 * product ids, quantities and an optional code.
 */
class CartPricer
{
    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     *
     * @throws ValidationException when a product is unavailable or the code can't be used
     */
    public function price(array $items, ?string $promoCodeInput = null): PricedCart
    {
        $products = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotalCents = 0;

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);

            // Existence is validated by the Form Request; availability can change
            // between adding to the cart and checking out, so check it here.
            if (! $product || ! $product->is_available) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ($product?->name ?? 'A product').' is no longer available.',
                ]);
            }

            $unitCents = Money::toCents($product->effectivePrice());
            $lineCents = $unitCents * $item['quantity'];
            $subtotalCents += $lineCents;

            $lines[] = [
                'product_id' => $product->id,
                // Snapshot: later edits to the product must not alter this order.
                'product_name' => $product->name,
                'unit_price' => Money::fromCents($unitCents),
                'original_unit_price' => $product->isOnOffer() ? $product->price : null,
                'quantity' => $item['quantity'],
                'line_total' => Money::fromCents($lineCents),
            ];
        }

        $deliveryFeeCents = Money::toCents(config('shop.delivery_fee'));
        $promoCode = $this->resolvePromoCode($promoCodeInput, $subtotalCents);

        return new PricedCart(
            lines: $lines,
            subtotalCents: $subtotalCents,
            deliveryFeeCents: $deliveryFeeCents,
            discountCents: $promoCode?->discountCentsFor($subtotalCents, $deliveryFeeCents) ?? 0,
            promoCode: $promoCode,
        );
    }

    /**
     * @throws ValidationException
     */
    private function resolvePromoCode(?string $input, int $subtotalCents): ?PromoCode
    {
        if (blank($input)) {
            return null;
        }

        $promoCode = PromoCode::findByCode($input);

        if (! $promoCode) {
            throw ValidationException::withMessages([
                'promo_code' => 'This promo code does not exist.',
            ]);
        }

        // The model explains *why* it can't be used, so the UI can say so.
        if ($reason = $promoCode->rejectionReason($subtotalCents)) {
            throw ValidationException::withMessages(['promo_code' => $reason]);
        }

        return $promoCode;
    }
}
