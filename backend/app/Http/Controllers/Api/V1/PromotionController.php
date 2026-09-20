<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromoCodeResource;
use App\Models\PromoCode;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Promo codes advertised on the storefront's Deals page.
 *
 * Only codes flagged `is_public` and currently redeemable are listed, so
 * private or exhausted codes are never handed out.
 */
class PromotionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PromoCodeResource::collection(
            PromoCode::query()->publiclyListed()->orderBy('min_subtotal')->get()
        );
    }
}
