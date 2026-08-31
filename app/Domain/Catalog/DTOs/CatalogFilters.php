<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DTOs;

use Illuminate\Http\Request;

final readonly class CatalogFilters
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public ?string $query = null,
        public array $categories = [],
        public ?string $language = null,
        public ?int $minTraffic = null,
        public ?int $maxPriceMinorUnits = null,
        public ?int $minDomainRating = null,
        public ?int $maxSpamScore = null,
        public string $sort = 'traffic',
        public string $direction = 'desc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var array<int, string> $categories */
        $categories = array_values(array_filter((array) $request->input('categories', [])));

        return new self(
            query: $request->string('q')->trim()->value() ?: null,
            categories: $categories,
            language: $request->string('language')->value() ?: null,
            minTraffic: $request->integer('min_traffic') ?: null,
            maxPriceMinorUnits: $request->integer('max_price') ?: null,
            minDomainRating: $request->integer('min_dr') ?: null,
            maxSpamScore: $request->integer('max_spam') ?: null,
            sort: in_array($request->input('sort'), ['traffic', 'price_minor_units', 'domain_rating'], true)
                ? (string) $request->input('sort')
                : 'traffic',
            direction: $request->input('direction') === 'asc' ? 'asc' : 'desc',
        );
    }
}
