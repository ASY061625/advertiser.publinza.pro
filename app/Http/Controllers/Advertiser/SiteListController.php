<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\Wishlist;
use App\Domain\Catalog\Models\WishlistItem;
use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
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

    /**
     * Hides a site, or unhides it.
     *
     * The reason is optional and only ever asked for on the way in. It is for
     * the advertiser's own memory — six months later, "too many outbound
     * links" is the difference between a list they trust and a list they have
     * to rebuild — so it is stored and never required.
     */
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

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:190']]);
        $reason = trim((string) ($validated['reason'] ?? ''));

        Blacklist::query()->create([
            'user_id' => $request->user()->id,
            'website_id' => $website->id,
            'reason' => $reason === '' ? null : $reason,
        ]);

        return back()->with('success', "{$website->domain} is hidden from your catalog.");
    }

    /**
     * Reports a problem with a site.
     *
     * Opens a conversation rather than filing a ticket somewhere the advertiser
     * cannot see. A report with no reply is indistinguishable from a report
     * nobody read, and the inbox is where they already look for answers.
     */
    public function report(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('view', $website);

        $validated = $request->validate(['message' => ['required', 'string', 'min:10', 'max:2000']]);

        $conversation = Conversation::query()->create([
            'user_id' => $request->user()->id,
            'website_id' => $website->id,
            'subject' => "Problem with {$website->domain}",
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => SenderType::User,
            'sender_id' => $request->user()->id,
            'body' => $validated['message'],
        ]);

        return back()->with('success', 'Thanks — we have opened a conversation about this site.');
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
