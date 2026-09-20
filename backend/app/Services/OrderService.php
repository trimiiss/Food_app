<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Order domain operations shared by the customer and admin controllers.
 *
 * Keeping checkout and status changes here (rather than in controllers) gives
 * one place that enforces pricing rules and the OrderStatus state machine.
 */
class OrderService
{
    /**
     * Turn a client-side cart into a persisted order.
     *
     * The client only sends product ids and quantities. Prices are always read
     * from the database: whatever the browser thinks something costs is ignored.
     *
     * @param  array{
     *     items: list<array{product_id: int, quantity: int}>,
     *     delivery_address: string,
     *     contact_phone: string,
     *     notes?: string|null
     * }  $data
     */
    public function place(User $user, array $data): Order
    {
        $products = Product::query()
            ->whereIn('id', array_column($data['items'], 'product_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotalCents = 0;

        foreach ($data['items'] as $index => $item) {
            $product = $products->get($item['product_id']);

            // Existence is validated by the Form Request; availability can change
            // between adding to cart and checking out, so check it here.
            if (! $product || ! $product->is_available) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ($product?->name ?? 'A product').' is no longer available.',
                ]);
            }

            // Charge the offer price when one is running; Money keeps this in
            // integer cents so float addition never touches money.
            $unitCents = Money::toCents($product->effectivePrice());
            $lineCents = $unitCents * $item['quantity'];
            $subtotalCents += $lineCents;

            $lines[] = [
                'product_id' => $product->id,
                // Snapshot: later edits to the product must not alter this order.
                'product_name' => $product->name,
                'unit_price' => Money::fromCents($unitCents),
                // Keeps "was EUR x.xx" on the receipt after the offer ends.
                'original_unit_price' => $product->isOnOffer() ? $product->price : null,
                'quantity' => $item['quantity'],
                'line_total' => Money::fromCents($lineCents),
            ];
        }

        $deliveryFeeCents = Money::toCents(config('shop.delivery_fee'));

        // All-or-nothing: an order without its items must never exist.
        return DB::transaction(function () use ($user, $data, $lines, $subtotalCents, $deliveryFeeCents) {
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => Order::generateOrderNumber(),
                'status' => OrderStatus::Pending,
                'subtotal' => Money::fromCents($subtotalCents),
                'delivery_fee' => Money::fromCents($deliveryFeeCents),
                'total' => Money::fromCents($subtotalCents + $deliveryFeeCents),
                'delivery_address' => $data['delivery_address'],
                'contact_phone' => $data['contact_phone'],
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->createMany($lines);

            return $order->load('items');
        });
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
