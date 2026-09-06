<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\Wishlist;
use App\Domain\Catalog\Models\WishlistItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moving a site from one of the advertiser's lists to another.
 *
 * One action rather than a remove-then-add per pair, because the pairs multiply
 * — six directions between three lists — and because a move has to be one thing
 * for undo to mean anything. Both halves share a transaction: a site that left
 * the favourites and never reached the blacklist is a site the advertiser has
 * lost track of.
 *
 * The return value is what the undo toast needs to put it back.
 */
final class MoveBetweenLists
{
    public const LISTS = ['favorites', 'wishlist', 'blacklist'];

    /**
     * @return array{from: string, to: string, websiteId: int, domain: string, wishlistId: int|null, note: string|null, priority: bool, reason: string|null}
     */
    public function handle(User $user, Website $website, string $from, string $to, ?int $wishlistId = null): array
    {
        if ($from === $to) {
            throw new RuntimeException('That site is already there.');
        }

        return DB::transaction(function () use ($user, $website, $from, $to, $wishlistId): array {
            // Read before removing: undo has to be able to restore the note and
            // the reason, and both are gone the moment the row is deleted.
            $carried = $this->remove($user, $website, $from);

            $this->add($user, $website, $to, $wishlistId, $carried);

            return $carried + ['from' => $from, 'to' => $to, 'websiteId' => $website->id, 'domain' => $website->domain];
        });
    }

    /**
     * Puts back what a move took away.
     *
     * @param  array<string, mixed>  $moved  What handle() returned.
     */
    public function undo(User $user, array $moved): void
    {
        $website = Website::query()->find($moved['websiteId'] ?? null);

        if ($website === null) {
            return;
        }

        DB::transaction(function () use ($user, $website, $moved): void {
            $this->remove($user, $website, (string) $moved['to']);
            $this->add($user, $website, (string) $moved['from'], $moved['wishlistId'] ?? null, $moved);
        });
    }

    /**
     * @return array{wishlistId: int|null, note: string|null, priority: bool, reason: string|null}
     */
    private function remove(User $user, Website $website, string $list): array
    {
        $carried = ['wishlistId' => null, 'note' => null, 'priority' => false, 'reason' => null];

        if ($list === 'favorites') {
            Favorite::query()->where('user_id', $user->id)->where('website_id', $website->id)->delete();

            return $carried;
        }

        if ($list === 'blacklist') {
            $row = Blacklist::query()->where('user_id', $user->id)->where('website_id', $website->id)->first();
            $carried['reason'] = $row?->reason;
            $row?->delete();

            return $carried;
        }

        $item = WishlistItem::query()
            ->whereIn('wishlist_id', $this->wishlistIds($user))
            ->where('website_id', $website->id)
            ->first();

        if ($item !== null) {
            $carried['wishlistId'] = $item->wishlist_id;
            $carried['note'] = $item->note;
            $carried['priority'] = $item->priority;
            $item->delete();
        }

        return $carried;
    }

    /**
     * @param  array<string, mixed>  $carried
     */
    private function add(User $user, Website $website, string $list, ?int $wishlistId, array $carried): void
    {
        if ($list === 'favorites') {
            Favorite::query()->firstOrCreate(['user_id' => $user->id, 'website_id' => $website->id]);

            return;
        }

        if ($list === 'blacklist') {
            Blacklist::query()->updateOrCreate(
                ['user_id' => $user->id, 'website_id' => $website->id],
                ['reason' => $carried['reason'] ?? null, 'blocked_by' => 'advertiser'],
            );

            return;
        }

        $target = $wishlistId ?? $carried['wishlistId'] ?? null;

        $wishlist = $target === null
            ? null
            : Wishlist::query()->where('user_id', $user->id)->find($target);

        // Created on demand, as everywhere else: a shortlist is a shortlist, and
        // making somebody name a list before they can move one site into it is
        // a step that exists only because the schema wanted a parent row.
        $wishlist ??= Wishlist::query()->firstOrCreate(['user_id' => $user->id, 'name' => 'Saved sites']);

        WishlistItem::query()->firstOrCreate(
            ['wishlist_id' => $wishlist->id, 'website_id' => $website->id],
            ['note' => $carried['note'] ?? null, 'priority' => (bool) ($carried['priority'] ?? false)],
        );
    }

    /**
     * @return list<int>
     */
    private function wishlistIds(User $user): array
    {
        return Wishlist::query()->where('user_id', $user->id)->pluck('id')->all();
    }
}
