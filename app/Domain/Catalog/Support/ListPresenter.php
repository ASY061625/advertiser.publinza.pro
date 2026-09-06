<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Support;

use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WishlistItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * The three lists, in the shape the shared catalog table reads.
 *
 * Every row is a CatalogPresenter row plus whatever the list itself knows —
 * when it was added, the note, the reason. Reusing the presenter is what lets
 * /lists reuse the catalog's table, its cells and its quant bars rather than
 * growing a parallel set that would drift the first time a metric changed
 * shape.
 */
final class ListPresenter
{
    public function __construct(private readonly CatalogPresenter $presenter) {}

    /**
     * @param  BaseCollection<int, Favorite>|Collection<int, Favorite>  $favorites
     * @return list<array<string, mixed>>
     */
    public function favorites($favorites, int $userId): array
    {
        return $this->rows($favorites, $userId, static fn (Favorite $row): array => [
            'addedAt' => $row->created_at->toIso8601String(),
        ]);
    }

    /**
     * @param  BaseCollection<int, WishlistItem>|Collection<int, WishlistItem>  $items
     * @return list<array<string, mixed>>
     */
    public function wishlistItems($items, int $userId): array
    {
        return $this->rows($items, $userId, static fn (WishlistItem $row): array => [
            'itemId' => $row->id,
            'addedAt' => $row->created_at->toIso8601String(),
            'note' => $row->note,
            'priority' => $row->priority,
        ]);
    }

    /**
     * @param  BaseCollection<int, Blacklist>|Collection<int, Blacklist>  $rows
     * @return list<array<string, mixed>>
     */
    public function blacklist($rows, int $userId): array
    {
        return $this->rows($rows, $userId, static fn (Blacklist $row): array => [
            'entryId' => $row->id,
            'addedAt' => $row->created_at->toIso8601String(),
            'reason' => $row->reason,
            'blockedBy' => $row->blocked_by,
        ]);
    }

    /**
     * Joins list rows to their presented sites.
     *
     * The presenter is called once for the whole page rather than per row: it
     * resolves five per-advertiser facts in bulk, and calling it a row at a
     * time would turn that saving into the N+1 it exists to avoid.
     *
     * @param  BaseCollection<int, mixed>|Collection<int, mixed>  $entries
     * @param  callable(mixed): array<string, mixed>  $extra
     * @return list<array<string, mixed>>
     */
    private function rows($entries, int $userId, callable $extra): array
    {
        $entries = collect($entries)->filter(static fn (mixed $row): bool => $row->website !== null);

        if ($entries->isEmpty()) {
            return [];
        }

        $sites = $this->presenter->handle($entries->pluck('website'), $userId, null);
        $byId = collect($sites)->keyBy('id');

        return $entries
            ->map(static function (mixed $row) use ($byId, $extra): ?array {
                $site = $byId->get($row->website_id);

                return $site === null ? null : $site + $extra($row);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The search, category and sort every tab shares.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * Applied to the site behind the row rather than to the row, because all
     * three lists are lists *of sites* — "Finance sites in my blacklist" is the
     * same question as "Finance sites in my favourites", and answering it two
     * different ways would be two things to get right.
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function filtered(Builder $query, ?string $search, ?int $categoryId, string $sort): Builder
    {
        $query->whereHas('website', function (Builder $site) use ($search, $categoryId): void {
            if ($search !== null && $search !== '') {
                $site->where(function (Builder $inner) use ($search): void {
                    $inner->where('domain', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            }

            if ($categoryId !== null) {
                $site->where('category_id', $categoryId);
            }
        });

        return self::sorted($query, $sort);
    }

    /**
     * The chosen order, applied on its own so the wishlist can put its priority
     * flag in front of it.
     *
     * Sorting by domain is a correlated subquery rather than a join: the three
     * list tables have no shared shape to join through, and a subquery on an
     * indexed primary key costs nothing at the size a personal list reaches.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function sorted(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy($query->qualifyColumn('created_at')),
            'domain' => $query->orderBy(
                Website::query()
                    ->select('domain')
                    ->whereColumn('websites.id', $query->qualifyColumn('website_id')),
            ),
            default => $query->orderByDesc($query->qualifyColumn('created_at')),
        };
    }

    /** The sorts every tab offers. */
    public const SORTS = ['newest', 'oldest', 'domain'];

    /**
     * What CatalogPresenter reads off every site, as one eager load.
     *
     * A constant rather than five strings repeated in four queries: Eloquent's
     * lazy-loading guard only arms on a multi-row hydrate, so a relation missed
     * here passes every single-row test and fails the moment somebody has two
     * favourites.
     */
    public const WITH = [
        'website.category',
        'website.primaryLanguage',
        'website.country',
        'website.latestMetric',
        'website.prices',
    ];
}
