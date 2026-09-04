<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Actions;

use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Intelligence\Exceptions\MetricsUnavailable;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Intelligence\Models\CompetitorMetric;
use App\Domain\Intelligence\Support\MetricsProviderRegistry;
use App\Domain\Projects\Support\UrlNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Asks the configured provider about one domain and writes what it said.
 *
 * A failure marks the row and stops. It does not write a metric row of zeros
 * and it does not delete the last one: the tab's amber notice exists precisely
 * so that an outage shows last week's figures with a date on them, and both of
 * those behaviours would take that away — one by overwriting the truth with
 * zeros, the other by leaving nothing to show.
 */
final class FetchCompetitorMetrics
{
    public function __construct(private readonly MetricsProviderRegistry $registry) {}

    public function handle(Competitor $competitor): bool
    {
        $ownDomain = $this->ownDomain($competitor);

        if ($ownDomain === null) {
            return $this->fail($competitor, 'This project has no readable website address.');
        }

        $provider = $this->registry->current();

        try {
            $metrics = $provider->fetch($competitor->domain, $ownDomain);
        } catch (MetricsUnavailable $e) {
            return $this->fail($competitor, $e->summary());
        }

        $row = $metrics->toRow();

        // The vendor lists who links to them; the catalog decides what those
        // links are *about*. Done here rather than in the provider because it
        // is a fact about Publinza's inventory, not about the vendor's data —
        // and because it is what makes the recommendation offerable: a category
        // in this map is a category this company can actually sell a placement in.
        $row['link_categories'] = $this->categorise($metrics->referringDomainNames);

        DB::transaction(function () use ($competitor, $row): void {
            CompetitorMetric::query()->create(['competitor_id' => $competitor->id] + $row);

            $competitor->forceFill(['fetch_state' => FetchState::Ready, 'fetch_error' => null])->save();
        });

        return true;
    }

    /**
     * Referring domains grouped by the catalog category they sit in.
     *
     * One query, `whereIn` over the hosts the provider returned. Domains that
     * are not in the catalog are simply absent — they are real links, but not
     * ones this tab can do anything about, and counting them would inflate a
     * recommendation towards categories with nothing to offer.
     *
     * @param  list<string>  $hosts
     * @return array<string, int>
     */
    private function categorise(array $hosts): array
    {
        if ($hosts === []) {
            return [];
        }

        /** @var array<string, int> $counts */
        $counts = DB::table('websites')
            ->join('website_categories', 'website_categories.id', '=', 'websites.category_id')
            ->whereIn('websites.domain', $hosts)
            ->where('websites.is_active', true)
            ->groupBy('website_categories.name')
            ->pluck(DB::raw('count(*)'), 'website_categories.name')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        arsort($counts);

        return $counts;
    }

    private function ownDomain(Competitor $competitor): ?string
    {
        // Loaded rather than touched. `$competitor->project` is a lazy load,
        // and this app turns lazy loading into an exception outside production.
        // Whether it throws depends on where the row came from — Eloquent only
        // arms the guard on models hydrated from a query that returned more
        // than one row — so a caller holding one row gets a silent extra query
        // and a caller iterating a collection gets an exception, from the same
        // line. Loading it explicitly is correct for both.
        $project = $competitor->loadMissing('project')->project;

        return $project === null ? null : UrlNormalizer::hostOf($project->website_url);
    }

    private function fail(Competitor $competitor, string $reason): bool
    {
        $competitor->forceFill([
            'fetch_state' => FetchState::Failed,
            'fetch_error' => mb_substr($reason, 0, 190),
        ])->save();

        return false;
    }
}
