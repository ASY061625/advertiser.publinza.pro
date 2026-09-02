<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser;

use App\Domain\Projects\DTOs\ProjectWizardData;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The final submit. Every rule the wizard enforces per step is enforced again
 * here — the Next button being disabled is a courtesy, not a control.
 */
class StoreProjectWizardRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'website_url' => ['required', 'string', 'url', 'max:2048'],
            'name' => ['required', 'string', 'max:60'],
            'category_id' => ['required', 'integer', 'exists:website_categories,id'],
            'color' => ['nullable', 'string', 'in:'.implode(',', ProjectWizardData::COLORS)],

            'sensitive_topic_ids' => ['array'],
            'sensitive_topic_ids.*' => ['integer', 'exists:sensitive_topics,id'],
            'country_ids' => ['array'],
            'country_ids.*' => ['integer', 'exists:countries,id'],
            'language_ids' => ['array'],
            'language_ids.*' => ['integer', 'exists:languages,id'],

            'publisher_task' => ['nullable', 'string', 'max:20000'],

            'landing_pages' => ['required', 'array', 'min:1', 'max:100'],
            'landing_pages.*.anchor_text' => ['required', 'string', 'max:120'],
            'landing_pages.*.url' => ['required', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website_url.required' => 'Add the site you are promoting.',
            'website_url.url' => 'Enter the full URL, including https://.',
            'name.required' => 'Give the project a name.',
            'name.max' => 'Keep the name to 60 characters so it fits in the projects list.',
            'category_id.required' => 'Pick a category — it is what we match sites against.',
            'landing_pages.required' => 'Add at least one landing page.',
            'landing_pages.min' => 'Add at least one landing page.',
            'landing_pages.*.anchor_text.required' => 'Every row needs anchor text.',
            'landing_pages.*.url.required' => 'Every row needs a target URL.',
        ];
    }

    /**
     * The same-site rule for landing pages.
     *
     * Done here rather than as a rule string because the message has to name
     * both domains to be any use: "must be on the same domain" leaves someone
     * staring at two URLs that look alike to them.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = ProjectWizardData::fromArray($this->all());

            foreach ($data->offSiteLandingPages() as $index => $message) {
                $validator->errors()->add("landing_pages.{$index}.url", $message);
            }

            // The brief's limit is on what someone typed, not on the markup the
            // editor wrapped it in.
            if ($data->publisherTask !== null
                && mb_strlen(strip_tags($data->publisherTask)) > ProjectWizardData::MAX_TASK_CHARS) {
                $validator->errors()->add(
                    'publisher_task',
                    'The brief is over '.number_format(ProjectWizardData::MAX_TASK_CHARS).' characters. Trim it and try again.',
                );
            }
        });
    }
}
