<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_settings_are_public(): void
    {
        config([
            'shop.currency' => 'EUR',
            'shop.delivery_fee' => 2.5,
            'shop.max_item_quantity' => 50,
            'shop.pickup.address' => 'LeuEats Kitchen',
            'shop.pickup.ready_in_minutes' => 20,
        ]);

        $this->getJson('/api/v1/shop')
            ->assertOk()
            ->assertExactJson(['data' => [
                'currency' => 'EUR',
                'delivery_fee' => 2.5,
                'max_item_quantity' => 50,
                // The SPA builds its delivery/pickup choice from this.
                'fulfillment_types' => [
                    ['value' => 'delivery', 'label' => 'Delivery'],
                    ['value' => 'pickup', 'label' => 'Pickup'],
                ],
                'pickup_address' => 'LeuEats Kitchen',
                'pickup_ready_in_minutes' => 20,
            ]]);
    }

    public function test_categories_list_counts_only_available_products(): void
    {
        $pizza = Category::factory()->create(['name' => 'Pizza', 'slug' => 'pizza']);
        Product::factory()->count(2)->for($pizza)->create();
        Product::factory()->unavailable()->for($pizza)->create();

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'pizza')
            ->assertJsonPath('data.0.products_count', 2);
    }

    public function test_products_list_hides_unavailable_products(): void
    {
        Product::factory()->create(['name' => 'Margherita']);
        Product::factory()->unavailable()->create(['name' => 'Sold Out Special']);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Margherita')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'price', 'category' => ['slug']]], 'meta', 'links']);
    }

    public function test_products_can_be_filtered_by_category_slug(): void
    {
        $pizza = Category::factory()->create(['slug' => 'pizza']);
        $drinks = Category::factory()->create(['slug' => 'drinks']);
        Product::factory()->count(3)->for($pizza)->create();
        Product::factory()->count(2)->for($drinks)->create();

        $this->getJson('/api/v1/products?category=drinks')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category.slug', 'drinks');
    }

    public function test_products_can_be_searched_by_name(): void
    {
        Product::factory()->create(['name' => 'Pepperoni Pizza']);
        Product::factory()->create(['name' => 'Caesar Salad']);

        $this->getJson('/api/v1/products?search=pepper')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pepperoni Pizza');
    }

    public function test_price_is_returned_as_a_number(): void
    {
        Product::factory()->create(['price' => 8.5]);

        $this->assertSame(8.5, $this->getJson('/api/v1/products')->json('data.0.price'));
    }

    public function test_invalid_listing_query_is_rejected(): void
    {
        $this->getJson('/api/v1/products?per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_product_can_be_fetched_by_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'margherita']);

        $this->getJson('/api/v1/products/margherita')
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_unavailable_or_missing_product_returns_clean_404(): void
    {
        Product::factory()->unavailable()->create(['slug' => 'hidden']);

        $this->getJson('/api/v1/products/hidden')->assertNotFound();
        $this->getJson('/api/v1/products/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Product not found.']);
    }
}
