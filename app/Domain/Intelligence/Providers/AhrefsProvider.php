<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Providers;

use App\Domain\Intelligence\DTOs\DomainMetrics;
use App\Domain\Intelligence\DTOs\GapKeyword;
use Carbon\CarbonImmutable;

/**
 * Ahrefs API v3.
 *
 * Four endpoints, because Ahrefs splits what one row of this table shows across
 * four: `domain-rating` for DR, `metrics` for traffic, keywords and value,
 * `backlinks-stats` for links and referring domains, and `metrics-history` for
 * the twelve-month line. The gap list comes from the competitors-keywords
 * endpoint, the one call that takes both domains.
 *
 * Ahrefs has no domain authority — that is Moz's score — so `da` stays null and
 * the column shows an em dash rather than a zero.
 */
final class AhrefsProvider extends HttpMetricsProvider
{
    private const BASE = 'https://api.ahrefs.com/v3';

    public function key(): string
    {
        return 'ahrefs';
    }

    public function label(): string
    {
        return 'Ahrefs';
    }

    public function isConfigured(): bool
    {
        $token = config('services.ahrefs.token');

        return is_string($token) && $token !== '';
    }

    public function fetch(string $domain, string $ownDomain): DomainMetrics
    {
        $token = (string) config('services.ahrefs.token');
        $today = CarbonImmutable::now();

        $get = fn (string $path, array $query): mixed => $this->send(
            $domain,
            fn () => $this->request()->withToken($token)->get(self::BASE.$path, $query),
        )->json();

        $rating = $get('/site-explorer/domain-rating', ['target' => $domain, 'date' => $today->toDateString()]);

        $metrics = $get('/site-explorer/metrics', [
            'target' => $domain,
            'date' => $today->toDateString(),
            'volume_mode' => 'monthly',
        ]);

        $links = $get('/site-explorer/backlinks-stats', ['target' => $domain, 'date' => $today->toDateString()]);

        $history = $get('/site-explorer/metrics-history', [
            'target' => $domain,
            'date_from' => $today->subMonths(12)->toDateString(),
            'history_grouping' => 'monthly',
        ]);

        $compared = $get('/site-explorer/organic-competitors-keywords', [
            'target' => $domain,
            'compare_target' => $ownDomain,
            'limit' => $this->gapLimit() * 2,
        ]);

        $rows = array_values(array_filter((array) data_get($compared, 'keywords', []), 'is_array'));

        $referrers = (array) data_get(
            $get('/site-explorer/refdomains', [
                'target' => $domain,
                'date' => $today->toDateString(),
                'limit' => $this->referrerLimit(),
                'order_by' => 'domain_rating:desc',
            ]),
            'refdomains',
            [],
        );

        return new DomainMetrics(
            domain: $domain,
            provider: $this->key(),
            organicTraffic: (int) data_get($metrics, 'metrics.org_traffic', 0),
            organicKeywords: (int) data_get($metrics, 'metrics.org_keywords', 0),
            dr: $this->score(data_get($rating, 'domain_rating.domain_rating')),
            da: null,
            referringDomains: (int) data_get($links, 'metrics.live_refdomains', 0),
            backlinks: (int) data_get($links, 'metrics.live', 0),
            // Ahrefs quotes traffic value in whole units of currency.
            trafficValueCents: (int) round(((float) data_get($metrics, 'metrics.org_cost', 0)) * 100),
            trafficHistory: $this->lastTwelveMonths(array_map(
                static fn (array $point): array => [
                    'month' => mb_substr((string) ($point['date'] ?? ''), 0, 7),
                    'traffic' => (int) ($point['org_traffic'] ?? 0),
                ],
                array_values(array_filter((array) data_get($history, 'metrics', []), 'is_array')),
            )),
            // A measurement, not an estimate: the endpoint flags each keyword
            // with whether both targets rank for it. The three-way split the
            // chart draws is not built here — two thirds of it is arithmetic
            // against the project's own row, which this call knows nothing about.
            sharedKeywords: count(array_filter($rows, static fn (array $r): bool => (bool) ($r['both_rank'] ?? false))),
            // The gap is what they rank for and the project does not, so the
            // shared rows are exactly what has to come out.
            gapKeywords: $this->topGaps(array_map(
                static fn (array $row): GapKeyword => new GapKeyword(
                    keyword: (string) ($row['keyword'] ?? ''),
                    position: (int) ($row['best_position'] ?? 0),
                    volume: (int) ($row['volume'] ?? 0),
                    difficulty: (int) ($row['keyword_difficulty'] ?? 0),
                    url: isset($row['best_position_url']) ? (string) $row['best_position_url'] : null,
                ),
                array_values(array_filter($rows, static fn (array $r): bool => ! ($r['both_rank'] ?? false))),
            )),
            referringDomainNames: $this->hosts(array_map(
                static fn (mixed $row): string => is_array($row) ? (string) ($row['domain'] ?? '') : '',
                $referrers,
            )),
            fetchedAt: $today,
        );
    }

    private function score(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
