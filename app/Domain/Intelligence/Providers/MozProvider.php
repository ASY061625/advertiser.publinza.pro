<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Providers;

use App\Domain\Intelligence\DTOs\DomainMetrics;
use Carbon\CarbonImmutable;

/**
 * Moz Links API v2.
 *
 * The narrowest of the four. Moz sells link data: domain authority, referring
 * domains and backlinks are all first-class, and organic traffic, keywords and
 * traffic value are not sold at all.
 *
 * Those columns come back as zero and the tab is honest about it rather than
 * inventing them — which is exactly why `dr` and `da` are separate nullable
 * columns and why the provider is named beside every figure. An advertiser
 * comparing two sites on DA needs to know that both numbers came from Moz.
 */
final class MozProvider extends HttpMetricsProvider
{
    private const BASE = 'https://lsapi.seomoz.com/v2';

    public function key(): string
    {
        return 'moz';
    }

    public function label(): string
    {
        return 'Moz';
    }

    public function isConfigured(): bool
    {
        $id = config('services.moz.access_id');
        $secret = config('services.moz.secret_key');

        return is_string($id) && $id !== '' && is_string($secret) && $secret !== '';
    }

    public function fetch(string $domain, string $ownDomain): DomainMetrics
    {
        $auth = [(string) config('services.moz.access_id'), (string) config('services.moz.secret_key')];

        $metrics = $this->send($domain, fn () => $this->request()
            ->withBasicAuth(...$auth)
            ->post(self::BASE.'/url_metrics', ['targets' => [$domain], 'scope' => 'root_domain']))
            ->json('results.0', []);

        $referrers = $this->send($domain, fn () => $this->request()
            ->withBasicAuth(...$auth)
            ->post(self::BASE.'/linking_root_domains', [
                'target' => $domain,
                'target_scope' => 'root_domain',
                'limit' => $this->referrerLimit(),
                'sort' => 'domain_authority',
            ]))->json('results', []);

        return new DomainMetrics(
            domain: $domain,
            provider: $this->key(),
            // Moz does not sell search traffic. Zero here means "not measured
            // by this provider", which the tab says in as many words rather
            // than drawing a site with no visitors.
            organicTraffic: 0,
            organicKeywords: 0,
            dr: null,
            da: isset($metrics['domain_authority']) ? (int) round((float) $metrics['domain_authority']) : null,
            referringDomains: (int) ($metrics['root_domains_to_root_domain'] ?? 0),
            backlinks: (int) ($metrics['external_pages_to_root_domain'] ?? 0),
            trafficValueCents: 0,
            trafficHistory: [],
            sharedKeywords: null,
            gapKeywords: [],
            referringDomainNames: $this->hosts(array_map(
                static fn (mixed $row): string => is_array($row) ? (string) ($row['root_domain'] ?? '') : '',
                (array) $referrers,
            )),
            fetchedAt: CarbonImmutable::now(),
        );
    }
}
