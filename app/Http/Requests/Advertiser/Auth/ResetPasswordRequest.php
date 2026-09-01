<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Choose a new password.',
            'password.min' => 'Passwords need at least 10 characters. A short phrase works well.',
            'password.mixed' => 'Add at least one capital letter and one lower-case letter.',
            'password.numbers' => 'Add at least one number.',
            'password.uncompromised' => 'This password has appeared in a public data breach. Choose a different one.',
            'password.confirmed' => 'The two passwords do not match. Retype the second one.',
        ];
    }
}
