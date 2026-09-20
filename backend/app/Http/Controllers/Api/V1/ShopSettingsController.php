<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public storefront settings, so the SPA can show the delivery fee, currency
 * and pickup details before checkout without hardcoding a copy of
 * config/shop.php or of the FulfillmentType enum.
 */
class ShopSettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'currency' => config('shop.currency'),
                'delivery_fee' => (float) config('shop.delivery_fee'),
                'max_item_quantity' => (int) config('shop.max_item_quantity'),
                'fulfillment_types' => FulfillmentType::options(),
                'pickup_address' => config('shop.pickup.address'),
                'pickup_ready_in_minutes' => (int) config('shop.pickup.ready_in_minutes'),
            ],
        ]);
    }
}
