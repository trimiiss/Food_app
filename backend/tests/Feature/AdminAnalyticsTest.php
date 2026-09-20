<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = [], array $items = []): Order
    {
        $order = Order::factory()->create($attributes);

        foreach ($items as $name => [$quantity, $unitPrice]) {
            $order->items()->create([
                'product_name' => $name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ]);
        }

        return $order;
    }

    public function test_analytics_are_admin_only(): void
    {
        $this->getJson('/api/v1/admin/analytics')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/analytics')->assertForbidden();
    }

    public function test_totals_count_every_order_except_cancellations(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->order(['total' => 30.00, 'status' => OrderStatus::Delivered]);
        // Still cooking, but the money is real.
        $this->order(['total' => 10.00, 'status' => OrderStatus::Preparing]);
        $this->order(['total' => 99.00, 'status' => OrderStatus::Cancelled]);

        $this->getJson('/api/v1/admin/analytics?days=7')
            ->assertOk()
            ->assertJsonPath('data.totals.orders', 2)
            ->assertJsonPath('data.totals.sales', 40)
            ->assertJsonPath('data.totals.average_order_value', 20)
            ->assertJsonPath('data.totals.cancelled_orders', 1)
            ->assertJsonPath('data.range.days', 7);
    }

    public function test_sales_by_day_covers_every_day_in_the_range(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->order(['total' => 25.00, 'created_at' => now()]);
        $this->order(['total' => 15.00, 'created_at' => now()->subDays(2)]);

        $response = $this->getJson('/api/v1/admin/analytics?days=7')->assertOk();

        // Seven days, quiet ones included, oldest first — a chart needs the gaps.
        $days = $response->json('data.sales_by_day');
        $this->assertCount(7, $days);
        $this->assertSame(now()->subDays(6)->toDateString(), $days[0]['date']);
        $this->assertSame(now()->toDateString(), $days[6]['date']);
        $this->assertEqualsWithDelta(25.0, $days[6]['sales'], 0.001);
        $this->assertEqualsWithDelta(15.0, $days[4]['sales'], 0.001);
        $this->assertEqualsWithDelta(0.0, $days[5]['sales'], 0.001);
        $this->assertSame(0, $days[5]['orders']);
    }

    public function test_orders_outside_the_window_are_left_out(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->order(['total' => 20.00, 'created_at' => now()]);
        $this->order(['total' => 500.00, 'created_at' => now()->subDays(20)]);

        $this->getJson('/api/v1/admin/analytics?days=7')
            ->assertOk()
            ->assertJsonPath('data.totals.sales', 20);

        $this->getJson('/api/v1/admin/analytics?days=30')
            ->assertOk()
            ->assertJsonPath('data.totals.sales', 520);
    }

    public function test_top_products_rank_by_quantity_sold(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->order([], ['Margherita' => [2, 8.00], 'Cola' => [1, 2.00]]);
        $this->order([], ['Margherita' => [3, 8.00]]);
        // A cancelled order never sold anything.
        $this->order(['status' => OrderStatus::Cancelled], ['Cola' => [50, 2.00]]);

        $this->getJson('/api/v1/admin/analytics')
            ->assertOk()
            ->assertJsonPath('data.top_products.0.name', 'Margherita')
            ->assertJsonPath('data.top_products.0.quantity', 5)
            ->assertJsonPath('data.top_products.0.sales', 40)
            ->assertJsonPath('data.top_products.1.name', 'Cola')
            ->assertJsonPath('data.top_products.1.quantity', 1);
    }

    public function test_promo_code_performance_is_grouped_by_code(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->order(['promo_code' => 'WELCOME10', 'discount_total' => 2.00, 'total' => 18.00]);
        $this->order(['promo_code' => 'WELCOME10', 'discount_total' => 3.00, 'total' => 27.00]);
        $this->order(['promo_code' => null, 'total' => 10.00]);

        $this->getJson('/api/v1/admin/analytics')
            ->assertOk()
            ->assertJsonCount(1, 'data.promo_codes')
            ->assertJsonPath('data.promo_codes.0.code', 'WELCOME10')
            ->assertJsonPath('data.promo_codes.0.orders', 2)
            ->assertJsonPath('data.promo_codes.0.discount_total', 5)
            ->assertJsonPath('data.promo_codes.0.sales', 45);
    }

    public function test_fulfillment_split_lists_both_types_even_when_unused(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->order(['total' => 20.00]);

        $this->getJson('/api/v1/admin/analytics')
            ->assertOk()
            ->assertJsonPath('data.fulfillment_split.0.value', 'delivery')
            ->assertJsonPath('data.fulfillment_split.0.orders', 1)
            ->assertJsonPath('data.fulfillment_split.1.value', 'pickup')
            ->assertJsonPath('data.fulfillment_split.1.orders', 0)
            ->assertJsonPath('data.fulfillment_split.1.sales', 0);
    }

    public function test_an_unsupported_range_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/analytics?days=9999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('days');
    }

    public function test_analytics_survive_an_empty_shop(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/analytics')
            ->assertOk()
            ->assertJsonPath('data.totals.orders', 0)
            ->assertJsonPath('data.totals.sales', 0)
            // No orders must not mean dividing by zero.
            ->assertJsonPath('data.totals.average_order_value', 0)
            ->assertJsonCount(0, 'data.top_products')
            ->assertJsonCount(0, 'data.promo_codes');
    }

    public function test_orders_can_be_exported_as_csv(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $customer = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        Order::factory()->for($customer)->create([
            'order_number' => 'ORD-20260920-ABC123',
            'total' => 21.50,
            'status' => OrderStatus::Delivered,
        ]);
        Order::factory()->pickup()->create(['order_number' => 'ORD-20260920-PICKUP']);

        $response = $this->get('/api/v1/admin/orders/export');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('leueats-orders-'.now()->format('Y-m-d').'.csv');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Order,"Placed at",Status,Fulfilment', $csv);
        $this->assertStringContainsString('ORD-20260920-ABC123', $csv);
        $this->assertStringContainsString('Ada Lovelace', $csv);
        $this->assertStringContainsString('ada@example.com', $csv);
        $this->assertStringContainsString('21.50', $csv);
        $this->assertStringContainsString('Pickup', $csv);
    }

    public function test_the_export_applies_the_same_filters_as_the_orders_list(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        Order::factory()->create(['order_number' => 'ORD-DELIVERY-1']);
        Order::factory()->pickup()->create(['order_number' => 'ORD-PICKUP-1']);

        $csv = $this->get('/api/v1/admin/orders/export?fulfillment_type=pickup')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('ORD-PICKUP-1', $csv);
        $this->assertStringNotContainsString('ORD-DELIVERY-1', $csv);
    }

    public function test_export_is_admin_only(): void
    {
        $this->getJson('/api/v1/admin/orders/export')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/orders/export')->assertForbidden();
    }
}
