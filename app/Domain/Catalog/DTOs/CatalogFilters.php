<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DTOs;

use Illuminate\Http\Request;

final readonly class CatalogFilters
{
    /**
     * @param  list<int>  $categories
     */
    public function __construct(
        public ?string $query = null,
        public array $categories = [],
        public ?string $language = null,
        public ?int $minTraffic = null,
        public ?int $maxPriceCents = null,
        public ?int $minDomainRating = null,
        public ?int $maxSpamScore = null,
        public string $sort = 'traffic',
        public string $direction = 'desc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<int> $categories */
        $categories = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) $request->input('categories', []),
        )));

        return new self(
            query: $request->string('q')->trim()->value() ?: null,
            categories: $categories,
            language: $request->string('language')->value() ?: null,
            minTraffic: $request->integer('min_traffic') ?: null,
            // The UI sends dollars; the schema stores cents.
            maxPriceCents: $request->integer('max_price') ? $request->integer('max_price') * 100 : null,
            minDomainRating: $request->integer('min_dr') ?: null,
            maxSpamScore: $request->integer('max_spam') ?: null,
            sort: in_array($request->input('sort'), ['traffic', 'price', 'domain_rating', 'domain_authority', 'spam_score'], true)
                ? (string) $request->input('sort')
                : 'traffic',
            direction: $request->input('direction') === 'asc' ? 'asc' : 'desc',
        );
    }
}
