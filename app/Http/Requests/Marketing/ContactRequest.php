<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // RFC syntax only. The `dns` rule performs a live MX lookup inside the
            // request, which puts a network round trip on the critical path and
            // rejects a valid address whenever its DNS is briefly unreachable.
            // Whether an address receives mail is settled by sending to it.
            'email' => ['required', 'email:rfc', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'website' => ['nullable', 'string', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.min' => 'Tell us a little more so we can answer properly.',
            'email.email' => 'That does not look like an address we can reply to.',
        ];
    }
}
