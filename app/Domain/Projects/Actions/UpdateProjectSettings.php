<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\ProjectAudit;
use App\Domain\Projects\Support\UrlNormalizer;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * The whole settings form, saved in one transaction.
 *
 * Everything the form can change lands together or not at all: a save that
 * wrote the name, then failed on the landing pages, would leave a project
 * half-edited and an audit trail describing a state that never existed.
 *
 * The audit entries are written from a before/after snapshot taken around the
 * write, so what the History tab shows is what actually changed rather than
 * what the form submitted — resubmitting an unchanged field records nothing.
 */
final class UpdateProjectSettings
{
    public function __construct(private readonly SaveFolder $folders) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Project $project, array $data, ?string $ip = null): Project
    {
        return DB::transaction(function () use ($actor, $project, $data, $ip): Project {
            $before = $this->snapshot($project);

            $project->update([
                'name' => trim((string) $data['name']),
                // Stored in one canonical spelling, so the same site typed two
                // ways is the same site to the same-domain landing-page check.
                'website_url' => UrlNormalizer::normalize((string) $data['website_url']) ?? (string) $data['website_url'],
                'category_id' => $data['category_id'] === null ? null : (int) $data['category_id'],
                'color' => $data['color'] === null || $data['color'] === '' ? null : (string) $data['color'],
                'publisher_task' => $this->task($data['publisher_task'] ?? null),
            ]);

            $project->sensitiveTopics()->sync($data['sensitive_topic_ids'] ?? []);
            $project->countries()->sync($data['country_ids'] ?? []);
            $project->languages()->sync($data['language_ids'] ?? []);

            // The landing pages on a project belong to its folders, and the
            // form edits the default folder's. Reusing SaveFolder means the
            // "posts already point at this page" refusal is the same rule
            // here as in the folder editor rather than a second copy of it.
            $folder = $project->folders()->orderBy('sort_order')->orderBy('id')->first();

            if ($folder !== null && isset($data['landing_pages'])) {
                $this->folders->handle($project, $folder, [
                    'name' => $folder->name,
                    'publisher_task' => $folder->publisher_task,
                    'landing_pages' => array_values((array) $data['landing_pages']),
                ]);
            }

            ProjectAudit::record($actor, $project, $this->diff($before, $this->snapshot($project)), $ip);

            return $project;
        });
    }

    /**
     * The fields the History tab reports on, in the words a person would use.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Project $project): array
    {
        // load(), not loadMissing(): the "after" snapshot runs on a model whose
        // relations are already loaded from before the sync, and reusing those
        // would compare the old targeting against itself.
        $project->refresh()->load([
            'sensitiveTopics:id,name', 'countries:id,name', 'languages:id,name', 'category:id,name',
        ]);

        return [
            'name' => $project->name,
            'website' => $project->website_url,
            'category' => $project->category?->name,
            'colour' => $project->color,
            'brief' => $project->publisher_task === null ? null : trim(strip_tags($project->publisher_task)),
            'topics' => $project->sensitiveTopics->pluck('name')->all(),
            'countries' => $project->countries->pluck('name')->all(),
            'languages' => $project->languages->pluck('name')->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $out = [];

        foreach ($after as $field => $value) {
            $out[$field] = [$before[$field] ?? null, $value];
        }

        return $out;
    }

    /**
     * Publisher-facing rich text typed by the advertiser: through the allowlist
     * once, on write, never on the way out.
     */
    private function task(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $clean = HtmlSanitizer::clean($raw);

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }
}
