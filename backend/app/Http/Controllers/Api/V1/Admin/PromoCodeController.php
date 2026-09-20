<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PromoCodeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoCodeRequest;
use App\Http\Resources\PromoCodeResource;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PromoCodeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PromoCodeResource::collection(PromoCode::query()->latest('id')->get())
            ->additional([
                // Lets the admin form build its type picker from the enum.
                'types' => array_map(
                    fn (PromoCodeType $type) => ['value' => $type->value, 'label' => $type->label()],
                    PromoCodeType::cases(),
                ),
            ]);
    }

    public function store(PromoCodeRequest $request): JsonResponse
    {
        $promoCode = PromoCode::create($request->validated());

        return (new PromoCodeResource($promoCode))->response()->setStatusCode(201);
    }

    public function show(PromoCode $promoCode): PromoCodeResource
    {
        return new PromoCodeResource($promoCode);
    }

    public function update(PromoCodeRequest $request, PromoCode $promoCode): PromoCodeResource
    {
        $promoCode->update($request->validated());

        return new PromoCodeResource($promoCode);
    }

    /**
     * Safe for past orders: they snapshot the code as a string rather than
     * referencing this row.
     */
    public function destroy(PromoCode $promoCode): Response
    {
        $promoCode->delete();

        return response()->noContent();
    }
}
