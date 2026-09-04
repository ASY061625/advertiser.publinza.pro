<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Actions;

use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\UrlNormalizer;
use App\Jobs\FetchCompetitorMetricsJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Tracks one more rival domain against a project.
 *
 * The four refusals — unreadable, your own site, already tracked, no slots left
 * — are all raised as validation errors on the `domain` field, because all four
 * are things the person typing can see and fix, and all four should appear
 * under the box they typed into rather than as a page-level alarm.
 *
 * The row is created before its numbers exist and the fetch is queued. That is
 * what lets the tab show the row immediately: the alternative is a form that
 * hangs for the length of six vendor round trips and then either appears or
 * does not.
 */
final class AddCompetitor
{
    public function __construct(private readonly EnsureProjectSiteTracked $ensureSelf) {}

    public function handle(Project $project, string $rawDomain, ?string $label = null): Competitor
    {
        $domain = UrlNormalizer::hostOf($rawDomain);

        if ($domain === null) {
            throw ValidationException::withMessages([
                'domain' => 'That does not look like a website address.',
            ]);
        }

        $ownDomain = UrlNormalizer::hostOf($project->website_url);

        if ($domain === $ownDomain) {
            throw ValidationException::withMessages([
                'domain' => 'That is this project’s own site — it is already the one everything is compared against.',
            ]);
        }

        if ($this->tracked($project)->where('domain', $domain)->exists()) {
            throw ValidationException::withMessages([
                'domain' => 'You are already tracking that domain on this project.',
            ]);
        }

        $limit = (int) config('publinza.competitors.max_per_project', 10);

        if ($this->tracked($project)->count() >= $limit) {
            throw ValidationException::withMessages([
                'domain' => "This project is tracking its {$limit} competitors. Remove one to add another.",
            ]);
        }

        // Your own site has to be measured before the first comparison against
        // it means anything, and this is the first moment it is certainly
        // needed. Idempotent, so adding the tenth rival does not re-queue it.
        $this->ensureSelf->handle($project);

        $competitor = Competitor::query()->create([
            'project_id' => $project->id,
            'is_self' => false,
            'domain' => $domain,
            'label' => $this->cleanLabel($label),
            'added_at' => now(),
            'fetch_state' => FetchState::Pending,
        ]);

        FetchCompetitorMetricsJob::dispatch($competitor->id);

        return $competitor;
    }

    /**
     * @return Builder<Competitor>
     */
    private function tracked(Project $project)
    {
        // The project's own row is not a competitor: it does not occupy a slot
        // and it is not a duplicate of anything the advertiser can add.
        return Competitor::query()->where('project_id', $project->id)->where('is_self', false);
    }

    private function cleanLabel(?string $label): ?string
    {
        $label = trim((string) $label);

        return $label === '' ? null : mb_substr($label, 0, 120);
    }
}
