<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPromoCodeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        return Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SUMMER20',
            'description' => 'Summer sale',
            'type' => 'percent',
            'value' => 20,
            'min_subtotal' => 15,
            'is_active' => true,
            'is_public' => true,
        ], $overrides);
    }

    public function test_promo_code_endpoints_require_an_admin(): void
    {
        $promo = PromoCode::factory()->create();

        $this->getJson('/api/v1/admin/promo-codes')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/promo-codes')->assertForbidden();
        $this->postJson('/api/v1/admin/promo-codes', $this->payload())->assertForbidden();
        $this->deleteJson("/api/v1/admin/promo-codes/{$promo->id}")->assertForbidden();
    }

    public function test_admin_can_create_a_code_and_it_is_upper_cased(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/promo-codes', $this->payload(['code' => 'summer20']))
            ->assertCreated()
            ->assertJsonPath('data.code', 'SUMMER20')
            ->assertJsonPath('data.summary', '20% off')
            ->assertJsonPath('data.uses_count', 0)
            ->assertJsonPath('data.is_redeemable', true);
    }

    public function test_admin_list_includes_private_and_expired_codes_plus_types(): void
    {
        $this->actingAsAdmin();
        PromoCode::factory()->create(['code' => 'PUBLIC10']);
        PromoCode::factory()->create(['code' => 'SECRET10', 'is_public' => false]);
        PromoCode::factory()->expired()->create(['code' => 'OLD10']);

        $this->getJson('/api/v1/admin/promo-codes')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(3, 'types');
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $this->actingAsAdmin();
        PromoCode::factory()->create(['code' => 'SUMMER20']);

        $this->postJson('/api/v1/admin/promo-codes', $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'A promo code with this code already exists.');
    }

    public function test_validation_rules(): void
    {
        $this->actingAsAdmin();

        // Percentage above 100, spaces in the code, end before start.
        $this->postJson('/api/v1/admin/promo-codes', $this->payload([
            'code' => 'BAD CODE',
            'value' => 150,
            'starts_at' => now()->addWeek()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'value', 'ends_at'])
            ->assertJsonPath('errors.value.0', 'A percentage discount must be between 1 and 100.');

        $this->postJson('/api/v1/admin/promo-codes', $this->payload(['type' => 'fixed', 'value' => 0]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.value.0', 'An amount discount must be greater than zero.');

        $this->postJson('/api/v1/admin/promo-codes', $this->payload(['type' => 'buy_one_get_one']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_free_delivery_ignores_the_value(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/promo-codes', $this->payload(['code' => 'FREESHIP', 'type' => 'free_delivery', 'value' => 99]))
            ->assertCreated()
            ->assertJsonPath('data.value', 0)
            ->assertJsonPath('data.summary', 'Free delivery');
    }

    public function test_admin_can_update_and_deactivate_a_code(): void
    {
        $this->actingAsAdmin();
        $promo = PromoCode::factory()->percent(10)->create(['code' => 'SUMMER20']);

        $this->putJson("/api/v1/admin/promo-codes/{$promo->id}", $this->payload(['value' => 25, 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('data.value', 25)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_redeemable', false);

        // A deactivated code stops working at checkout.
        $this->getJson('/api/v1/promotions')->assertJsonCount(0, 'data');
    }

    public function test_usage_limit_cannot_be_cut_below_redemptions_already_made(): void
    {
        $this->actingAsAdmin();
        $promo = PromoCode::factory()->create(['code' => 'SUMMER20', 'uses_count' => 7]);

        $this->putJson("/api/v1/admin/promo-codes/{$promo->id}", $this->payload(['max_uses' => 3]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.max_uses.0', 'This code has already been used 7 times.');
    }

    public function test_deleting_a_code_keeps_past_orders_intact(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20.00]);
        PromoCode::factory()->percent(10)->create(['code' => 'TEMP10']);

        Sanctum::actingAs($user);
        $orderId = $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'delivery_address' => '1 Promo Street, London',
            'contact_phone' => '+44 20 7946 0958',
            'promo_code' => 'TEMP10',
        ])->assertCreated()->json('data.id');

        $this->app['auth']->forgetGuards();
        $this->actingAsAdmin();
        $this->deleteJson('/api/v1/admin/promo-codes/'.PromoCode::firstWhere('code', 'TEMP10')->id)->assertNoContent();

        $this->getJson("/api/v1/admin/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.promo_code', 'TEMP10')
            ->assertJsonPath('data.discount_total', 2);
    }

    public function test_dashboard_counts_offers_and_active_codes(): void
    {
        $this->actingAsAdmin();
        Product::factory()->count(2)->onOffer(5.00)->create(['price' => 10.00]);
        Product::factory()->onOffer(3.00, now()->subDay()->toDateTimeString())->create(['price' => 9.00]);
        Product::factory()->create();
        PromoCode::factory()->create(['code' => 'LIVE10']);
        PromoCode::factory()->expired()->create(['code' => 'DEAD10']);

        $this->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.products_on_offer_count', 2)
            ->assertJsonPath('data.active_promo_codes_count', 1);
    }
}
