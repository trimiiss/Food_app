<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Used for both create (POST) and update (PUT/PATCH).
 * Route access is already restricted to admins by the `admin` middleware.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $source = $this->input('slug') ?: $this->input('name');

        if (filled($source)) {
            $this->merge(['slug' => Str::slug($source)]);
        }

        // HTML forms and JSON clients disagree on booleans ("1", "true", true);
        // normalise before the `boolean` rule sees it. Default: available.
        $this->merge([
            'is_available' => $this->has('is_available')
                ? filter_var($this->input('is_available'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable', 'string', 'max:170',
                Rule::unique('products', 'slug')->ignore($this->route('product')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999.99'],
            'image_url' => ['nullable', 'url:http,https', 'max:500'],
            'is_available' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'A product with this name/slug already exists.',
            'category_id.exists' => 'The selected category does not exist.',
            'price.decimal' => 'The price may have at most 2 decimal places.',
        ];
    }

    /**
     * Human-readable field names for the default messages
     * ("The category field is required." rather than "category id").
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'image_url' => 'image URL',
            'is_available' => 'availability',
        ];
    }
}
