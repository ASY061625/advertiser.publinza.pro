<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\DTOs;

use Carbon\CarbonImmutable;

/**
 * One domain's SEO figures, in the shape this product reads them.
 *
 * Every provider returns something different — Ahrefs calls it `domain_rating`,
 * Moz calls it `domain_authority`, DataForSEO nests both under a `metrics.organic`
 * object — and the translation happens in the provider, not here and not in the
 * view. Downstream code never learns which vendor answered, only what a domain's
 * numbers are, which is what makes swapping the provider a config change.
 *
 * DR and DA are deliberately both present and both nullable. They are different
 * vendors' scores for the same idea, no provider supplies both, and a missing
 * one has to stay missing: filling it from the other would invent a number and
 * print it in a column headed with someone else's trademark.
 */
final readonly class DomainMetrics
{
    /**
     * @param  list<array{month: string, traffic: int}>  $trafficHistory  Oldest first.
     * @param  int|null  $sharedKeywords  Keywords this domain and the project's
     *                                    own site both rank for. Null when the
     *                                    provider does not compare, and for the
     *                                    project's own row, which has nothing
     *                                    to compare itself to.
     * @param  list<GapKeyword>  $gapKeywords
     * @param  list<string>  $referringDomainNames  The hosts linking to this
     *                                              domain, best first and
     *                                              capped. Grouping them into
     *                                              catalog categories is done
     *                                              against Publinza's own
     *                                              websites table, not by the
     *                                              vendor — the recommendation
     *                                              it feeds can only offer
     *                                              sites this company runs.
     */
    public function __construct(
        public string $domain,
        public string $provider,
        public int $organicTraffic = 0,
        public int $organicKeywords = 0,
        public ?int $dr = null,
        public ?int $da = null,
        public int $referringDomains = 0,
        public int $backlinks = 0,
        public int $trafficValueCents = 0,
        public array $trafficHistory = [],
        public ?int $sharedKeywords = null,
        public array $gapKeywords = [],
        public array $referringDomainNames = [],
        public ?CarbonImmutable $fetchedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'organic_traffic' => $this->organicTraffic,
            'organic_keywords' => $this->organicKeywords,
            // Null stays null — the column is nullable precisely so that a
            // score nobody measured is not printed as the worst possible one.
            // A number that *was* measured is clamped: both scales are 0–100,
            // and a provider returning 240 is a provider bug.
            'dr' => $this->clamp($this->dr),
            'da' => $this->clamp($this->da),
            'referring_domains' => $this->referringDomains,
            'backlinks' => $this->backlinks,
            'traffic_value_cents' => $this->trafficValueCents,
            'provider' => $this->provider,
            'traffic_history' => $this->trafficHistory,
            'shared_keywords' => $this->sharedKeywords,
            'gap_keywords' => array_map(static fn (GapKeyword $k): array => $k->toArray(), $this->gapKeywords),
            'fetched_at' => $this->fetchedAt ?? CarbonImmutable::now(),
        ];
    }

    private function clamp(?int $score): ?int
    {
        return $score === null ? null : max(0, min(100, $score));
    }
}
