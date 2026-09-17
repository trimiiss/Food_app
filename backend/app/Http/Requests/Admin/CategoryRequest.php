<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Used for both create (POST) and update (PUT/PATCH).
 * Route access is already restricted to admins by the `admin` middleware.
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Slug is optional: derive it from the name when left blank.
     */
    protected function prepareForValidation(): void
    {
        $source = $this->input('slug') ?: $this->input('name');

        if (filled($source)) {
            $this->merge(['slug' => Str::slug($source)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable', 'string', 'max:120',
                // On update, the category's own current slug isn't a conflict.
                Rule::unique('categories', 'slug')->ignore($this->route('category')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'A category with this name/slug already exists.',
        ];
    }
}
