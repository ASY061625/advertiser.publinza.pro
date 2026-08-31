<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser;

use Illuminate\Foundation\Http\FormRequest;

class TopUpRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:10', 'max:100000'],
            'reference' => ['required', 'string', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'The smallest top-up is $10.',
        ];
    }
}
