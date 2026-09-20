<?php

namespace Database\Factories;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 60);
        $deliveryFee = 2.50;

        return [
            'user_id' => User::factory(),
            'order_number' => Order::generateOrderNumber(),
            'status' => OrderStatus::Pending,
            'fulfillment_type' => FulfillmentType::Delivery,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $subtotal + $deliveryFee,
            'delivery_address' => fake()->streetAddress().', '.fake()->city(),
            'contact_phone' => fake()->phoneNumber(),
            'notes' => null,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /** Collected in store: no fee, no address. */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'fulfillment_type' => FulfillmentType::Pickup,
            'delivery_fee' => 0,
            'total' => $attributes['subtotal'],
            'delivery_address' => null,
        ]);
    }
}
