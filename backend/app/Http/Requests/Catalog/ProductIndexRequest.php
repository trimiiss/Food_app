<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query-string filters for product listings (public and admin).
 */
class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:100'],
            'on_offer' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** Whether the caller asked for discounted products only. */
    public function onlyOffers(): bool
    {
        return $this->boolean('on_offer');
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 24);
    }
}
