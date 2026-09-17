<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;

/**
 * A few past orders for the demo customer, in different statuses, so that
 * order history and admin order management aren't empty on first launch.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(OrderService $orders): void
    {
        $customer = User::firstWhere('email', UserSeeder::CUSTOMER_EMAIL);

        // Idempotent: only seed orders once.
        if (! $customer || $customer->orders()->exists()) {
            return;
        }

        $demo = [
            // [status, days ago, [product slug => quantity]]
            [OrderStatus::Delivered, 6, ['margherita' => 2, 'mint-lemonade' => 2]],
            [OrderStatus::Delivered, 3, ['double-bacon-smash' => 1, 'chocolate-fudge-brownie' => 1]],
            [OrderStatus::Preparing, 0, ['spaghetti-carbonara' => 1, 'chicken-caesar-salad' => 1, 'tiramisu' => 2]],
            [OrderStatus::Pending, 0, ['pepperoni' => 1, 'iced-latte' => 1]],
        ];

        foreach ($demo as [$status, $daysAgo, $lines]) {
            $products = Product::whereIn('slug', array_keys($lines))->pluck('id', 'slug');

            // Go through the real checkout path so totals and snapshots are
            // computed exactly as they would be for a customer.
            $order = $orders->place($customer, [
                'items' => collect($lines)
                    ->map(fn (int $quantity, string $slug) => ['product_id' => $products[$slug], 'quantity' => $quantity])
                    ->values()
                    ->all(),
                'delivery_address' => '221B Baker Street, London NW1 6XE',
                'contact_phone' => '+44 20 7946 0958',
                'notes' => $daysAgo === 0 ? 'Please ring the bell.' : null,
            ]);

            // Seeding shortcut: set the historical status/time directly rather than
            // replaying each transition. Runtime changes always go through OrderService.
            $placedAt = now()->subDays($daysAgo)->subHours(2);
            $order->forceFill([
                'status' => $status,
                // Keep the date embedded in the order number consistent with the back-dated timestamp.
                'order_number' => Order::generateOrderNumber($placedAt),
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ])->save();
        }
    }
}
