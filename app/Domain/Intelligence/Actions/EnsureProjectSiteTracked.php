<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Actions;

use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\UrlNormalizer;
use App\Jobs\FetchCompetitorMetricsJob;

/**
 * Keeps the project's own site as a measured row, and keeps it correct.
 *
 * Two jobs, and the second is the one that is easy to forget: the promoted URL
 * is editable on the settings tab, so the self row's domain can go stale. When
 * it has, the row is repointed and refetched — otherwise every delta on the tab
 * would silently be against the site the advertiser used to promote.
 *
 * Idempotent by design: called on every read of the tab and on every add.
 */
final class EnsureProjectSiteTracked
{
    public function handle(Project $project): ?Competitor
    {
        $domain = UrlNormalizer::hostOf($project->website_url);

        if ($domain === null) {
            return null;
        }

        /** @var Competitor|null $self */
        $self = Competitor::query()
            ->where('project_id', $project->id)
            ->where('is_self', true)
            ->first();

        if ($self === null) {
            // A rival added earlier at this domain would collide with the
            // unique key. It cannot happen through AddCompetitor, which refuses
            // the project's own domain — but the promoted URL can be edited
            // afterwards to one already being tracked, and the row that is
            // already measuring that domain is the right one to promote.
            $existing = Competitor::query()
                ->where('project_id', $project->id)
                ->where('domain', $domain)
                ->first();

            if ($existing !== null) {
                $existing->forceFill(['is_self' => true, 'label' => null])->save();

                return $existing;
            }

            $self = Competitor::query()->create([
                'project_id' => $project->id,
                'is_self' => true,
                'domain' => $domain,
                'added_at' => now(),
                'fetch_state' => FetchState::Pending,
            ]);

            FetchCompetitorMetricsJob::dispatch($self->id);

            return $self;
        }

        if ($self->domain !== $domain) {
            // The advertiser may have moved the project onto a domain they were
            // tracking as a rival. Two rows cannot hold one domain, and a site
            // cannot be its own competitor, so that row goes — with its metrics,
            // which were measured against a different "own site" and are not
            // the ones this row should carry.
            Competitor::query()
                ->where('project_id', $project->id)
                ->where('domain', $domain)
                ->whereKeyNot($self->getKey())
                ->delete();

            $self->forceFill([
                'domain' => $domain,
                'fetch_state' => FetchState::Pending,
                'fetch_error' => null,
            ])->save();

            FetchCompetitorMetricsJob::dispatch($self->id);
        }

        return $self;
    }
}
