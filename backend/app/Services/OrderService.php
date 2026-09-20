<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Order domain operations shared by the customer and admin controllers.
 *
 * Keeping checkout and status changes here (rather than in controllers) gives
 * one place that enforces the OrderStatus state machine; pricing itself lives
 * in CartPricer, which the cart preview endpoint uses as well.
 */
class OrderService
{
    public function __construct(private readonly CartPricer $pricer) {}

    /**
     * Turn a client-side cart into a persisted order.
     *
     * The client only sends product ids, quantities and an optional promo code.
     * Prices are always read from the database: whatever the browser thinks
     * something costs is ignored.
     *
     * @param  array{
     *     items: list<array{product_id: int, quantity: int}>,
     *     fulfillment_type?: string|null,
     *     delivery_address?: string|null,
     *     contact_phone: string,
     *     notes?: string|null,
     *     promo_code?: string|null
     * }  $data
     */
    public function place(User $user, array $data): Order
    {
        // Pickup or delivery decides both the fee and whether an address is kept.
        $fulfillment = FulfillmentType::tryFrom((string) ($data['fulfillment_type'] ?? ''))
            ?? FulfillmentType::Delivery;

        $cart = $this->pricer->price($data['items'], $data['promo_code'] ?? null, $fulfillment);

        // All-or-nothing: an order without its items, or a redemption counted
        // for an order that was never created, must never happen.
        return DB::transaction(function () use ($user, $data, $cart, $fulfillment) {
            if ($cart->promoCode) {
                $this->redeem($cart->promoCode);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => Order::generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'fulfillment_type' => $fulfillment,
                'subtotal' => Money::fromCents($cart->subtotalCents),
                'delivery_fee' => Money::fromCents($cart->deliveryFeeCents),
                'promo_code' => $cart->promoCode?->code,
                'discount_total' => Money::fromCents($cart->discountCents),
                'total' => Money::fromCents($cart->totalCents()),
                'delivery_address' => $fulfillment->needsDeliveryAddress()
                    ? $data['delivery_address']
                    : null,
                'contact_phone' => $data['contact_phone'],
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->createMany($cart->lines);

            return $order->load('items');
        });
    }

    /**
     * Count one redemption, re-checking the limit under a row lock: two
     * customers could reach the last available use at the same moment.
     */
    private function redeem(PromoCode $promoCode): void
    {
        $locked = PromoCode::query()->lockForUpdate()->find($promoCode->id);

        if (! $locked || $locked->isFullyRedeemed()) {
            throw ValidationException::withMessages([
                'promo_code' => 'This promo code has been fully redeemed.',
            ]);
        }

        $locked->increment('uses_count');
    }

    /**
     * Move an order to a new status, enforcing the OrderStatus state machine.
     *
     * The row is locked for the duration so two admins clicking at the same
     * moment can't both apply a transition from the same starting state.
     */
    public function transition(Order $order, OrderStatus $next): Order
    {
        return DB::transaction(function () use ($order, $next) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->status->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot change an order from \"{$locked->status->label()}\" to \"{$next->label()}\".",
                ]);
            }

            $locked->update(['status' => $next]);

            return $locked;
        });
    }

    /**
     * Customer-initiated cancellation: only allowed before preparation starts.
     */
    public function cancelByCustomer(Order $order): Order
    {
        if (! $order->status->isCancellableByCustomer()) {
            throw ValidationException::withMessages([
                'status' => 'This order is already being prepared and can no longer be cancelled.',
            ]);
        }

        return $this->transition($order, OrderStatus::Cancelled);
    }
}
