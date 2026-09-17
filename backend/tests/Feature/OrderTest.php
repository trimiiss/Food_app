<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.delivery_fee' => 2.50]);
    }

    private function checkoutPayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'items' => $items,
            'delivery_address' => '12 Baker Street, London',
            'contact_phone' => '+44 20 7946 0958',
            'notes' => 'Ring twice',
        ], $overrides);
    }

    public function test_guest_cannot_place_an_order(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/v1/orders', $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertUnauthorized();
    }

    public function test_customer_can_place_an_order_priced_from_the_database(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $pizza = Product::factory()->create(['name' => 'Margherita', 'price' => 8.50]);
        $cola = Product::factory()->create(['name' => 'Cola', 'price' => 1.10]);

        $response = $this->postJson('/api/v1/orders', $this->checkoutPayload([
            // A client-supplied price must be ignored entirely.
            ['product_id' => $pizza->id, 'quantity' => 2, 'price' => 0.01],
            ['product_id' => $cola->id, 'quantity' => 3],
        ]));

        // 2 x 8.50 + 3 x 1.10 = 20.30 subtotal (exact, thanks to integer cents) + 2.50 fee.
        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', 20.3)
            ->assertJsonPath('data.delivery_fee', 2.5)
            ->assertJsonPath('data.total', 22.8)
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'Margherita')
            ->assertJsonPath('data.items.0.unit_price', 8.5)
            ->assertJsonPath('data.items.0.line_total', 17);

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $response->json('data.order_number'));
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => 22.80]);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_order_keeps_its_price_after_the_product_is_edited(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Burger', 'price' => 9.00]);

        $orderId = $this->postJson('/api/v1/orders', $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->json('data.id');

        $product->update(['name' => 'Deluxe Burger', 'price' => 15.00]);

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertJsonPath('data.items.0.product_name', 'Burger')
            ->assertJsonPath('data.items.0.unit_price', 9)
            ->assertJsonPath('data.subtotal', 9);
    }

    public function test_checkout_rejects_unavailable_products(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $available = Product::factory()->create();
        $soldOut = Product::factory()->unavailable()->create(['name' => 'Seasonal Soup']);

        $response = $this->postJson('/api/v1/orders', $this->checkoutPayload([
            ['product_id' => $available->id, 'quantity' => 1],
            ['product_id' => $soldOut->id, 'quantity' => 1],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.product_id']);

        // Error keys contain literal dots, so read them directly rather than via a dot path.
        $this->assertSame('Seasonal Soup is no longer available.', $response->json('errors')['items.1.product_id'][0]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_validates_the_cart_and_delivery_details(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->postJson('/api/v1/orders', $this->checkoutPayload([], [
            'delivery_address' => '',
            'contact_phone' => 'call me maybe',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items', 'delivery_address', 'contact_phone']);

        $this->postJson('/api/v1/orders', $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 0],
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => 999999, 'quantity' => 1],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity', 'items.0.product_id', 'items.2.product_id']);
    }

    public function test_customer_only_sees_their_own_orders_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Order::factory()->for($user)->create();
        $newer = Order::factory()->for($user)->create();
        Order::factory()->create(); // another customer's order

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_customer_cannot_view_or_cancel_another_customers_order(): void
    {
        $someoneElses = Order::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/orders/{$someoneElses->id}")->assertNotFound();
        $this->postJson("/api/v1/orders/{$someoneElses->id}/cancel")->assertNotFound();
        $this->assertSame(OrderStatus::Pending, $someoneElses->fresh()->status);
    }

    public function test_customer_can_cancel_a_pending_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.allowed_transitions', []);
    }

    public function test_customer_cannot_cancel_once_preparation_has_started(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->status(OrderStatus::Preparing)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }
}
