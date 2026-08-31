<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'target_url' => ['required', 'url', 'max:2048'],
            'anchor_text' => ['required', 'string', 'max:190'],
            'brief' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_url.url' => 'Enter the full URL, including https://.',
        ];
    }
}
