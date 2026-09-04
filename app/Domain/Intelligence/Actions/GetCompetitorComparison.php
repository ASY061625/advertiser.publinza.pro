<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Actions;

use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Intelligence\Models\CompetitorMetric;
use App\Domain\Intelligence\Support\MetricsProviderRegistry;
use App\Domain\Projects\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the Competitors tab shows, from one read.
 *
 * The whole tab is one comparison — the table's chips, all three charts and the
 * recommendation strip are the same numbers seen four ways — so they are built
 * together from one set of rows. Built separately they would eventually
 * disagree, and a table that says you lead beside a chart that says you trail
 * is worse than either one alone.
 *
 * The project's own site is a row like the others (`is_self`), so it is loaded
 * by the same query and carries the same metrics. Everything below asks "is
 * this the self row?" rather than "where do I get the baseline from?".
 */
final class GetCompetitorComparison
{
    /** The metrics every delta chip and every sort is computed over. */
    private const MEASURES = [
        'organicTraffic' => 'organic_traffic',
        'organicKeywords' => 'organic_keywords',
        'dr' => 'dr',
        'da' => 'da',
        'referringDomains' => 'referring_domains',
        'backlinks' => 'backlinks',
        'trafficValueCents' => 'traffic_value_cents',
    ];

    public function __construct(
        private readonly EnsureProjectSiteTracked $ensureSelf,
        private readonly RefreshCompetitor $refresh,
        private readonly MetricsProviderRegistry $registry,
        private readonly BuildCompetitorRecommendations $recommendations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Project $project): array
    {
        $this->ensureSelf->handle($project);

        $rows = Competitor::query()
            ->where('project_id', $project->id)
            ->with('latestMetric')
            ->orderByDesc('is_self')
            ->orderBy('id')
            ->get();

        // A stale row is refetched on the way past. Cheap: it only queues work,
        // and only for rows nothing is already fetching.
        $this->refresh->refill($rows);

        $self = $rows->firstWhere('is_self', true);
        $competitors = $rows->where('is_self', false)->values();
        $baseline = $self?->latestMetric;

        $limit = (int) config('publinza.competitors.max_per_project', 10);

        return [
            'self' => $self === null ? null : $this->row($self, $baseline, null),
            'competitors' => $competitors
                ->values()
                ->map(fn (Competitor $c, int $slot): array => $this->row($c, $baseline, $slot))
                ->all(),
            'slots' => ['used' => $competitors->count(), 'limit' => $limit],
            'source' => $this->source($rows),
            'trend' => $this->trend($self, $competitors),
            'overlap' => $this->overlap($competitors, $baseline),
            'recommendations' => $this->recommendations->handle($project, $competitors, $baseline),
            'maxGapKeywords' => (int) config('publinza.competitors.gap_keywords', 100),
        ];
    }

    /**
     * One domain's line in the table, or the card above it.
     *
     * @return array<string, mixed>
     */
    private function row(Competitor $competitor, ?CompetitorMetric $baseline, ?int $slot): array
    {
        $metric = $competitor->latestMetric;

        return [
            'id' => $competitor->id,
            'domain' => $competitor->domain,
            'label' => $competitor->label,
            'isSelf' => $competitor->is_self,
            // Which of the ten colour-and-stroke identities this competitor
            // holds in the charts. By position among the tracked rows, not by
            // rank on any measure: sorting the table must not repaint the lines.
            'slot' => $slot,
            'state' => $competitor->fetch_state->value,
            'error' => $competitor->fetch_error,
            'metrics' => $metric === null ? null : [
                'organicTraffic' => $metric->organic_traffic,
                'organicKeywords' => $metric->organic_keywords,
                'dr' => $metric->dr,
                'da' => $metric->da,
                'referringDomains' => $metric->referring_domains,
                'backlinks' => $metric->backlinks,
                'trafficValueCents' => $metric->traffic_value_cents,
            ],
            'deltas' => $competitor->is_self ? null : $this->deltas($metric, $baseline),
            'updatedAt' => $metric?->fetched_at?->toIso8601String(),
            'provider' => $metric === null ? null : $this->registry->labelFor($metric->provider),
            'cooldownSeconds' => $competitor->cooldownSeconds(),
            'gapKeywords' => count($metric?->gap_keywords ?? []),
        ];
    }

    /**
     * How this domain stands against the project's own site, per measure.
     *
     * Positive means they are ahead, which for every measure here means the
     * advertiser is behind — the chip is read as a fact about the competitor,
     * and coloured by what it means for the reader.
     *
     * Null where there is nothing to compare: a measure this provider does not
     * sell, or a baseline of zero, which has no percentage difference from
     * anything. Printing "+100%" against a zero would be arithmetic on a number
     * that was never measured.
     *
     * @return array<string, array{percent: float|null, leading: bool|null}>
     */
    private function deltas(?CompetitorMetric $metric, ?CompetitorMetric $baseline): array
    {
        $deltas = [];

        foreach (self::MEASURES as $key => $column) {
            $theirs = $metric?->{$column};
            $yours = $baseline?->{$column};

            if ($metric === null || $baseline === null || $theirs === null || $yours === null) {
                $deltas[$key] = ['percent' => null, 'leading' => null];

                continue;
            }

            $deltas[$key] = [
                'percent' => $yours === 0 ? null : round((($theirs - $yours) / $yours) * 100, 1),
                // Equal is not leading and not trailing; the chip renders it flat.
                'leading' => $theirs === $yours ? null : $yours > $theirs,
            ];
        }

        return $deltas;
    }

