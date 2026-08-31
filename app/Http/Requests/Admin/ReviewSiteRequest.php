<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSiteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'reason' => ['required_if:approved,false', 'nullable', 'string', 'max:500'],
        ];
    }
}
