<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_price_and_percentage_are_exposed(): void
    {
        Product::factory()->onOffer(7.50)->create(['name' => 'Deal Pizza', 'price' => 10.00, 'slug' => 'deal-pizza']);

        $this->getJson('/api/v1/products/deal-pizza')
            ->assertOk()
            ->assertJsonPath('data.price', 10)
            ->assertJsonPath('data.effective_price', 7.5)
            ->assertJsonPath('data.is_on_offer', true)
            ->assertJsonPath('data.discount_percentage', 25);
    }

    public function test_expired_offer_is_ignored(): void
    {
        Product::factory()
            ->onOffer(4.00, now()->subHour()->toDateTimeString())
            ->create(['price' => 9.00, 'slug' => 'stale-deal']);

        $this->getJson('/api/v1/products/stale-deal')
            ->assertOk()
            ->assertJsonPath('data.effective_price', 9)
            ->assertJsonPath('data.is_on_offer', false)
            ->assertJsonPath('data.discount_percentage', null);
    }

    public function test_offer_without_end_date_keeps_running(): void
    {
        $product = Product::factory()->onOffer(5.00)->create(['price' => 8.00]);

        $this->assertTrue($product->isOnOffer());
        $this->assertSame('5.00', $product->effectivePrice());
    }

    public function test_products_can_be_filtered_to_offers_only(): void
    {
        Product::factory()->onOffer(6.00)->create(['name' => 'On Sale', 'price' => 12.00]);
        Product::factory()->onOffer(3.00, now()->subDay()->toDateTimeString())->create(['name' => 'Expired Sale', 'price' => 9.00]);
        Product::factory()->create(['name' => 'Full Price']);

        $this->getJson('/api/v1/products?on_offer=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'On Sale');

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_checkout_charges_the_offer_price_and_keeps_the_original(): void
    {
        config(['shop.delivery_fee' => 2.50]);
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->onOffer(7.50)->create(['name' => 'Deal Pizza', 'price' => 10.00]);

        // 2 x 7.50 = 15.00 (+2.50 delivery), not 2 x 10.00.
        $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'delivery_address' => '1 Offer Street, London',
            'contact_phone' => '+44 20 7946 0958',
        ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', 15)
            ->assertJsonPath('data.total', 17.5)
            ->assertJsonPath('data.items.0.unit_price', 7.5)
            ->assertJsonPath('data.items.0.original_unit_price', 10);
    }

    public function test_checkout_ignores_an_expired_offer(): void
    {
        config(['shop.delivery_fee' => 2.50]);
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()
            ->onOffer(2.00, now()->subMinute()->toDateTimeString())
            ->create(['price' => 9.00]);

        $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => '1 Offer Street, London',
            'contact_phone' => '+44 20 7946 0958',
        ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', 9)
            ->assertJsonPath('data.items.0.unit_price', 9)
            ->assertJsonPath('data.items.0.original_unit_price', null);
    }

    public function test_admin_can_put_a_product_on_offer(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['price' => 12.00]);

        $this->putJson("/api/v1/admin/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'price' => '12.00',
            'discount_price' => '9.00',
            'discount_ends_at' => now()->addWeek()->toDateTimeString(),
            'is_available' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_on_offer', true)
            ->assertJsonPath('data.effective_price', 9);
    }

    public function test_offer_price_must_be_below_the_normal_price(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['price' => 12.00]);

        $this->putJson("/api/v1/admin/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'price' => '12.00',
            'discount_price' => '15.00',
            'is_available' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.discount_price.0', 'The offer price field must be less than 12.00.');
    }

    public function test_clearing_the_offer_price_also_clears_the_end_date(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->onOffer(9.00, now()->addWeek()->toDateTimeString())->create(['price' => 12.00]);

        $this->putJson("/api/v1/admin/products/{$product->id}", [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'price' => '12.00',
            'discount_price' => '',
            'is_available' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_on_offer', false)
            ->assertJsonPath('data.discount_ends_at', null);

        $this->assertNull($product->fresh()->discount_price);
    }
}
