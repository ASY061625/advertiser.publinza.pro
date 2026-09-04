<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Actions;

use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Intelligence\Models\CompetitorMetric;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Collection;

/**
 * Where a competitor has links and this project does not.
 *
 * The commercial point of the tab, and the reason it has to be derived rather
 * than written: "they have 34 links from Technology sites you don't" is only
 * worth reading if the 34 is real and the Technology sites are ones an
 * advertiser can actually buy a placement on. Both come from the same place —
 * the referring domains the provider listed, intersected with Publinza's own
 * catalog by FetchCompetitorMetrics.
 *
 * Which means a category never appears here unless the catalog has sites in it.
 * A suggestion nobody can act on is an advertisement for a gap in the inventory.
 */
final class BuildCompetitorRecommendations
{
    /**
     * @param  Collection<int, Competitor>  $competitors
     * @return list<array<string, mixed>>
     */
    public function handle(Project $project, Collection $competitors, ?CompetitorMetric $baseline): array
    {
        $yours = $this->counts($baseline);
        $best = [];

        foreach ($competitors as $competitor) {
            foreach ($this->counts($competitor->latestMetric) as $category => $count) {
                $lead = $count - ($yours[$category] ?? 0);

                if ($lead < 1) {
                    continue;
                }

                // One card per category, naming the competitor furthest ahead
                // in it. Summing across rivals instead would double-count the
                // sites two of them both link from, and produce a number the
                // advertiser cannot find anywhere else on the page.
                if (! isset($best[$category]) || $lead > $best[$category]['count']) {
                    $best[$category] = [
                        'category' => $category,
                        'count' => $lead,
                        'competitor' => $competitor->label ?? $competitor->domain,
                        'competitorId' => $competitor->id,
                    ];
                }
            }
        }

        if ($best === []) {
            return [];
        }

        usort($best, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $best = array_slice($best, 0, (int) config('publinza.competitors.recommendations', 5));

        // One query for the ids the catalog link needs, after the shortlist is
        // known rather than for every category any competitor has a link in.
        $ids = WebsiteCategory::query()
            ->whereIn('name', array_column($best, 'category'))
            ->pluck('id', 'name');

        return array_values(array_map(static fn (array $row): array => [
            'category' => $row['category'],
            'categoryId' => $ids[$row['category']] ?? null,
            'count' => $row['count'],
            'competitor' => $row['competitor'],
            'competitorId' => $row['competitorId'],
        ], $best));
    }

    /**
     * @return array<string, int>
     */
    private function counts(?CompetitorMetric $metric): array
    {
        $categories = $metric?->link_categories;

        if (! is_array($categories)) {
            return [];
        }

        $counts = [];

        foreach ($categories as $name => $count) {
            if (is_string($name) && is_numeric($count)) {
                $counts[$name] = (int) $count;
            }
        }

        return $counts;
    }
}
