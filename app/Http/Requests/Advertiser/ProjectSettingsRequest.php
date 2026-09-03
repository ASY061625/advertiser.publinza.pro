<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser;

use App\Domain\Projects\DTOs\ProjectWizardData;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\UrlNormalizer;
use App\Support\HtmlSanitizer;
use App\Support\PublicSuffix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProjectSettingsRequest extends FormRequest
{
    public const MAX_NAME = 60;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::MAX_NAME],
            'website_url' => ['required', 'string', 'max:2048'],
            'category_id' => ['nullable', 'integer', 'exists:website_categories,id'],
            'color' => ['nullable', 'string', 'max:32'],

            'sensitive_topic_ids' => ['array', 'max:50'],
            'sensitive_topic_ids.*' => ['integer', 'exists:sensitive_topics,id'],
            'country_ids' => ['array', 'max:300'],
            'country_ids.*' => ['integer', 'exists:countries,id'],
            'language_ids' => ['array', 'max:200'],
            'language_ids.*' => ['integer', 'exists:languages,id'],

            // A coarse ceiling on the markup; the limit people see is on the
            // text, checked below.
            'publisher_task' => ['nullable', 'string', 'max:20000'],

            'landing_pages' => ['array', 'max:200'],
            'landing_pages.*.id' => ['nullable', 'integer'],
            'landing_pages.*.anchor_text' => ['required', 'string', 'max:120'],
            'landing_pages.*.url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->checkWebsiteUrl($validator);
            $this->checkBriefLength($validator);
            $this->checkLandingPagesAreOnSite($validator);
        });
    }

    /**
     * The promoted URL has to normalise to something real, because the
     * landing-page rule below is measured against it — an unparseable value
     * here would silently let every landing page through.
     */
    private function checkWebsiteUrl(Validator $validator): void
    {
        if (UrlNormalizer::hostOf((string) $this->input('website_url')) === null) {
            $validator->errors()->add('website_url', 'Enter the full address of the site, including https://.');
        }
    }

    private function checkBriefLength(Validator $validator): void
    {
        $raw = $this->input('publisher_task');

        if (! is_string($raw) || $raw === '') {
            return;
        }

        if (mb_strlen(strip_tags(HtmlSanitizer::clean($raw))) > ProjectWizardData::MAX_TASK_CHARS) {
            $validator->errors()->add(
                'publisher_task',
                'The brief is over '.number_format(ProjectWizardData::MAX_TASK_CHARS)
                    .' characters. Trim it and try again.',
            );
        }
    }

    /**
     * Measured against the URL being *saved*, not the one on the record: an
     * advertiser moving the project to a new domain in the same save should
     * have their landing pages judged against the new one.
     */
    private function checkLandingPagesAreOnSite(Validator $validator): void
    {
        $rows = $this->input('landing_pages');
        $promoted = UrlNormalizer::hostOf((string) $this->input('website_url'));

        if (! is_array($rows) || $rows === [] || $promoted === null) {
            return;
        }

        foreach ($rows as $index => $row) {
            $url = is_array($row) ? (string) ($row['url'] ?? '') : '';

            if (trim($url) === '') {
                continue;
            }

            $host = UrlNormalizer::hostOf($url);

            if ($host === null) {
                $validator->errors()->add("landing_pages.{$index}.url", 'That does not look like a web address.');

                continue;
            }

            if (! PublicSuffix::sameSite($host, $promoted)) {
                $validator->errors()->add("landing_pages.{$index}.url", sprintf(
                    'This has to be a page on %s — you entered %s. Landing pages are the pages on your own site '
                    .'that the links point to.',
                    PublicSuffix::registrable($promoted) ?? $promoted,
                    $host,
                ));
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the project a name — the site or campaign it covers.',
            'name.max' => 'Project names are up to '.self::MAX_NAME.' characters. Shorten it a little.',
            'website_url.required' => 'Add the site you are promoting.',
            'category_id.exists' => 'Pick a category from the list.',
            'landing_pages.*.anchor_text.required' => 'Every landing page needs anchor text — the words the link uses.',
            'landing_pages.*.url.required' => 'Every landing page needs a target URL.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'website_url' => (string) $validated['website_url'],
            'category_id' => $validated['category_id'] ?? null,
            'color' => $validated['color'] ?? null,
            'publisher_task' => $validated['publisher_task'] ?? null,
            'sensitive_topic_ids' => array_values(array_map('intval', $validated['sensitive_topic_ids'] ?? [])),
            'country_ids' => array_values(array_map('intval', $validated['country_ids'] ?? [])),
            'language_ids' => array_values(array_map('intval', $validated['language_ids'] ?? [])),
            'landing_pages' => array_values($validated['landing_pages'] ?? []),
        ];
    }
}
