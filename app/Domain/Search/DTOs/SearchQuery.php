<?php

declare(strict_types=1);

namespace App\Domain\Search\DTOs;

use App\Domain\Search\Contracts\SearchableIndex;
use Illuminate\Database\Eloquent\Model;

/** One index's share of a multi-search. */
final readonly class SearchQuery
{
    /**
     * @param  string  $key  How the caller finds this query's ids in the result.
     * @param  class-string<Model&SearchableIndex>  $model  The searchable model, which names the index.
     * @param  list<string>  $columns  What the database fallback matches on. Ignored by
     *                                 Meilisearch, which was told at index time.
     * @param  array<string, int|string>  $filters  Equality filters. In practice `user_id`,
     *                                              and the reason this parameter exists.
     */
    public function __construct(
        public string $key,
        public string $model,
        public string $term,
        public int $limit,
        public array $columns = [],
        public array $filters = [],
    ) {}
}
