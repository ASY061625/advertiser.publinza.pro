<?php

declare(strict_types=1);

namespace App\Domain\Search\Engines;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\DTOs\SearchQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The same four searches, as four LIKE queries.
 *
 * Used wherever Scout is not on Meilisearch — the test suite runs on the
 * `collection` driver, and a small installation may never stand a search engine
 * up at all. It is not as good: no typo tolerance, no relevance ranking beyond
 * "shorter matches first". It is a working palette, which is the point — a
 * feature that only exists when an optional service is running is a feature
 * half the installations do not have.
 *
 * Four queries rather than one round trip, but they are four indexed LIKEs
 * against tables scoped to one account, which is not the cost that made
 * multi-search worth having against a network service.
 */
final class DatabaseEngine implements SearchEngine
{
    /**
     * @return array<string, list<int>>
     */
    public function multiSearch(SearchQuery ...$queries): array
    {
        $out = [];

        foreach ($queries as $query) {
            $out[$query->key] = $this->run($query);
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private function run(SearchQuery $query): array
    {
        if ($query->columns === [] || trim($query->term) === '') {
            return [];
        }

        /** @var Model $model */
        $model = new $query->model;

        $builder = $model->newQuery();

        foreach ($query->filters as $field => $value) {
            $builder->where($field, $value);
        }

        $like = self::escape($query->term);

        $builder->where(function (Builder $group) use ($query, $like): void {
            foreach ($query->columns as $column) {
                // Dot notation reaches a relation, because "that post on
                // techweekly" is a search against the website's domain and the
                // posts table has no such column.
                if (! str_contains($column, '.')) {
                    self::like($group, $column, $like);

                    continue;
                }

                [$relation, $field] = explode('.', $column, 2);

                $group->orWhereHas($relation, fn (Builder $q) => self::like($q, $field, $like, and: true));
            }
        });

        return $builder
            ->take($query->limit)
            ->pluck($model->getKeyName())
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * `%a\_b%` — the wildcards in the term itself, neutralised.
     *
     * `_` and `%` are LIKE's own wildcards, so an unescaped "a_b.com" matches
     * "axb.com" as well, and a search for "%" matches the entire catalog.
     */
    private static function escape(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($term)).'%';
    }

    /**
     * A LIKE with an explicit ESCAPE clause.
     *
     * Spelled out rather than left to `where(..., 'like', ...)`, because the
     * default escape character is not portable: MySQL treats a backslash as one
     * and SQLite does not, so the escaping above silently stops working the
     * moment the driver changes — matching nothing instead of matching too
     * much, which is the harder of the two to notice.
     *
     * @param  Builder<covariant Model>  $query
     */
    private static function like(Builder $query, string $column, string $value, bool $and = false): void
    {
        $sql = $query->getQuery()->getGrammar()->wrap($column)." like ? escape '\\'";

        $and ? $query->whereRaw($sql, [$value]) : $query->orWhereRaw($sql, [$value]);
    }
}
