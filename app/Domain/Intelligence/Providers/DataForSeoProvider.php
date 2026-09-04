<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Providers;

use App\Domain\Intelligence\DTOs\DomainMetrics;
use App\Domain\Intelligence\DTOs\GapKeyword;
use Carbon\CarbonImmutable;

/**
 * DataForSEO Labs and Backlinks APIs.
 *
 * Everything is POSTed as a list of task objects and answers under
 * `tasks.0.result.0`, so the shape is uniform even though the endpoints are
 * not. Its rank is a 0–1000 scale, rescaled here to the 0–100 the DR column
 * means — a 900 printed in a column headed DR would be read as a domain rating
 * nine times better than any site on earth.
 */
final class DataForSeoProvider extends HttpMetricsProvider
{
    private const BASE = 'https://api.dataforseo.com/v3';

    public function key(): string
    {
        return 'dataforseo';
    }

    public function label(): string
    {
        return 'DataForSEO';
    }

    public function isConfigured(): bool
    {
        $login = config('services.dataforseo.login');
        $password = config('services.dataforseo.password');

        return is_string($login) && $login !== '' && is_string($password) && $password !== '';
    }

    public function fetch(string $domain, string $ownDomain): DomainMetrics
    {
        $auth = [(string) config('services.dataforseo.login'), (string) config('services.dataforseo.password')];
        $location = (int) config('services.dataforseo.location_code', 2840);
        $language = (string) config('services.dataforseo.language_code', 'en');

        $post = fn (string $path, array $task): mixed => $this->send(
            $domain,
            fn () => $this->request()->withBasicAuth(...$auth)->post(self::BASE.$path, [$task]),
        )->json('tasks.0.result.0');

        $overview = $post('/dataforseo_labs/google/domain_rank_overview/live', [
            'target' => $domain,
            'location_code' => $location,
            'language_code' => $language,
        ]);

        $backlinks = $post('/backlinks/summary/live', ['target' => $domain, 'internal_list_limit' => 1]);

        $history = $post('/dataforseo_labs/google/historical_rank_overview/live', [
            'target' => $domain,
            'location_code' => $location,
            'language_code' => $language,
        ]);

        $intersection = $post('/dataforseo_labs/google/domain_intersection/live', [
            'target1' => $domain,
            'target2' => $ownDomain,
            'location_code' => $location,
            'language_code' => $language,
            'limit' => 1,
        ]);

        $gap = $post('/dataforseo_labs/google/competitors_domain/live', [
            'target' => $domain,
            'location_code' => $location,
            'language_code' => $language,
            'exclude_top_domains' => false,
            'limit' => $this->gapLimit(),
        ]);

        $referrers = $post('/backlinks/referring_domains/live', [
            'target' => $domain,
            'limit' => $this->referrerLimit(),
            'order_by' => ['rank,desc'],
        ]);

        $organic = data_get($overview, 'items.0.metrics.organic', []);

        return new DomainMetrics(
            domain: $domain,
            provider: $this->key(),
            organicTraffic: (int) ($organic['etv'] ?? 0),
            organicKeywords: (int) ($organic['count'] ?? 0),
            // 0–1000 → 0–100, so the column means what its heading says.
            dr: $this->rescale(data_get($backlinks, 'rank')),
            da: null,
            referringDomains: (int) data_get($backlinks, 'referring_domains', 0),
            backlinks: (int) data_get($backlinks, 'backlinks', 0),
            // `estimated_paid_traffic_cost` is what the organic traffic would
            // cost to buy — dollars, so cents is a multiplication.
            trafficValueCents: (int) round(((float) ($organic['estimated_paid_traffic_cost'] ?? 0)) * 100),
            trafficHistory: $this->lastTwelveMonths(array_values(array_filter(array_map(
                static function (mixed $item): ?array {
                    if (! is_array($item) || ! isset($item['year'], $item['month'])) {
                        return null;
                    }

                    return [
                        'month' => sprintf('%04d-%02d', (int) $item['year'], (int) $item['month']),
                        'traffic' => (int) data_get($item, 'metrics.organic.etv', 0),
                    ];
                },
                (array) data_get($history, 'items.0.items', []),
            )))),
            sharedKeywords: (int) data_get($intersection, 'total_count', 0),
            gapKeywords: $this->topGaps(array_map(
                static fn (array $row): GapKeyword => new GapKeyword(
                    keyword: (string) data_get($row, 'keyword_data.keyword', ''),
                    position: (int) data_get($row, 'ranked_serp_element.serp_item.rank_absolute', 0),
                    volume: (int) data_get($row, 'keyword_data.keyword_info.search_volume', 0),
                    difficulty: (int) data_get($row, 'keyword_data.keyword_properties.keyword_difficulty', 0),
                    url: is_string(data_get($row, 'ranked_serp_element.serp_item.url'))
                        ? (string) data_get($row, 'ranked_serp_element.serp_item.url')
                        : null,
                ),
                array_values(array_filter((array) data_get($gap, 'items', []), 'is_array')),
            )),
            referringDomainNames: $this->hosts(array_map(
                static fn (mixed $row): string => is_array($row) ? (string) ($row['domain'] ?? '') : '',
                (array) data_get($referrers, 'items', []),
            )),
            fetchedAt: CarbonImmutable::now(),
        );
    }

    private function rescale(mixed $rank): ?int
    {
        return is_numeric($rank) ? (int) round(((float) $rank) / 10) : null;
    }
}
