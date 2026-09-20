<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromoCodeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Used for both create (POST) and update (PUT/PATCH).
 * Route access is already restricted to admins by the `admin` middleware.
 */
class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('code'))) {
            // Stored upper-cased so lookups can be case-insensitive.
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }

        foreach (['is_active', 'is_public'] as $flag) {
            $this->merge([
                $flag => $this->has($flag)
                    ? filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    : true,
            ]);
        }

        // Empty strings from a form mean "no limit" / "no date".
        foreach (['starts_at', 'ends_at', 'max_uses'] as $optional) {
            if ($this->has($optional) && blank($this->input($optional))) {
                $this->merge([$optional => null]);
            }
        }

        // Free delivery ignores `value`; keep the stored number tidy.
        if ($this->input('type') === PromoCodeType::FreeDelivery->value) {
            $this->merge(['value' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Letters, numbers, dashes and underscores — codes get typed by hand.
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('promo_codes', 'code')->ignore($this->route('promo_code')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PromoCodeType::class)],
            'value' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
        ];
    }

    /**
     * Rules that depend on the chosen type, plus not cutting a limit below the
     * redemptions already handed out.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $type = PromoCodeType::tryFrom((string) $this->input('type'));
                $value = (float) $this->input('value');

                if ($type === PromoCodeType::Percent && ($value <= 0 || $value > 100)) {
                    $validator->errors()->add('value', 'A percentage discount must be between 1 and 100.');
                }

                if ($type === PromoCodeType::Fixed && $value <= 0) {
                    $validator->errors()->add('value', 'An amount discount must be greater than zero.');
                }

                $promoCode = $this->route('promo_code');
                $maxUses = $this->input('max_uses');

                if ($promoCode && $maxUses !== null && (int) $maxUses < $promoCode->uses_count) {
                    $validator->errors()->add(
                        'max_uses',
                        "This code has already been used {$promoCode->uses_count} times."
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dashes and underscores only.',
            'code.unique' => 'A promo code with this code already exists.',
            'ends_at.after' => 'The end date must be after the start date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'min_subtotal' => 'minimum order',
            'max_uses' => 'usage limit',
            'starts_at' => 'start date',
            'ends_at' => 'end date',
            'is_active' => 'active flag',
            'is_public' => 'public flag',
        ];
    }
}
