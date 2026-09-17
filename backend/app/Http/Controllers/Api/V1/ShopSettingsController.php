<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public storefront settings, so the SPA can show the delivery fee and
 * currency before checkout without hardcoding a copy of config/shop.php.
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
            ],
        ]);
    }
}
