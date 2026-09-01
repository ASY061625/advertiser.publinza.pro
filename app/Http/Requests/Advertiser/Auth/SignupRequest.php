<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SignupRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:190', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                // Ten characters minimum, mixed case and a digit, and checked
                // against Have I Been Pwned's breach corpus. Length does more
                // for strength than symbol rules, so there is no symbol rule.
                Password::min(10)->mixedCase()->numbers()->uncompromised(),
            ],
            'company' => ['nullable', 'string', 'max:190'],
            'country' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'referrer_source' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'timezone', 'max:64'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Every message says what is wrong and what to do about it. None of them
     * says "invalid".
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter your name so we know who to address.',
            'name.min' => 'That looks too short to be a name. Enter at least two characters.',

            'email.required' => 'Enter your work email address.',
            'email.email' => 'That address is missing something — check for a typo in the domain.',
            'email.unique' => 'There is already an account with this address. Sign in instead, or reset the password.',

            'password.required' => 'Choose a password.',
            'password.min' => 'Passwords need at least 10 characters. A short phrase works well.',
            'password.mixed' => 'Add at least one capital letter and one lower-case letter.',
            'password.numbers' => 'Add at least one number.',
            'password.uncompromised' => 'This password has appeared in a public data breach. Choose a different one.',
            'password.confirmed' => 'The two passwords do not match. Retype the second one.',

            'country.exists' => 'Pick a country from the list.',
            'timezone.timezone' => 'We did not recognise that time zone. Leave it blank and we will use UTC.',

            'terms.accepted' => 'Tick the box to accept the terms and privacy policy.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
