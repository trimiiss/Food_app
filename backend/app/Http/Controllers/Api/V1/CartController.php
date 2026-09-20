<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PreviewCartRequest;
use App\Services\CartPricer;
use Illuminate\Http\JsonResponse;

/**
 * Prices a cart without creating an order, so the cart and checkout screens
 * show offer prices, promo discounts, the delivery fee (or none, for a pickup)
 * and totals computed by the same code that will charge the customer.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartPricer $pricer) {}

    /**
     * POST /cart/preview  { items: [{product_id, quantity}], fulfillment_type?, promo_code? }
     */
    public function preview(PreviewCartRequest $request): JsonResponse
    {
        $cart = $this->pricer->price(
            $request->validated('items'),
            $request->validated('promo_code'),
            $request->fulfillmentType(),
        );

        return response()->json(['data' => $cart->toArray()]);
    }
}
