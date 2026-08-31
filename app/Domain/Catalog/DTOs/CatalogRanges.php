<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DTOs;

/**
 * Min/max per metric across the whole filtered catalog.
 *
 * The quant-bars in the catalog table scale against this, not against the
 * visible page — otherwise the bars would rescale on every pagination click and
 * a buyer could not compare shapes between pages.
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
        ];
    }
}
