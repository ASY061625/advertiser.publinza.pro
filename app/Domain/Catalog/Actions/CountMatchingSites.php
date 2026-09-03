<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\Website;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many catalog sites a project's targeting would show.
 *
 * The number the settings form quotes before anything is saved, so the answer
 * has to come from the same rules the catalog itself applies — a form that
 * promises 142 sites and a catalog that then shows 30 is worse than no number
 * at all.
 *
 * Sensitive topics narrow rather than widen: a site is a match only if it
 * accepts *every* topic the project ticked. An advertiser writing about crypto
 * and gambling needs a site that takes both, not either.
 */
final class CountMatchingSites
{
    /**
     * @param  list<int>  $topicIds
     * @param  list<int>  $countryIds
     * @param  list<int>  $languageIds
     */
    public function handle(array $topicIds, array $countryIds, array $languageIds): int
    {
        return $this->query($topicIds, $countryIds, $languageIds)->count();
    }

    /**
     * @param  list<int>  $topicIds
     * @param  list<int>  $countryIds
     * @param  list<int>  $languageIds
     * @return Builder<Website>
     */
    public function query(array $topicIds, array $countryIds, array $languageIds): Builder
    {
        $query = Website::query()->where('is_active', true);

        if ($countryIds !== []) {
            $query->whereIn('country_id', $countryIds);
        }

        if ($languageIds !== []) {
            $query->whereIn('primary_language_id', $languageIds);
        }

        // The column is a JSON array of slugs. Matching on the slug rather than
        // the id keeps it readable in the database and survives a reseed, and
        // a LIKE over the encoded array is the one thing MySQL and SQLite
        // agree on without a JSON function each spells differently.
        foreach ($this->slugsFor($topicIds) as $slug) {
            $query->where('accepts_sensitive_topics', 'like', '%"'.$slug.'"%');
        }

        return $query;
    }

    /**
     * @param  list<int>  $topicIds
     * @return list<string>
     */
    private function slugsFor(array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        return SensitiveTopic::query()
            ->whereIn('id', $topicIds)
            ->pluck('slug')
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->all();
    }
}
