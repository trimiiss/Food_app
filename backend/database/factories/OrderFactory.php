<?php

namespace Database\Factories;

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
}
