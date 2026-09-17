<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        return Sanctum::actingAs(User::factory()->admin()->create());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function adminEndpoints(): array
    {
        return [
            'stats' => ['get', '/api/v1/admin/stats'],
            'list categories' => ['get', '/api/v1/admin/categories'],
            'create category' => ['post', '/api/v1/admin/categories'],
            'list products' => ['get', '/api/v1/admin/products'],
            'create product' => ['post', '/api/v1/admin/products'],
            'list orders' => ['get', '/api/v1/admin/orders'],
        ];
    }

    #[DataProvider('adminEndpoints')]
    public function test_anonymous_users_get_401(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('adminEndpoints')]
    public function test_customers_get_403(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->json($method, $uri)
            ->assertForbidden()
            ->assertJsonPath('message', 'This action requires administrator privileges.');
    }

    // ---- Categories -----------------------------------------------------

    public function test_admin_can_create_a_category_with_a_derived_slug(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/categories', ['name' => 'Hot Drinks', 'description' => 'Coffee & tea'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'hot-drinks');

        $this->postJson('/api/v1/admin/categories', ['name' => 'Hot drinks'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_can_update_a_category_keeping_its_own_slug(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create(['name' => 'Pizza', 'slug' => 'pizza']);

        $this->putJson("/api/v1/admin/categories/{$category->id}", ['name' => 'Pizza', 'description' => 'Wood-fired'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Wood-fired');
    }

    public function test_admin_cannot_delete_a_category_that_still_has_products(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create(['name' => 'Pizza']);
        Product::factory()->count(2)->for($category)->create();

        $this->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cannot delete "Pizza" because it still has 2 products. Move or delete them first.');

        $this->assertModelExists($category);
    }

    public function test_admin_can_delete_an_empty_category(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $this->deleteJson("/api/v1/admin/categories/{$category->id}")->assertNoContent();
        $this->assertModelMissing($category);
    }

    // ---- Products ---------------------------------------------------------

    public function test_admin_product_list_includes_unavailable_products(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create();
        Product::factory()->unavailable()->create();

        $this->getJson('/api/v1/admin/products')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_update_and_delete_a_product(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $id = $this->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'Truffle Fries',
            'price' => 6.5,
            'image_url' => 'https://example.com/fries.jpg',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'truffle-fries')
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.category.id', $category->id)
            ->json('data.id');

        $this->putJson("/api/v1/admin/products/{$id}", [
            'category_id' => $category->id,
            'name' => 'Truffle Fries',
            'price' => '7.25',
            'is_available' => 'false',
        ])
            ->assertOk()
            ->assertJsonPath('data.price', 7.25)
            ->assertJsonPath('data.is_available', false);

        $this->deleteJson("/api/v1/admin/products/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }

    public function test_product_validation(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/products', [
            'category_id' => 999,
            'name' => '',
            'price' => 4.999,
            'image_url' => 'javascript:alert(1)',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'name', 'price', 'image_url']);
    }

    public function test_deleting_a_product_keeps_past_orders_intact(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create(['name' => 'Old Favourite']);
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Old Favourite',
            'unit_price' => 5, 'quantity' => 1, 'line_total' => 5,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertNoContent();

        $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_id', null)
            ->assertJsonPath('data.items.0.product_name', 'Old Favourite');
    }

    // ---- Orders -----------------------------------------------------------

    public function test_admin_sees_all_orders_and_can_filter_by_status(): void
    {
        $this->actingAsAdmin();
        Order::factory()->count(2)->create();
        Order::factory()->status(OrderStatus::Delivered)->create();

        $this->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(count(OrderStatus::cases()), 'statuses')
            ->assertJsonStructure(['data' => [['customer' => ['email'], 'items_count']]]);

        $this->getJson('/api/v1/admin/orders?status=delivered')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/admin/orders?status=teleported')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_can_search_orders_by_customer_email(): void
    {
        $this->actingAsAdmin();
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        Order::factory()->for($alice)->create();
        Order::factory()->create();

        $this->getJson('/api/v1/admin/orders?search=alice@')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.email', 'alice@example.com');
    }

    public function test_admin_advances_an_order_through_the_state_machine(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.allowed_transitions.0.value', 'preparing');

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_admin_cannot_make_an_invalid_status_jump(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->status(OrderStatus::Delivered)->create();

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'pending'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Cannot change an order from "Delivered" to "Pending".');

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    // ---- Dashboard --------------------------------------------------------

    public function test_stats_summarise_the_shop(): void
    {
        $this->actingAsAdmin();
        Product::factory()->count(3)->create();
        Product::factory()->unavailable()->create();
        Order::factory()->create(['total' => 10]);
        Order::factory()->status(OrderStatus::Delivered)->create(['total' => 25.50]);
        Order::factory()->status(OrderStatus::Delivered)->create(['total' => 4.50]);
        Order::factory()->status(OrderStatus::Cancelled)->create(['total' => 99]);

        $this->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.products_count', 4)
            ->assertJsonPath('data.available_products_count', 3)
            ->assertJsonPath('data.orders_count', 4)
            ->assertJsonPath('data.open_orders_count', 1)
            ->assertJsonPath('data.orders_by_status.delivered', 2)
            ->assertJsonPath('data.orders_by_status.preparing', 0)
            ->assertJsonPath('data.revenue', 30)
            ->assertJsonCount(4, 'data.recent_orders');
    }
}
