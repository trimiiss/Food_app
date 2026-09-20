<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pickup vs delivery: who pays the delivery fee, what a customer has to tell
 * us, and how the order then reads back.
 */
class FulfillmentTest extends TestCase
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
        ], $overrides);
    }

    public function test_pickup_order_is_not_charged_the_delivery_fee(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['price' => 9.00]);

        $this->postJson('/api/v1/orders', $this->checkoutPayload(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['fulfillment_type' => 'pickup', 'delivery_address' => null],
        ))
            ->assertCreated()
            ->assertJsonPath('data.fulfillment_type', 'pickup')
            ->assertJsonPath('data.fulfillment_label', 'Pickup')
            ->assertJsonPath('data.subtotal', 18)
            ->assertJsonPath('data.delivery_fee', 0)
            ->assertJsonPath('data.total', 18)
            // Nothing to deliver to: the address is not kept.
            ->assertJsonPath('data.delivery_address', null);

        $this->assertDatabaseHas('orders', ['fulfillment_type' => 'pickup', 'delivery_fee' => 0, 'total' => 18.00]);
    }

    public function test_delivery_order_still_pays_the_fee(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['price' => 9.00]);

        $this->postJson('/api/v1/orders', $this->checkoutPayload(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['fulfillment_type' => 'delivery'],
        ))
            ->assertCreated()
            ->assertJsonPath('data.fulfillment_type', 'delivery')
            ->assertJsonPath('data.delivery_fee', 2.5)
            ->assertJsonPath('data.total', 20.5)
            ->assertJsonPath('data.delivery_address', '12 Baker Street, London');
    }

    public function test_an_order_without_a_fulfillment_type_is_still_a_delivery(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['price' => 9.00]);

        $this->postJson('/api/v1/orders', $this->checkoutPayload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.fulfillment_type', 'delivery')
            ->assertJsonPath('data.delivery_fee', 2.5);
    }

    public function test_delivery_requires_an_address_but_pickup_does_not(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();
        $items = [['product_id' => $product->id, 'quantity' => 1]];

        $this->postJson('/api/v1/orders', $this->checkoutPayload($items, [
            'fulfillment_type' => 'delivery',
            'delivery_address' => null,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('delivery_address');

        $this->postJson('/api/v1/orders', $this->checkoutPayload($items, [
            'fulfillment_type' => 'pickup',
            'delivery_address' => null,
        ]))->assertCreated();
    }

    public function test_an_address_sent_with_a_pickup_order_is_ignored(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->postJson('/api/v1/orders', $this->checkoutPayload(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['fulfillment_type' => 'pickup'],
        ))
            ->assertCreated()
            ->assertJsonPath('data.delivery_address', null);
    }

    public function test_unknown_fulfillment_type_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->postJson('/api/v1/orders', $this->checkoutPayload(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['fulfillment_type' => 'drone'],
        ))
            ->assertStatus(422)
            ->assertJsonValidationErrors('fulfillment_type');
    }

    public function test_cart_preview_drops_the_fee_for_pickup(): void
    {
        $product = Product::factory()->create(['price' => 12.00]);

        $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'fulfillment_type' => 'pickup',
        ])
            ->assertOk()
            ->assertJsonPath('data.fulfillment_type', 'pickup')
            ->assertJsonPath('data.delivery_fee', 0)
            ->assertJsonPath('data.total', 12);

        $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'fulfillment_type' => 'delivery',
        ])
            ->assertOk()
            ->assertJsonPath('data.delivery_fee', 2.5)
            ->assertJsonPath('data.total', 14.5);
    }

    public function test_free_delivery_code_is_rejected_on_a_pickup_order(): void
    {
        $product = Product::factory()->create(['price' => 12.00]);
        PromoCode::factory()->freeDelivery()->create(['code' => 'FREESHIP']);

        // There is no fee to waive, so the code is refused with a reason
        // instead of silently discounting nothing.
        $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'fulfillment_type' => 'pickup',
            'promo_code' => 'FREESHIP',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.promo_code.0', 'This code only applies to delivery orders.');

        $this->postJson('/api/v1/cart/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'fulfillment_type' => 'delivery',
            'promo_code' => 'FREESHIP',
        ])
            ->assertOk()
            ->assertJsonPath('data.discount_total', 2.5);
    }

    public function test_a_percentage_code_still_works_on_a_pickup_order(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['price' => 20.00]);
        PromoCode::factory()->percent(10)->create(['code' => 'WELCOME10']);

        $this->postJson('/api/v1/orders', $this->checkoutPayload(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['fulfillment_type' => 'pickup', 'promo_code' => 'WELCOME10'],
        ))
            ->assertCreated()
            ->assertJsonPath('data.discount_total', 2)
            ->assertJsonPath('data.delivery_fee', 0)
            ->assertJsonPath('data.total', 18);
    }

    public function test_pickup_orders_are_labelled_for_collection(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->pickup()->for($user)->status(OrderStatus::Preparing)->create();

        // "Out for delivery" makes no sense for an order the customer collects.
        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status_label', 'Preparing')
            ->assertJsonPath('data.allowed_transitions.0.value', 'out_for_delivery')
            ->assertJsonPath('data.allowed_transitions.0.label', 'Ready for pickup');

        $delivery = Order::factory()->for($user)->status(OrderStatus::Preparing)->create();

        $this->getJson("/api/v1/orders/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.allowed_transitions.0.label', 'Out for delivery');
    }

    public function test_admin_can_filter_orders_by_fulfillment_type(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        Order::factory()->count(2)->pickup()->create();
        Order::factory()->create();

        $this->getJson('/api/v1/admin/orders?fulfillment_type=pickup')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.fulfillment_type', 'pickup')
            ->assertJsonPath('fulfillment_types.1.label', 'Pickup');

        $this->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
