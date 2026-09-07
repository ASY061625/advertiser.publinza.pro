<?php

declare(strict_types=1);

namespace App\Domain\Search\Contracts;

use App\Domain\Search\DTOs\SearchQuery;

/**
 * Four indexes, one round trip.
 *
 * The palette needs websites, projects, posts and conversations for every
 * keystroke, and doing that as four sequential searches would put four network
 * round trips inside a 200ms debounce. Meilisearch answers all four in one
 * `multiSearch` request; this interface is the shape of that call, so the
 * database fallback can satisfy it too.
 *
 * Implementations return *ids*, not models. Hydrating is the caller's job,
 * because only the caller knows which relations each group's template needs —
 * and an engine that returned models would have to guess.
 */
interface SearchEngine
{
    /**
     * @return array<string, list<int>> Keyed by SearchQuery::$key, ids in
     *                                  relevance order.
     */
    public function multiSearch(SearchQuery ...$queries): array;
}
