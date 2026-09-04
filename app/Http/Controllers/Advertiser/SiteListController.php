<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\Wishlist;
use App\Domain\Catalog\Models\WishlistItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The three lists an advertiser keeps about a site: favourites, wishlists and
 * the blacklist.
 *
 * Favourites and the blacklist toggle rather than add, because both are
 * operated from a single control on a row — one heart, one menu item — and a
 * control that only works in one direction leaves people with no way back
 * except a settings page they have to go and find.
 */
class SiteListController extends Controller
{
    public function toggleFavorite(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('view', $website);

        $existing = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('website_id', $website->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('success', "Removed {$website->domain} from favorites.");
        }

        Favorite::query()->create(['user_id' => $request->user()->id, 'website_id' => $website->id]);

        return back()->with('success', "Added {$website->domain} to favorites.");
    }

    public function toggleBlacklist(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('view', $website);

        $existing = Blacklist::query()
            ->where('user_id', $request->user()->id)
            ->where('website_id', $website->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('success', "{$website->domain} is back in your catalog.");
        }

        $reason = trim((string) $request->input('reason'));

        Blacklist::query()->create([
            'user_id' => $request->user()->id,
            'website_id' => $website->id,
            'reason' => $reason === '' ? null : mb_substr($reason, 0, 190),
        ]);

        return back()->with('success', "{$website->domain} is hidden from your catalog.");
    }

    /**
     * Adds a site to a wishlist, creating the advertiser's default one if they
     * have none yet.
     *
     * A wishlist is a shortlist, so the first one is made on demand rather than
     * asking someone to name a list before they can save anything to it.
     */
    public function addToWishlist(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('view', $website);

        $validated = $request->validate([
            'wishlist_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);

        $wishlist = isset($validated['wishlist_id'])
            ? Wishlist::query()
                ->where('id', $validated['wishlist_id'])
                ->where('user_id', $request->user()->id)
                ->first()
            : null;

        $wishlist ??= Wishlist::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'name' => 'Saved sites'],
        );

        // Idempotent: the menu item is one click and clicking twice should not
        // produce two rows nobody asked for.
        WishlistItem::query()->firstOrCreate(
            ['wishlist_id' => $wishlist->id, 'website_id' => $website->id],
            ['note' => $validated['note'] ?? null],
        );

        return back()->with('success', "Saved {$website->domain} to {$wishlist->name}.");
    }
}
