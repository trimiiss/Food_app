<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Null if the product has since been deleted; the snapshot fields remain.
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'unit_price' => (float) $this->unit_price,
            // Set only when the line was bought on offer ("was EUR x.xx").
            'original_unit_price' => $this->original_unit_price === null ? null : (float) $this->original_unit_price,
            'quantity' => $this->quantity,
            'line_total' => (float) $this->line_total,
        ];
    }
}
