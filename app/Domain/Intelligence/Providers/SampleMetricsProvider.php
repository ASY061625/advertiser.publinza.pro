<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Providers;

use App\Domain\Intelligence\Contracts\MetricsProvider;
use App\Domain\Intelligence\DTOs\DomainMetrics;
use App\Domain\Intelligence\DTOs\GapKeyword;
use Carbon\CarbonImmutable;

/**
 * Plausible figures for a environment with no vendor account.
 *
 * None of the four real providers answers without paid credentials, so without
 * this the tab is unreachable in development, in CI and in any review app — and
 * a feature nobody can open is a feature nobody reviews.
 *
 * It is called "Sample data" everywhere it appears, in the same line that names
 * Ahrefs or Moz when one of them answered. That line is the whole safeguard:
 * the tab already had to print its source for a real vendor, so a made-up
 * source has somewhere honest to say so.
 *
 * The numbers are derived from a hash of the domain rather than randomised, so
 * a domain keeps its figures across refreshes and two people looking at the
 * same screen see the same thing.
 */
final class SampleMetricsProvider implements MetricsProvider
{
    public function key(): string
    {
        return 'sample';
    }

    public function label(): string
    {
        return 'Sample data';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function fetch(string $domain, string $ownDomain): DomainMetrics
    {
        $seed = crc32($domain);
        $pick = static fn (int $salt, int $min, int $max): int => $min + (($seed >> $salt) % max(1, $max - $min));

        $traffic = $pick(0, 4_000, 400_000);
        $keywords = $pick(3, 500, 40_000);
        $isSelf = $domain === $ownDomain;

        return new DomainMetrics(
            domain: $domain,
            provider: $this->key(),
            organicTraffic: $traffic,
            organicKeywords: $keywords,
            dr: $pick(6, 8, 88),
            da: $pick(9, 6, 82),
            referringDomains: $pick(11, 60, 9_000),
            backlinks: $pick(13, 300, 400_000),
            trafficValueCents: $traffic * $pick(2, 40, 320),
            trafficHistory: $this->history($traffic, $seed),
            // Your own site has nothing to compare itself against.
            sharedKeywords: $isSelf ? null : (int) ($keywords * 0.18),
            gapKeywords: $isSelf ? [] : $this->gaps($domain, $seed),
            referringDomainNames: [],
            fetchedAt: CarbonImmutable::now(),
        );
    }

    /**
     * @return list<array{month: string, traffic: int}>
     */
    private function history(int $traffic, int $seed): array
    {
        $points = [];
        $month = CarbonImmutable::now()->startOfMonth()->subMonths(11);

        for ($i = 0; $i < 12; $i++) {
            // A gentle trend plus a wobble, so the lines are readable as lines
            // rather than as twelve unrelated points.
            $trend = 0.72 + (0.28 * $i / 11);
            $wobble = 1 + ((($seed >> $i) % 17) - 8) / 100;

            $points[] = [
                'month' => $month->addMonths($i)->format('Y-m'),
                'traffic' => max(0, (int) round($traffic * $trend * $wobble)),
            ];
        }

        return $points;
    }

    /**
     * @return list<GapKeyword>
     */
    private function gaps(string $domain, int $seed): array
    {
        $stems = [
            'best %s alternatives', '%s pricing', 'how to choose %s', '%s vs competitors',
            '%s for small business', 'free %s tools', '%s reviews', '%s integration guide',
            'is %s worth it', '%s discount code',
        ];

        $subject = explode('.', $domain)[0];
        $keywords = [];

        foreach ($stems as $i => $stem) {
            $keywords[] = new GapKeyword(
                keyword: sprintf($stem, $subject),
                position: 1 + (($seed >> $i) % 30),
                volume: 120 + (($seed >> ($i + 2)) % 12_000),
                difficulty: 5 + (($seed >> ($i + 4)) % 80),
                url: "https://{$domain}/".str_replace([' ', '%s'], ['-', $subject], $stem),
            );
        }

        return $keywords;
    }
}
