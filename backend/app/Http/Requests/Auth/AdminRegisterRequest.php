<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Validator;

class AdminRegisterRequest extends RegisterRequest
{
    /**
     * With no code configured, admin self-registration is switched off.
     */
    public function authorize(): bool
    {
        return filled(config('auth.admin_registration_code'));
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Admin registration is disabled.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'registration_code' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // hash_equals: constant-time comparison, so the code can't be
                // recovered character by character from response timings.
                $valid = hash_equals(
                    (string) config('auth.admin_registration_code'),
                    (string) $this->input('registration_code'),
                );

                if (! $valid && ! $validator->errors()->has('registration_code')) {
                    $validator->errors()->add('registration_code', 'The registration code is invalid.');
                }
            },
        ];
    }
}