    /**
     * Who produced the figures on screen, and when.
     *
     * From the newest row actually being displayed rather than from config: the
     * tab's line says where these numbers came from, and after a provider
     * switch that is not the same as who would answer a fetch queued now.
     *
     * @param  Collection<int, Competitor>  $rows
     * @return array<string, mixed>
     */
    private function source(Collection $rows): array
    {
        $metrics = $rows->map(static fn (Competitor $c): ?CompetitorMetric => $c->latestMetric)->filter();
        $newest = $metrics->max(static fn (CompetitorMetric $m): ?CarbonImmutable => $m->fetched_at?->toImmutable());
        $oldest = $metrics->min(static fn (CompetitorMetric $m): ?CarbonImmutable => $m->fetched_at?->toImmutable());

        $days = (int) config('publinza.competitors.cache_days', 7);
        $anyFailed = $rows->contains(static fn (Competitor $c): bool => $c->fetch_state === FetchState::Failed);

        return [
            'provider' => $this->registry->labelFor($metrics->first()?->provider),
            'updatedAt' => $newest?->toIso8601String(),
            // The amber notice: something failed, and what is on screen is the
            // last good answer rather than a current one.
            'showingCached' => $anyFailed && $metrics->isNotEmpty(),
            'cachedSince' => $oldest?->toIso8601String(),
            'cacheDays' => $days,
        ];
    }

    /**
     * Twelve months of organic traffic, one series per domain.
     *
     * The months come from the union of every series rather than from the
     * calendar: providers disagree about how much history they hold, and a
     * fixed twelve-month axis would draw a site with nine months of data as one
     * that lost all its traffic in the three before it existed.
     *
     * @param  Collection<int, Competitor>  $competitors
     * @return array<string, mixed>
     */
    private function trend(?Competitor $self, Collection $competitors): array
    {
        // A new collection, not `$competitors->prepend()`: prepend mutates the
        // receiver, and the same collection is read again below for the overlap
        // and the recommendations — both of which would then find the project's
        // own site sitting among its rivals, comparing it to itself.
        $all = collect($self === null ? [] : [$self])->concat($competitors);

        $months = [];

        foreach ($all as $competitor) {
            foreach ($competitor->latestMetric?->traffic_history ?? [] as $point) {
                if (is_array($point) && isset($point['month'])) {
                    $months[(string) $point['month']] = true;
                }
            }
        }

        $months = array_keys($months);
        sort($months);
        $months = array_slice($months, -12);

        $series = [];
        $slot = 0;

        foreach ($all as $competitor) {
            $history = [];

            foreach ($competitor->latestMetric?->traffic_history ?? [] as $point) {
                if (is_array($point) && isset($point['month'])) {
                    $history[(string) $point['month']] = (int) ($point['traffic'] ?? 0);
                }
            }

            if ($history === []) {
                // No history is not a flat line at zero. A series with nothing
                // to plot is left out of the chart and named in its empty state.
                if (! $competitor->is_self) {
                    $slot++;
                }

                continue;
            }

            $series[] = [
                'id' => $competitor->id,
                'domain' => $competitor->domain,
                'label' => $competitor->label,
                'isSelf' => $competitor->is_self,
                'slot' => $competitor->is_self ? null : $slot,
                // Null where a month is missing for this domain, so the line
                // breaks rather than dropping to the floor and back.
                'points' => array_map(static fn (string $m): ?int => $history[$m] ?? null, $months),
            ];

            if (! $competitor->is_self) {
                $slot++;
            }
        }

        return ['months' => array_values($months), 'series' => $series];
    }

    /**
     * The three-way keyword split, per competitor.
     *
     * Two thirds of it is arithmetic that needs both rows: the provider
     * measured how many keywords the two domains share, and "only them" and
     * "only you" are each side's total minus that.
     *
     * @param  Collection<int, Competitor>  $competitors
     * @return list<array<string, mixed>>
     */
    private function overlap(Collection $competitors, ?CompetitorMetric $baseline): array
    {
        if ($baseline === null) {
            return [];
        }

        $rows = [];
        $slot = 0;

        foreach ($competitors as $competitor) {
            $metric = $competitor->latestMetric;
            $shared = $metric?->shared_keywords;

            if ($metric === null || $shared === null) {
                $slot++;

                continue;
            }

            $rows[] = [
                'id' => $competitor->id,
                'domain' => $competitor->domain,
                'label' => $competitor->label,
                'slot' => $slot++,
                'shared' => $shared,
                // Clamped at zero: a provider that counts shared keywords on a
                // different basis from total keywords can report more shared
                // than either side holds, and a negative bar is not a fact.
                'theirs' => max(0, $metric->organic_keywords - $shared),
                'yours' => max(0, $baseline->organic_keywords - $shared),
                'gapKeywords' => count($metric->gap_keywords ?? []),
            ];
        }

        return $rows;
    }
}
