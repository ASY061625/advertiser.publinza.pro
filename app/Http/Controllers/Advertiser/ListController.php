<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Actions\GetCatalogRanges;
use App\Domain\Catalog\Actions\ImportBlacklist;
use App\Domain\Catalog\Actions\MoveBetweenLists;
use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Catalog\Models\Wishlist;
use App\Domain\Catalog\Models\WishlistItem;
use App\Domain\Catalog\Support\ListPresenter;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The three lists an advertiser keeps about sites, on one page.
 *
 * One page rather than three because they are one decision seen from three
 * angles — a site is saved, planned or refused — and moving between them is
 * the most common thing anybody does here. Three routes would have made that
 * a navigation.
 *
 * The tab lives in the query string so a link is a view: the header's heart
 * points at `?tab=favorites` and lands on it.
 */
class ListController extends Controller
{
    private const TABS = ['favorites', 'wishlist', 'blacklist'];

    public function index(Request $request, GetCatalogRanges $ranges, ListPresenter $presenter): Response
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'favorites';

        $search = trim((string) $request->query('q', '')) ?: null;
        $categoryId = $request->integer('category') ?: null;
        $sort = in_array($request->query('sort'), ListPresenter::SORTS, true)
            ? (string) $request->query('sort')
            : 'newest';

        $wishlists = $this->wishlists($user);
        $activeWishlist = $this->activeWishlist($wishlists, $request->integer('list') ?: null);

