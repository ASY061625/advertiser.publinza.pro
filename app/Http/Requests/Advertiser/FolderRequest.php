<?php

declare(strict_types=1);

namespace App\Http\Requests\Advertiser;

use App\Domain\Projects\DTOs\ProjectWizardData;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FolderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // A coarse ceiling on the markup, matching the create wizard. The
            // limit people actually see is on the text, checked below.
            'publisher_task' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * The brief's limit is on what someone typed, not on the markup the editor
     * wrapped it in — a counter that charged people for the <strong> tags they
     * cannot see would be nonsense. Same number as the editor's counter, so the
     * form cannot pass the browser and then be refused by the server.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $raw = $this->input('publisher_task');

            if (! is_string($raw) || $raw === '') {
                return;
            }

            $text = strip_tags(HtmlSanitizer::clean($raw));

            if (mb_strlen($text) > ProjectWizardData::MAX_TASK_CHARS) {
                $validator->errors()->add(
                    'publisher_task',
                    'The brief is over '.number_format(ProjectWizardData::MAX_TASK_CHARS)
                        .' characters. Trim it and try again.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the folder a name — the campaign or the pages it groups.',
            'name.max' => 'Folder names are up to 120 characters. Shorten it a little.',
        ];
    }
}
