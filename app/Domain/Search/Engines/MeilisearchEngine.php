<?php

declare(strict_types=1);

namespace App\Domain\Search\Engines;

use App\Domain\Search\Contracts\SearchableIndex;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\DTOs\SearchQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery as MeilisearchQuery;
use Throwable;

/**
 * All four indexes in one HTTP request.
 *
 * Scout has no multi-search: `Model::search()` is one index per call, so four
 * groups would be four sequential round trips inside a 200ms debounce. This
 * goes to the Meilisearch client directly for `multiSearch` and leaves Scout to
 * do what it is good at — keeping the indexes current.
 */
final class MeilisearchEngine implements SearchEngine
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array<string, list<int>>
     */
    public function multiSearch(SearchQuery ...$queries): array
    {
        if ($queries === []) {
            return [];
        }

        $federated = [];

        foreach ($queries as $query) {
            /*
             * The client's own query objects, not arrays.
             *
             * multiSearch() calls ->toArray() on every entry it is handed, so a
             * plain array is a fatal error rather than a rejected request.
             */
            $built = (new MeilisearchQuery)
                ->setIndexUid($this->indexFor($query->model))
                ->setQuery($query->term)
                ->setLimit($query->limit)
                // Ids only. The rows are read from the database afterwards,
                // where they are current — an index is eventually consistent,
                // and a palette that renders a price out of it will one day
                // show a price nobody is selling at.
                ->setAttributesToRetrieve(['id']);

            if ($query->filters !== []) {
                $built->setFilter([$this->filter($query->filters)]);
            }

            $federated[] = $built;
        }

        try {
            $response = $this->client->multiSearch($federated);
        } catch (Throwable $e) {
            /*
             * A search engine being down is not a broken page.
             *
             * The palette is an accelerator, not the only way to reach
             * anything — every group in it has a page behind it. So this
             * degrades to "no results" and says so in the log, rather than
             * turning Cmd+K into an error dialog.
             */
            Log::warning('Multi-search failed; the palette will show nothing.', [
                'reason' => $e->getMessage(),
            ]);

            return [];
        }

        $out = [];

        foreach (array_values($queries) as $i => $query) {
            $hits = $response['results'][$i]['hits'] ?? [];

            $out[$query->key] = array_values(array_map(
                static fn (array $hit): int => (int) $hit['id'],
                array_filter($hits, static fn ($hit): bool => is_array($hit) && isset($hit['id'])),
            ));
        }

        return $out;
    }

    /**
     * `user_id = 12 AND status = "open"`.
     *
     * Values are quoted rather than interpolated raw: a filter expression is a
     * small language, and a string with a quote in it would otherwise change
     * the expression's shape.
     *
     * @param  array<string, int|string>  $filters
     */
    private function filter(array $filters): string
    {
        $parts = [];

        foreach ($filters as $field => $value) {
            $parts[] = is_int($value)
                ? "{$field} = {$value}"
                : sprintf('%s = "%s"', $field, str_replace('"', '\"', $value));
        }

        return implode(' AND ', $parts);
    }

    /**
     * The index name Scout would have used.
     *
     * `searchableAs()` already carries `scout.prefix` — prepending it again
     * produces `publinza_publinza_posts`, an index that exists nowhere and
     * returns nothing, quietly. The SearchableIndex bound is what makes the
     * method callable at all: Scout's Searchable is a trait, so without an
     * interface there is no type that means "this model has an index".
     *
     * @param  class-string<Model&SearchableIndex>  $model
     */
    private function indexFor(string $model): string
    {
        return (string) (new $model)->searchableAs();
    }
}
