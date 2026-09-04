<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Providers;

use App\Domain\Intelligence\DTOs\DomainMetrics;
use App\Domain\Intelligence\DTOs\GapKeyword;
use Carbon\CarbonImmutable;

/**
 * SEMrush Analytics API v1.
 *
 * The one vendor here that does not speak JSON. Every endpoint answers with a
 * semicolon-separated table whose first line is the header, so each call is
 * parsed into rows keyed by that header rather than by position — SEMrush adds
 * columns, and reading `$parts[4]` would silently start returning the wrong
 * measure the day they do.
 *
 * SEMrush publishes Authority Score, which is neither Ahrefs' DR nor Moz's DA.
 * It is reported in the DA column because it is the same idea on the same 0–100
 * scale, and the tab always names the provider beside the figure.
 */
final class SemrushProvider extends HttpMetricsProvider
{
    private const BASE = 'https://api.semrush.com/';

    private const ANALYTICS = 'https://api.semrush.com/analytics/v1/';

    public function key(): string
    {
        return 'semrush';
    }

    public function label(): string
    {
        return 'SEMrush';
    }

    public function isConfigured(): bool
    {
        $key = config('services.semrush.key');

        return is_string($key) && $key !== '';
    }

    public function fetch(string $domain, string $ownDomain): DomainMetrics
    {
        $key = (string) config('services.semrush.key');
        $database = (string) config('services.semrush.database', 'us');

        $ranks = $this->rows($this->send($domain, fn () => $this->request()->asForm()->get(self::BASE, [
            'type' => 'domain_ranks',
            'key' => $key,
            'domain' => $domain,
            'database' => $database,
            'export_columns' => 'Db,Dn,Rk,Or,Ot,Oc,Ad,At,Ac',
        ]))->body());

        $history = $this->rows($this->send($domain, fn () => $this->request()->asForm()->get(self::BASE, [
            'type' => 'domain_rank_history',
            'key' => $key,
            'domain' => $domain,
            'database' => $database,
            'export_columns' => 'Rk,Or,Ot,Oc,Dt',
            'display_limit' => 12,
        ]))->body());

        $backlinks = $this->rows($this->send($domain, fn () => $this->request()->asForm()->get(self::ANALYTICS, [
            'type' => 'backlinks_overview',
            'key' => $key,
            'target' => $domain,
            'target_type' => 'root_domain',
            'export_columns' => 'ascore,total,domains_num',
        ]))->body());

        $referrers = $this->rows($this->send($domain, fn () => $this->request()->asForm()->get(self::ANALYTICS, [
            'type' => 'backlinks_refdomains',
            'key' => $key,
            'target' => $domain,
            'target_type' => 'root_domain',
            'export_columns' => 'domain,domain_ascore',
            'display_limit' => $this->referrerLimit(),
        ]))->body());

        $gap = $this->rows($this->send($domain, fn () => $this->request()->asForm()->get(self::BASE, [
            'type' => 'domain_domains',
            'key' => $key,
            'domains' => "*|or|{$domain}|-|or|{$ownDomain}",
            'database' => $database,
            'export_columns' => 'Ph,P0,Nq,Kd,Ur',
            'display_limit' => $this->gapLimit(),
        ]))->body());

        $shared = $this->rows($this->send($domain, fn () => $this->request()->asForm()->get(self::BASE, [
            'type' => 'domain_domains',
            'key' => $key,
            'domains' => "*|or|{$domain}|*|or|{$ownDomain}",
            'database' => $database,
            'export_columns' => 'Ph',
            'display_limit' => 1,
            'export_escape' => 1,
        ]))->body());

        $first = $ranks[0] ?? [];
        $links = $backlinks[0] ?? [];

        return new DomainMetrics(
            domain: $domain,
            provider: $this->key(),
            organicTraffic: (int) ($first['Ot'] ?? 0),
            organicKeywords: (int) ($first['Or'] ?? 0),
            dr: null,
            da: isset($links['ascore']) ? (int) round((float) $links['ascore']) : null,
            referringDomains: (int) ($links['domains_num'] ?? 0),
            backlinks: (int) ($links['total'] ?? 0),
            // "Oc" is organic cost, in whole units of currency.
            trafficValueCents: (int) round(((float) ($first['Oc'] ?? 0)) * 100),
            trafficHistory: $this->lastTwelveMonths(array_values(array_filter(array_map(
                static function (array $row): ?array {
                    // SEMrush dates the history rows YYYYMMDD.
                    $date = (string) ($row['Dt'] ?? '');

                    return mb_strlen($date) < 6 ? null : [
                        'month' => mb_substr($date, 0, 4).'-'.mb_substr($date, 4, 2),
                        'traffic' => (int) ($row['Ot'] ?? 0),
                    ];
                },
                $history,
            )))),
            // The row count of the both-rank query, which SEMrush reports as
            // its total rather than as a page — hence display_limit 1.
            sharedKeywords: count($shared),
            gapKeywords: $this->topGaps(array_map(
                static fn (array $row): GapKeyword => new GapKeyword(
                    keyword: (string) ($row['Ph'] ?? ''),
                    position: (int) ($row['P0'] ?? 0),
                    volume: (int) ($row['Nq'] ?? 0),
                    difficulty: (int) round((float) ($row['Kd'] ?? 0)),
                    url: isset($row['Ur']) ? (string) $row['Ur'] : null,
                ),
                $gap,
            )),
            referringDomainNames: $this->hosts(array_map(
                static fn (array $row): string => (string) ($row['domain'] ?? ''),
                $referrers,
            )),
            fetchedAt: CarbonImmutable::now(),
        );
    }

    /**
     * The semicolon table, as rows keyed by the header line.
     *
     * @return list<array<string, string>>
     */
    private function rows(string $body): array
    {
        $lines = preg_split('/\R/', trim($body)) ?: [];

        // An empty result is a legitimate answer — a domain SEMrush has never
        // seen — and reads as no rows rather than as a failure.
        if (count($lines) < 2) {
            return [];
        }

        $headers = explode(';', array_shift($lines));
        $rows = [];

        foreach ($lines as $line) {
            $values = explode(';', $line);

            if (count($values) === count($headers)) {
                $rows[] = array_combine($headers, $values);
            }
        }

        return $rows;
    }
}
