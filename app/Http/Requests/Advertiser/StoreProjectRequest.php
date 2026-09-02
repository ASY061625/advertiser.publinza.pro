<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'website_url' => ['required', 'url', 'max:2048'],
            'category_id' => ['nullable', 'integer', 'exists:website_categories,id'],
            'publisher_task' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Every message says what is wrong and what to do about it.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the project a name — the site or campaign it covers.',
            'website_url.required' => 'Add the site you are promoting.',
            'website_url.url' => 'Enter the full URL, including https://.',
            'category_id.exists' => 'Pick a category from the list.',
        ];
    }
}