        return inertia('Lists/Index', [
            'tab' => $tab,
            'filters' => ['q' => $search, 'category' => $categoryId, 'sort' => $sort, 'list' => $activeWishlist?->id],
            // Only the tab being looked at is loaded. The counts come from
            // three cheap aggregates, so the tab strip is honest without
            // paying for three pages of rows nobody asked for.
            'rows' => match ($tab) {
                'wishlist' => $activeWishlist === null
                    ? []
                    : $presenter->wishlistItems(
                        $this->wishlistQuery($activeWishlist, $search, $categoryId, $sort)->get(),
                        $user->id,
                    ),
                'blacklist' => $presenter->blacklist(
                    $this->blacklistQuery($user, $search, $categoryId, $sort)->get(),
                    $user->id,
                ),
                default => $presenter->favorites(
                    $this->favoritesQuery($user, $search, $categoryId, $sort)->get(),
                    $user->id,
                ),
            },
            'counts' => [
                'favorites' => Favorite::query()->where('user_id', $user->id)->count(),
                'wishlist' => WishlistItem::query()
                    ->whereIn('wishlist_id', $wishlists->pluck('id'))
                    ->count(),
                'blacklist' => Blacklist::query()->where('user_id', $user->id)->count(),
            ],
            'wishlists' => $wishlists->map(static fn (Wishlist $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'itemCount' => (int) $list->items_count,
            ])->values()->all(),
            'summary' => $activeWishlist === null || $tab !== 'wishlist'
                ? null
                : $this->wishlistSummary($activeWishlist),
            'ranges' => $ranges->handle()->toArray(),
            'categories' => WebsiteCategory::query()->orderBy('name')->get(['id', 'name'])->all(),
            'projects' => $this->projects($user),
            'sorts' => [
                ['value' => 'newest', 'label' => 'Recently added'],
                ['value' => 'oldest', 'label' => 'Oldest first'],
                ['value' => 'domain', 'label' => 'Domain A–Z'],
            ],
        ]);
    }

    /**
     * The list, as a spreadsheet.
     *
     * Streamed rather than built in memory, and it carries the same filters the
     * screen is showing — an export that ignores the search is an export of a
     * different list from the one somebody is looking at.
     */
    public function export(Request $request, ListPresenter $presenter): StreamedResponse
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'favorites';
        $search = trim((string) $request->query('q', '')) ?: null;
        $categoryId = $request->integer('category') ?: null;
        $sort = in_array($request->query('sort'), ListPresenter::SORTS, true)
            ? (string) $request->query('sort')
            : 'newest';

        $wishlists = $this->wishlists($user);
        $activeWishlist = $this->activeWishlist($wishlists, $request->integer('list') ?: null);

        $rows = match ($tab) {
            'wishlist' => $activeWishlist === null ? [] : $presenter->wishlistItems(
                $this->wishlistQuery($activeWishlist, $search, $categoryId, $sort)->get(),
                $user->id,
            ),
            'blacklist' => $presenter->blacklist(
                $this->blacklistQuery($user, $search, $categoryId, $sort)->get(),
                $user->id,
            ),
            default => $presenter->favorites(
                $this->favoritesQuery($user, $search, $categoryId, $sort)->get(),
                $user->id,
            ),
        };

        $name = $tab.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $tab): void {
            $out = fopen('php://output', 'wb');

            $headers = ['Domain', 'Category', 'Monthly traffic', 'DR', 'Price', 'Added'];
            $headers = match ($tab) {
                'wishlist' => [...$headers, 'Priority', 'Note'],
                'blacklist' => [...$headers, 'Reason', 'Blocked by'],
                default => $headers,
            };

            fputcsv($out, $headers);

            foreach ($rows as $row) {
                $line = [
                    $row['domain'],
                    $row['category'],
                    // Blank rather than 0 where nothing was measured: a
                    // spreadsheet that averages this column must not count an
                    // unmeasured site as a zero.
                    $row['traffic'] ?? '',
                    $row['domainRating'] ?? '',
                    number_format(($row['priceCents'] ?? 0) / 100, 2, '.', ''),
                    $row['addedAt'] ?? '',
                ];

                fputcsv($out, match ($tab) {
                    'wishlist' => [...$line, ($row['priority'] ?? false) ? 'Yes' : '', $row['note'] ?? ''],
                    'blacklist' => [...$line, $row['reason'] ?? '', $row['blockedBy'] ?? ''],
                    default => $line,
                });
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------- wishlists

    public function storeWishlist(Request $request): RedirectResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:120']])['name'];

        $list = Wishlist::query()->create(['user_id' => $request->user()->id, 'name' => $name]);

        return back()->with('success', "Created {$list->name}.");
    }

    public function updateWishlist(Request $request, Wishlist $wishlist): RedirectResponse
    {
        $this->ownsWishlist($request, $wishlist);

        $wishlist->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back()->with('success', 'Renamed.');
    }

    /**
     * Copies a list and everything in it.
     *
     * The notes and flags come along: a duplicate is somebody about to make a
     * variant of a plan they have already thought about, and stripping the
     * thinking out of the copy would leave them retyping it.
     */
    public function duplicateWishlist(Request $request, Wishlist $wishlist): RedirectResponse
    {
        $this->ownsWishlist($request, $wishlist);

        $copy = Wishlist::query()->create([
            'user_id' => $request->user()->id,
            'name' => mb_substr($wishlist->name.' (copy)', 0, 120),
        ]);

        $rows = $wishlist->items()->get()->map(static fn (WishlistItem $item): array => [
            'wishlist_id' => $copy->id,
            'website_id' => $item->website_id,
            'note' => $item->note,
            'priority' => $item->priority,
            'created_at' => now(),
        ])->all();

        if ($rows !== []) {
            WishlistItem::query()->insert($rows);
        }

        return back()->with('success', "Duplicated as {$copy->name}.");
    }

    public function destroyWishlist(Request $request, Wishlist $wishlist): RedirectResponse
    {
        $this->ownsWishlist($request, $wishlist);

        $name = $wishlist->name;
        $wishlist->delete();

        return back()->with('success', "Deleted {$name}.");
    }

    /** The note and the priority flag, saved inline. */
    public function updateWishlistItem(Request $request, WishlistItem $item): RedirectResponse
    {
        abort_unless($this->ownsList($request->user(), $item->wishlist_id), 403);

        $item->update($request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'priority' => ['nullable', 'boolean'],
        ]));

        return back();
    }

    // ------------------------------------------------------------- blacklist

    /** The reason, edited in place on the row. */
    public function updateBlacklist(Request $request, Blacklist $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->update($request->validate(['reason' => ['nullable', 'string', 'max:500']]));

        return back();
    }

    /**
     * Unblock everything that was ticked.
     *
     * Selection is offered on all three tabs, so it needs an action on all
     * three. Unblocking is the only bulk operation the blacklist has that is
     * not already a move, and doing it a row at a time after a bad import is
     * the reason people give up and keep the wrong sites hidden.
     */
    public function destroyBlacklist(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_ids' => ['required', 'array', 'min:1'],
            'website_ids.*' => ['integer'],
        ]);

        $removed = Blacklist::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('website_id', $data['website_ids'])
            ->delete();

        return back()->with(
            'success',
            $removed === 1 ? 'Site unblocked.' : $removed.' sites unblocked.',
        );
    }

    public function importBlacklist(Request $request, ImportBlacklist $import): RedirectResponse
    {
        $data = $request->validate([
            'domains' => ['required', 'string', 'max:20000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $report = $import->handle($request->user(), $data['domains'], $data['reason'] ?? null);

        // The report goes back in the session rather than as a toast: three
        // named groups and a list of unmatched domains is more than a toast can
        // hold, and the unmatched ones are the whole reason to look.
        return back()->with('importReport', $report);
    }

    // ------------------------------------------------------------ moving out

    /**
     * One site, from one list to another, with what undo needs to reverse it.
     */
    public function move(Request $request, MoveBetweenLists $move): RedirectResponse
    {
        $data = $request->validate([
            'website_id' => ['required', 'integer'],
            'from' => ['required', 'in:'.implode(',', MoveBetweenLists::LISTS)],
            'to' => ['required', 'in:'.implode(',', MoveBetweenLists::LISTS)],
            'wishlist_id' => ['nullable', 'integer'],
        ]);

        $website = Website::query()->find($data['website_id']);

        if ($website === null) {
            return back();
        }

        try {
            $moved = $move->handle(
                $request->user(),
                $website,
                $data['from'],
                $data['to'],
                $data['wishlist_id'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('moved', $moved);
    }

    public function undoMove(Request $request, MoveBetweenLists $move): RedirectResponse
    {
        $data = $request->validate([
            'websiteId' => ['required', 'integer'],
            'from' => ['required', 'in:'.implode(',', MoveBetweenLists::LISTS)],
            'to' => ['required', 'in:'.implode(',', MoveBetweenLists::LISTS)],
            'wishlistId' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
            'priority' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $move->undo($request->user(), $data);

        return back()->with('success', 'Put back.');
    }

    // ----------------------------------------------------------------- carts

    /**
     * Adds several sites to the cart at once.
     *
     * Sites with no price for the service are skipped rather than failing the
     * whole action: a shortlist assembled over weeks can contain a site whose
     * publisher has since withdrawn, and refusing the other eleven because of
     * it would be the wrong trade.
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_ids' => ['required', 'array', 'min:1'],
            'website_ids.*' => ['integer'],
            'project_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $project = Project::query()->where('user_id', $user->id)->find($data['project_id']);

        if ($project === null) {
            return back()->with('error', 'Choose a project first.');
        }

        $service = ServiceType::ArticlePlacement;
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        $already = CartItem::query()
            ->where('cart_id', $cart->id)
            ->whereIn('website_id', $data['website_ids'])
            ->pluck('website_id')
            ->flip();

        $sites = Website::query()
            ->with('prices')
            ->whereIn('id', $data['website_ids'])
            ->where('is_active', true)
            ->get();

        $added = 0;
        $skipped = 0;

        foreach ($sites as $site) {
            $price = $site->priceFor($service);

            if ($price === null || $already->has($site->id)) {
                $skipped++;

                continue;
            }

            CartItem::query()->create([
                'cart_id' => $cart->id,
                'website_id' => $site->id,
                'project_id' => $project->id,
                'service_type' => $service,
                'content_mode' => ContentMode::AdvertiserProvides,
                'unit_price_cents' => $price->price_cents,
            ]);

            $added++;
        }

        $message = $added === 0
            ? 'Nothing to add — those are already in your cart.'
            : sprintf('Added %d %s to your cart.', $added, $added === 1 ? 'site' : 'sites');

        return back()->with(
            $added === 0 ? 'error' : 'success',
            $skipped === 0 ? $message : $message.sprintf(' %d skipped.', $skipped),
        );
    }

    // ------------------------------------------------------------- internals

    /**
     * @param  Collection<int, Wishlist>  $wishlists
     */
    private function activeWishlist(Collection $wishlists, ?int $listId): ?Wishlist
    {
        if ($listId !== null) {
            $chosen = $wishlists->firstWhere('id', $listId);

            if ($chosen !== null) {
                return $chosen;
            }
        }

        return $wishlists->first();
    }

    /**
     * @return Collection<int, Wishlist>
     */
    private function wishlists(User $user): Collection
    {
        return Wishlist::query()
            ->where('user_id', $user->id)
            ->withCount('items')
            ->orderBy('name')
            ->get();
    }

    /**
     * What a shortlist adds up to.
     *
     * The averages skip unmeasured sites rather than counting them as zero: a
     * list of ten sites where two have never been crawled has an average DR of
     * the eight that have, and dividing by ten would report a number lower than
     * any site in the list.
     *
     * @return array<string, mixed>
     */
    private function wishlistSummary(Wishlist $wishlist): array
    {
        $sites = Website::query()
            ->with(['prices', 'latestMetric'])
            ->whereIn('id', $wishlist->items()->select('website_id'))
            ->get();

        $priced = $sites->map(static fn (Website $site): int => $site->priceFor(ServiceType::ArticlePlacement)?->price_cents ?? 0);
        $rated = $sites->map(static fn (Website $site): ?int => $site->latestMetric?->ahrefs_dr)->filter(static fn (?int $dr): bool => $dr !== null);
        $traffic = $sites->map(static fn (Website $site): ?int => $site->latestMetric?->monthly_traffic)->filter(static fn (?int $value): bool => $value !== null);

        return [
            'siteCount' => $sites->count(),
            'totalCents' => (int) $priced->sum(),
            'averageDr' => $rated->isEmpty() ? null : (int) round($rated->avg()),
            'totalTraffic' => (int) $traffic->sum(),
            // Named so the strip can say what the average is of, rather than
            // implying every site was measured.
            'measured' => $rated->count(),
        ];
    }

    /**
     * @return Builder<Favorite>
     */
    private function favoritesQuery(User $user, ?string $search, ?int $categoryId, string $sort): Builder
    {
        return ListPresenter::filtered(
            Favorite::query()->with(ListPresenter::WITH)->where('user_id', $user->id),
            $search,
            $categoryId,
            $sort,
        );
    }

    /**
     * @return Builder<WishlistItem>
     */
    private function wishlistQuery(Wishlist $wishlist, ?string $search, ?int $categoryId, string $sort): Builder
    {
        $query = ListPresenter::filtered(
            WishlistItem::query()->with(ListPresenter::WITH)->where('wishlist_id', $wishlist->id),
            $search,
            $categoryId,
            $sort,
        );

        // Flagged items float, whatever the sort. A priority flag that does not
        // change the order is a flag that does nothing — and the chosen sort
        // still decides the order within each half.
        return ListPresenter::sorted($query->reorder()->orderByDesc('priority'), $sort);
    }

    /**
     * @return Builder<Blacklist>
     */
    private function blacklistQuery(User $user, ?string $search, ?int $categoryId, string $sort): Builder
    {
        return ListPresenter::filtered(
            Blacklist::query()->with(ListPresenter::WITH)->where('user_id', $user->id),
            $search,
            $categoryId,
            $sort,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projects(User $user): array
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->where('status', ProjectStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(static fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
            ])
            ->all();
    }

    private function ownsWishlist(Request $request, Wishlist $wishlist): void
    {
        abort_unless($wishlist->user_id === $request->user()->id, 403);
    }

    private function ownsList(User $user, int $wishlistId): bool
    {
        return Wishlist::query()->where('user_id', $user->id)->whereKey($wishlistId)->exists();
    }
}
