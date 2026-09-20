<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fulfillment = $this->fulfillment();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            // Worded for how this order is fulfilled: "Ready for pickup" rather
            // than "Out for delivery" when the customer collects it.
            'status_label' => $this->status->labelFor($fulfillment),
            // Exposed so the UI never re-implements the state machine.
            'allowed_transitions' => array_map(
                fn (OrderStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->labelFor($fulfillment),
                ],
                $this->status->allowedTransitions(),
            ),
            'can_cancel' => $this->status->isCancellableByCustomer(),
            'fulfillment_type' => $fulfillment->value,
            'fulfillment_label' => $fulfillment->label(),
            'subtotal' => (float) $this->subtotal,
            'delivery_fee' => (float) $this->delivery_fee,
            // total = subtotal + delivery_fee - discount_total
            'promo_code' => $this->promo_code,
            'discount_total' => (float) $this->discount_total,
            'total' => (float) $this->total,
            // Null on a pickup order: there is nowhere to deliver it to.
            'delivery_address' => $this->delivery_address,
            'contact_phone' => $this->contact_phone,
            'notes' => $this->notes,
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'customer' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
