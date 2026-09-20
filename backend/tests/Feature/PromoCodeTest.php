<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.delivery_fee' => 2.50, 'shop.currency' => 'EUR']);
    }

    /** A 20.00 cart: 2 x 10.00. */
    private function cart(array $overrides = []): array
    {
        $product = Product::factory()->create(['price' => 10.00]);

        return array_merge(['items' => [['product_id' => $product->id, 'quantity' => 2]]], $overrides);
    }

    private function checkout(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/orders', array_merge([
            'delivery_address' => '1 Promo Street, London',
            'contact_phone' => '+44 20 7946 0958',
        ], $payload));
    }

    // ---- Pricing preview ---------------------------------------------------

    public function test_preview_prices_a_cart_without_a_code(): void
    {
        $this->postJson('/api/v1/cart/preview', $this->cart())
            ->assertOk()
            ->assertJsonPath('data.subtotal', 20)
            ->assertJsonPath('data.delivery_fee', 2.5)
            ->assertJsonPath('data.discount_total', 0)
            ->assertJsonPath('data.total', 22.5)
            ->assertJsonPath('data.promo_code', null);
    }

    public function test_preview_includes_offer_savings(): void
    {
        $product = Product::factory()->onOffer(6.00)->create(['price' => 10.00]);

        $this->postJson('/api/v1/cart/preview', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 12)
            ->assertJsonPath('data.offer_savings', 8)
            ->assertJsonPath('data.lines.0.original_unit_price', 10);
    }

    public function test_percentage_code_discounts_the_subtotal(): void
    {
        PromoCode::factory()->percent(10)->create(['code' => 'WELCOME10']);

        $this->postJson('/api/v1/cart/preview', $this->cart(['promo_code' => 'WELCOME10']))
            ->assertOk()
            ->assertJsonPath('data.discount_total', 2)      // 10% of 20.00
            ->assertJsonPath('data.total', 20.5)            // 20 + 2.50 - 2
            ->assertJsonPath('data.promo_code.code', 'WELCOME10')
            ->assertJsonPath('data.promo_code.summary', '10% off');
    }

    public function test_codes_are_case_insensitive(): void
    {
        PromoCode::factory()->percent(10)->create(['code' => 'WELCOME10']);

        $this->postJson('/api/v1/cart/preview', $this->cart(['promo_code' => ' welcome10 ']))
            ->assertOk()
            ->assertJsonPath('data.promo_code.code', 'WELCOME10');
    }

    public function test_fixed_code_never_exceeds_the_subtotal(): void
    {
        PromoCode::factory()->fixed(50)->create(['code' => 'BIG50']);

        // 50.00 off a 20.00 cart is capped at 20.00, so only delivery is left.
        $this->postJson('/api/v1/cart/preview', $this->cart(['promo_code' => 'BIG50']))
            ->assertOk()
            ->assertJsonPath('data.discount_total', 20)
            ->assertJsonPath('data.total', 2.5);
    }

    public function test_free_delivery_code_waives_the_fee(): void
    {
        PromoCode::factory()->freeDelivery()->create(['code' => 'FREESHIP']);

        $this->postJson('/api/v1/cart/preview', $this->cart(['promo_code' => 'FREESHIP']))
            ->assertOk()
            ->assertJsonPath('data.discount_total', 2.5)
            ->assertJsonPath('data.total', 20);
    }

    public static function unusableCodes(): array
    {
        return [
            'unknown' => [null, 'This promo code does not exist.'],
            'inactive' => ['inactive', 'This promo code is no longer active.'],
            'expired' => ['expired', 'This promo code has expired.'],
            'fully redeemed' => ['fullyRedeemed', 'This promo code has been fully redeemed.'],
        ];
    }

    #[DataProvider('unusableCodes')]
    public function test_unusable_codes_are_rejected_with_a_reason(?string $state, string $message): void
    {
        if ($state) {
            PromoCode::factory()->{$state}()->create(['code' => 'NOPE']);
        }

        $this->postJson('/api/v1/cart/preview', $this->cart(['promo_code' => 'NOPE']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.promo_code.0', $message);
    }

    public function test_code_below_its_minimum_order_is_rejected(): void
    {
        PromoCode::factory()->percent(10, 30)->create(['code' => 'SPEND30']);

        $this->postJson('/api/v1/cart/preview', $this->cart(['promo_code' => 'SPEND30']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.promo_code.0', 'This code needs a minimum order of EUR 30.00.');
    }

    public function test_code_that_has_not_started_is_rejected(): void
    {
        PromoCode::factory()->percent(10)->create(['code' => 'SOON', 'starts_at' => now()->addDay()]);

        $this->postJson('/api/v1/cart/preview', $this->cart(['promo_code' => 'SOON']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.promo_code.0', 'This promo code is not available yet.');
    }

    // ---- Checkout ----------------------------------------------------------

    public function test_checkout_applies_the_code_and_records_the_redemption(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $promo = PromoCode::factory()->percent(25)->create(['code' => 'QUARTER']);

        $this->checkout($this->cart(['promo_code' => 'quarter']))
            ->assertCreated()
            ->assertJsonPath('data.subtotal', 20)
            ->assertJsonPath('data.discount_total', 5)
            ->assertJsonPath('data.total', 17.5)
            ->assertJsonPath('data.promo_code', 'QUARTER');

        $this->assertSame(1, $promo->fresh()->uses_count);
    }

    public function test_checkout_rejects_an_invalid_code_and_creates_no_order(): void
    {
        Sanctum::actingAs(User::factory()->create());
        PromoCode::factory()->expired()->create(['code' => 'OLD']);

        $this->checkout($this->cart(['promo_code' => 'OLD']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['promo_code']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_last_redemption_is_not_handed_out_twice(): void
    {
        $promo = PromoCode::factory()->percent(10)->create(['code' => 'LASTONE', 'max_uses' => 1]);
        $product = Product::factory()->create(['price' => 10.00]);
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'promo_code' => 'LASTONE'];

        Sanctum::actingAs(User::factory()->create());
        $this->checkout($payload)->assertCreated();

        // A second customer tries the same code.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create());
        $this->checkout($payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.promo_code.0', 'This promo code has been fully redeemed.');

        $this->assertSame(1, $promo->fresh()->uses_count);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_orders_without_a_code_have_no_discount(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->checkout($this->cart())
            ->assertCreated()
            ->assertJsonPath('data.discount_total', 0)
            ->assertJsonPath('data.promo_code', null)
            ->assertJsonPath('data.total', 22.5);
    }

    // ---- Public promotions listing ----------------------------------------

    public function test_only_public_redeemable_codes_are_advertised(): void
    {
        PromoCode::factory()->percent(10)->create(['code' => 'PUBLIC10']);
        PromoCode::factory()->percent(15)->create(['code' => 'SECRET15', 'is_public' => false]);
        PromoCode::factory()->expired()->create(['code' => 'OLD10']);
        PromoCode::factory()->inactive()->create(['code' => 'OFF10']);
        PromoCode::factory()->fullyRedeemed()->create(['code' => 'GONE10']);

        $response = $this->getJson('/api/v1/promotions')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('PUBLIC10', $response->json('data.0.code'));
    }
}
