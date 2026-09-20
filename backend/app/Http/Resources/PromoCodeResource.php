<?php

namespace App\Http\Resources;

use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromoCode
 */
class PromoCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'summary' => $this->summary(),
            'value' => (float) $this->value,
            'min_subtotal' => (float) $this->min_subtotal,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            // Admin-facing summary of why a code isn't currently usable.
            'is_redeemable' => $this->is_active && $this->hasStarted() && ! $this->hasExpired() && ! $this->isFullyRedeemed(),
        ];
    }
}
