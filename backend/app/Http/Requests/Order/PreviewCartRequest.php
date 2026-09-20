<?php

namespace App\Http\Requests\Order;

use App\Enums\FulfillmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Same cart shape as checkout, without the delivery details — used to price a
 * cart before the customer commits to it.
 */
class PreviewCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A client that says nothing about fulfilment means "deliver it" — the
     * behaviour before pickup existed.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('fulfillment_type')) {
            $this->merge(['fulfillment_type' => FulfillmentType::Delivery->value]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('shop.max_item_quantity')],
            'fulfillment_type' => ['required', Rule::enum(FulfillmentType::class)],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function fulfillmentType(): FulfillmentType
    {
        return FulfillmentType::from($this->validated('fulfillment_type'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Your cart is empty.',
            'items.min' => 'Your cart is empty.',
            'items.*.product_id.distinct' => 'Each product may only appear once in the cart.',
            'items.*.product_id.exists' => 'One of the products in your cart no longer exists.',
        ];
    }
}
