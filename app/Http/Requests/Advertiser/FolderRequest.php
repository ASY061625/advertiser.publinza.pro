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

class FolderRequest extends FormRequest
{
    /** Matches the input's maxLength, so the two cannot drift apart. */
    public const MAX_NAME = 80;

    public const MAX_ANCHOR = 120;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::MAX_NAME],
            // A coarse ceiling on the markup, matching the create wizard. The
            // limit people actually see is on the text, checked below.
            'publisher_task' => ['nullable', 'string', 'max:20000'],

            'landing_pages' => ['array', 'max:200'],
            'landing_pages.*.id' => ['nullable', 'integer'],
            'landing_pages.*.anchor_text' => ['required', 'string', 'max:'.self::MAX_ANCHOR],
            'landing_pages.*.url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->checkBriefLength($validator);
            $this->checkLandingPagesAreOnSite($validator);
        });
    }

    /**
     * The brief's limit is on what someone typed, not on the markup the editor
     * wrapped it in — a counter that charged people for the <strong> tags they
     * cannot see would be nonsense. Same number as the editor's counter, so the
     * form cannot pass the browser and then be refused by the server.
     */
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
     * A landing page is a page on the advertiser's own site. Pointing one at
     * somewhere else is either a typo or a misunderstanding of what the field
     * is for, and both are worth saying out loud rather than storing.
     */
    private function checkLandingPagesAreOnSite(Validator $validator): void
    {
        $rows = $this->input('landing_pages');

        if (! is_array($rows) || $rows === []) {
            return;
        }

        $project = $this->route('project');
        $promoted = $project instanceof Project ? UrlNormalizer::hostOf($project->website_url) : null;

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

            if ($promoted !== null && ! PublicSuffix::sameSite($host, $promoted)) {
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
            'name.required' => 'Give the folder a name — the campaign or the pages it groups.',
            'name.max' => 'Folder names are up to '.self::MAX_NAME.' characters. Shorten it a little.',
            'landing_pages.*.anchor_text.required' => 'Every landing page needs anchor text — the words the link uses.',
            'landing_pages.*.anchor_text.max' => 'Anchor text is up to '.self::MAX_ANCHOR.' characters.',
            'landing_pages.*.url.required' => 'Every landing page needs a target URL.',
        ];
    }
}
