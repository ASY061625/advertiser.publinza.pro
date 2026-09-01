<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DTOs;

/**
 * Min/max per metric across the whole active catalog.
 *
 * The catalog quant-bars scale against these, not against the visible page.
 */
final readonly class CatalogRanges
{
    public function __construct(
        public int $trafficMin,
        public int $trafficMax,
        public int $domainRatingMin,
        public int $domainRatingMax,
        public int $domainAuthorityMin,
        public int $domainAuthorityMax,
        public int $spamScoreMin,
        public int $spamScoreMax,
        public int $priceMinCents = 0,
        public int $priceMaxCents = 0,
    ) {}

    /**
     * @return array<string, array{int, int}>
     */
    public function toArray(): array
    {
        return [
            'traffic' => [$this->trafficMin, $this->trafficMax],
            'domainRating' => [$this->domainRatingMin, $this->domainRatingMax],
            'domainAuthority' => [$this->domainAuthorityMin, $this->domainAuthorityMax],
            'spamScore' => [$this->spamScoreMin, $this->spamScoreMax],
            'price' => [$this->priceMinCents, $this->priceMaxCents],
        ];
    }
}
