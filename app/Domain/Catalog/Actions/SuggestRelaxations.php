<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DTOs\CatalogFilters;

/**
 * What to loosen when nothing matches.
 *
 * "No results" is a dead end; "raise your maximum price to $250 to see 34 more
 * sites" is a next step. Two things make that sentence worth printing, and both
 * are the reason this class is not three lines of arithmetic:
 *
 *   - The boundary is read off the inventory, not multiplied. The cheapest site
 *     above the buyer's ceiling is where the next result actually is; a fixed
 *     1.5x step lands short of it as often as not, and a suggestion that opens
 *     nothing is worse than no suggestion.
 *   - The count is measured by running the relaxed filter. A promised 34 that
 *     turns out to be 3 costs more trust than it buys.
 *
 * One filter is relaxed at a time, and only ones the buyer set. Relaxing two at
 * once makes it impossible to tell which was the problem.
 */
final class SuggestRelaxations
{
    /** Cards on screen. Three is a shortlist; six is another filter panel. */
    private const LIMIT = 3;

    public function __construct(private readonly SearchCatalog $search) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(CatalogFilters $filters, ?int $userId): array
    {
        $candidates = [];

        foreach ($this->candidates($filters, $userId) as [$label, $relaxed]) {
            $count = $this->search->count($relaxed, $userId);

            // A relaxation that opens nothing is not a suggestion.
            if ($count > 0) {
                $candidates[] = ['label' => $label, 'count' => $count, 'query' => $relaxed->toQuery()];
            }
        }

        // Most sites first: the buyer wants the change that opens the most, and
        // the count is on the card to judge it by.
        usort($candidates, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($candidates, 0, self::LIMIT);
    }

    /**
     * @return list<array{string, CatalogFilters}>
     */
    private function candidates(CatalogFilters $filters, ?int $userId): array
    {
        $candidates = [];

        if ($filters->price !== null) {
            // The cheapest site above the ceiling, rounded up to something a
            // person would name — so the number on the card is both reachable
            // and speakable.
            $next = $this->nearestAbove($filters->with(['price' => null]), $userId, 'website_prices.price_cents', $filters->price[1]);

            if ($next !== null) {
                $ceiling = $this->roundUpCents($next);

                $candidates[] = [
                    'Raise your maximum price to $'.number_format($ceiling / 100),
                    $filters->with(['price' => [$filters->price[0], $ceiling]]),
                ];
            }
        }

        foreach ([
            ['traffic', 'minimum traffic', 'metrics.monthly_traffic'],
            ['dr', 'minimum DR', 'metrics.ahrefs_dr'],
            ['da', 'minimum DA', 'metrics.moz_da'],
        ] as [$key, $name, $column]) {
            $range = $filters->{$key};

            if ($range === null || $range[0] <= 0) {
                continue;
            }

            $next = $this->nearestBelow($filters->with([$key => null]), $userId, $column, $range[0]);

            if ($next !== null) {
                $candidates[] = [
                    "Lower your {$name} to ".($key === 'traffic' ? $this->compact($next) : (string) $next),
                    $filters->with([$key => [$next, $range[1]]]),
                ];
            }
        }

        if ($filters->maxSpam !== null && $filters->maxSpam < 100) {
            $next = $this->nearestAbove($filters->with(['maxSpam' => null]), $userId, 'metrics.spam_score', $filters->maxSpam);

            if ($next !== null) {
                $candidates[] = ["Allow spam scores up to {$next}", $filters->with(['maxSpam' => $next])];
            }
        }

        // The set filters, dropped whole. There is no "nearest" value for a
        // list of ticked boxes — the only relaxation is to stop asking.
        foreach ([
            ['categories', 'Search every category'],
            ['countries', 'Include every country'],
            ['languages', 'Include every language'],
            ['topics', 'Drop the sensitive-topic requirement'],
        ] as [$key, $label]) {
            if ($filters->{$key} !== []) {
                $candidates[] = [$label, $filters->with([$key => []])];
            }
        }

        return $candidates;
    }

    /** The smallest value in the catalog strictly above a boundary. */
    private function nearestAbove(CatalogFilters $filters, ?int $userId, string $column, int $boundary): ?int
    {
        $value = $this->search->query($filters, $userId, ordered: false)
            ->reorder()
            ->where($column, '>', $boundary)
            ->min($column);

        return $value === null ? null : (int) $value;
    }

    /** The largest value in the catalog strictly below a boundary. */
    private function nearestBelow(CatalogFilters $filters, ?int $userId, string $column, int $boundary): ?int
    {
        $value = $this->search->query($filters, $userId, ordered: false)
            ->reorder()
            ->where($column, '<', $boundary)
            ->max($column);

        return $value === null ? null : (int) $value;
    }

    /** $187.30 → $200, so the card names a price rather than a measurement. */
    private function roundUpCents(int $cents): int
    {
        $dollars = (int) ceil($cents / 100);
        $step = $dollars > 1000 ? 500 : ($dollars > 200 ? 50 : 25);

        return (int) ceil($dollars / $step) * $step * 100;
    }

    private function compact(int $value): string
    {
        return match (true) {
            $value >= 1_000_000 => round($value / 1_000_000, 1).'M',
            $value >= 1_000 => round($value / 1_000, 1).'K',
            default => (string) $value,
        };
    }
}
