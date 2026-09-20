<?php

namespace App\Http\Requests\Order;

use App\Enums\FulfillmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
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
     * Only ids and quantities are accepted for items — never prices.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('shop.max_item_quantity')],
            'fulfillment_type' => ['required', Rule::enum(FulfillmentType::class)],
            // Required for a delivery, meaningless for a pickup — OrderService
            // stores null in that case, whatever the client sent.
            'delivery_address' => [
                Rule::requiredIf(fn () => $this->fulfillmentType()->needsDeliveryAddress()),
                'nullable', 'string', 'min:5', 'max:500',
            ],
            // Asked for either way: the kitchen calls about both.
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Whether the code can actually be used is decided by CartPricer.
            'promo_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    /** Falls back to delivery while the enum rule itself reports a bad value. */
    public function fulfillmentType(): FulfillmentType
    {
        return FulfillmentType::tryFrom((string) $this->input('fulfillment_type')) ?? FulfillmentType::Delivery;
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
            'delivery_address.required' => 'Please tell us where to deliver, or switch to pickup.',
            'contact_phone.regex' => 'Please enter a valid phone number.',
        ];
    }
}
