<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            // decimal:2 casts to a string ("8.50"); expose numbers to the client.
            // `price` is the list price, `effective_price` is what is charged now.
            'price' => (float) $this->price,
            'effective_price' => (float) $this->effectivePrice(),
            'discount_price' => $this->discount_price === null ? null : (float) $this->discount_price,
            'discount_ends_at' => $this->discount_ends_at?->toIso8601String(),
            'is_on_offer' => $this->isOnOffer(),
            'discount_percentage' => $this->discountPercentage(),
            'image_url' => $this->image_url,
            'is_available' => $this->is_available,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
