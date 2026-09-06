<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\ImportBlacklist;
use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Catalog\Models\Wishlist;
use App\Domain\Catalog\Models\WishlistItem;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\CartItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/** A shortlist with one site on it. */
function shortlist(User $user, string $name = 'Q3 finance push'): Wishlist
{
    return Wishlist::query()->create(['user_id' => $user->id, 'name' => $name]);
}

it('opens on favorites and deep-links to each tab', function (): void {
    $user = buyer();

    foreach (['favorites', 'wishlist', 'blacklist'] as $tab) {
        $this->actingAs($user)
            ->get(advertiserUrl("/lists?tab={$tab}"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Lists/Index')
                ->where('tab', $tab),
            );
    }

    // An unknown tab is the first one rather than a 404: the tab says which
    // view to open, and a view cannot be missing.
    $this->actingAs($user)
        ->get(advertiserUrl('/lists?tab=nonsense'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'favorites'));
});

it('counts all three lists whichever one is open', function (): void {
    $user = buyer();
    $list = shortlist($user);

    Favorite::query()->create(['user_id' => $user->id, 'website_id' => site()->id]);
    Favorite::query()->create(['user_id' => $user->id, 'website_id' => site()->id]);
    WishlistItem::query()->create(['wishlist_id' => $list->id, 'website_id' => site()->id]);
    Blacklist::query()->create(['user_id' => $user->id, 'website_id' => site()->id]);

    // Only the open tab's rows are loaded, but the tab strip has to be honest
    // about the other two — so the counts are three cheap aggregates.
    $this->actingAs($user)
        ->get(advertiserUrl('/lists?tab=blacklist'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts.favorites', 2)
            ->where('counts.wishlist', 1)
            ->where('counts.blacklist', 1)
            ->has('rows', 1),
        );
});

it('carries the date added and the site’s own metrics onto every row', function (): void {
    $user = buyer();
    $website = site(['domain' => 'saved.test'], ['monthly_traffic' => 42_000, 'ahrefs_dr' => 61]);

    Favorite::query()->create(['user_id' => $user->id, 'website_id' => $website->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/lists?tab=favorites'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.domain', 'saved.test')
            // The row is a catalog row plus what the list knows, which is what
            // lets /lists reuse the catalog's table rather than fork it.
            ->where('rows.0.traffic', 42_000)
            ->where('rows.0.domainRating', 61)
            ->where('rows.0.addedAt', fn (?string $value): bool => $value !== null),
        );
});

it('searches and filters by category on every tab', function (): void {
    $user = buyer();
    $finance = WebsiteCategory::factory()->create(['name' => 'Finance']);

    $wanted = site(['domain' => 'ledgerwise.com', 'category_id' => $finance->id]);
    $other = site(['domain' => 'gadgetlab.nl']);

    foreach ([$wanted, $other] as $website) {
        Blacklist::query()->create(['user_id' => $user->id, 'website_id' => $website->id]);
    }

    $domains = fn (string $query): array => collect(
        $this->actingAs($user)->get(advertiserUrl("/lists?tab=blacklist&{$query}"))->viewData('page')['props']['rows'],
    )->pluck('domain')->all();

    expect($domains('q=ledger'))->toBe(['ledgerwise.com'])
        ->and($domains("category={$finance->id}"))->toBe(['ledgerwise.com'])
        ->and($domains('q=nothingmatches'))->toBe([]);
});

it('sorts by when it was listed and by domain', function (): void {
    $user = buyer();

    $first = site(['domain' => 'zebra.test']);
    $second = site(['domain' => 'alpha.test']);

    // created_at is not fillable, so it is set after the fact rather than
    // widening the model's mass-assignment surface for a test.
    Favorite::query()->create(['user_id' => $user->id, 'website_id' => $first->id])
        ->forceFill(['created_at' => now()->subDay()])->save();
    Favorite::query()->create(['user_id' => $user->id, 'website_id' => $second->id]);

    $domains = fn (string $sort): array => collect(
        $this->actingAs($user)->get(advertiserUrl("/lists?sort={$sort}"))->viewData('page')['props']['rows'],
    )->pluck('domain')->all();

    expect($domains('newest'))->toBe(['alpha.test', 'zebra.test'])
        ->and($domains('oldest'))->toBe(['zebra.test', 'alpha.test'])
        ->and($domains('domain'))->toBe(['alpha.test', 'zebra.test']);
});

it('floats flagged wishlist items above the rest, whatever the sort', function (): void {
    $user = buyer();
    $list = shortlist($user);

    $ordinary = site(['domain' => 'aaa.test']);
    $flagged = site(['domain' => 'zzz.test']);

    WishlistItem::query()->create(['wishlist_id' => $list->id, 'website_id' => $ordinary->id]);
    WishlistItem::query()->create([
        'wishlist_id' => $list->id,
        'website_id' => $flagged->id,
        'priority' => true,
    ]);

    // A priority flag that does not change the order is a flag that does
    // nothing — so it wins even against an A–Z sort that would put it last.
    $domains = collect(
        $this->actingAs($user)
            ->get(advertiserUrl("/lists?tab=wishlist&list={$list->id}&sort=domain"))
            ->viewData('page')['props']['rows'],
    )->pluck('domain')->all();

    expect($domains)->toBe(['zzz.test', 'aaa.test']);
});

it('sums a shortlist and averages only what has been measured', function (): void {
    $user = buyer();
    $list = shortlist($user);

    $measured = site([], ['ahrefs_dr' => 60, 'monthly_traffic' => 30_000], ['price_cents' => 200_00]);
    $alsoMeasured = site([], ['ahrefs_dr' => 40, 'monthly_traffic' => 10_000], ['price_cents' => 100_00]);
    // Never crawled, so it has no DR to average.
    $unmeasured = Website::factory()->create(['is_active' => true]);
    WebsitePrice::factory()->create([
        'website_id' => $unmeasured->id,
        'service_type' => ServiceType::ArticlePlacement,
        'price_cents' => 50_00,
    ]);

    foreach ([$measured, $alsoMeasured, $unmeasured] as $website) {
        WishlistItem::query()->create(['wishlist_id' => $list->id, 'website_id' => $website->id]);
    }

    $this->actingAs($user)
        ->get(advertiserUrl("/lists?tab=wishlist&list={$list->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.siteCount', 3)
            ->where('summary.totalCents', 350_00)
            // The average is of the two that were measured, not of three with a
            // zero in it — which would report a number lower than any site here.
            ->where('summary.averageDr', 50)
            ->where('summary.measured', 2)
            ->where('summary.totalTraffic', 40_000),
        );
});

it('creates, renames, duplicates and deletes a wishlist', function (): void {
    $user = buyer();

    $this->actingAs($user)->post(advertiserUrl('/lists/wishlists'), ['name' => 'Q3 finance push'])
        ->assertRedirect();

    $list = Wishlist::query()->firstOrFail();
    WishlistItem::query()->create([
        'wishlist_id' => $list->id,
        'website_id' => site()->id,
        'note' => 'Best DR in the set',
        'priority' => true,
    ]);

    $this->actingAs($user)->patch(advertiserUrl("/lists/wishlists/{$list->id}"), ['name' => 'Q4 push']);
    expect($list->fresh()->name)->toBe('Q4 push');

    $this->actingAs($user)->post(advertiserUrl("/lists/wishlists/{$list->id}/duplicate"))->assertRedirect();

    $copy = Wishlist::query()->where('name', 'Q4 push (copy)')->firstOrFail();
    $copied = WishlistItem::query()->where('wishlist_id', $copy->id)->firstOrFail();

    // The thinking comes with the copy. A duplicate is somebody about to make a
    // variant of a plan, and stripping the notes would leave them retyping it.
    expect($copied->note)->toBe('Best DR in the set')->and($copied->priority)->toBeTrue();

    $this->actingAs($user)->delete(advertiserUrl("/lists/wishlists/{$list->id}"))->assertRedirect();

    expect(Wishlist::query()->whereKey($list->id)->exists())->toBeFalse()
        // Deleting a list takes its items and nothing else.
        ->and(WishlistItem::query()->where('wishlist_id', $copy->id)->count())->toBe(1);
});

it('saves a note and a priority flag inline', function (): void {
    $user = buyer();
    $list = shortlist($user);
    $item = WishlistItem::query()->create(['wishlist_id' => $list->id, 'website_id' => site()->id]);

    $this->actingAs($user)
        ->patch(advertiserUrl("/lists/items/{$item->id}"), ['note' => 'Cheapest in the set', 'priority' => true])
        ->assertRedirect();

    expect($item->fresh()->note)->toBe('Cheapest in the set')->and($item->fresh()->priority)->toBeTrue();
});

it('keeps one advertiser out of another’s list', function (): void {
    $user = buyer();
    $theirs = shortlist(buyer(), 'Not yours');
    $item = WishlistItem::query()->create(['wishlist_id' => $theirs->id, 'website_id' => site()->id]);

    $this->actingAs($user)->patch(advertiserUrl("/lists/wishlists/{$theirs->id}"), ['name' => 'Mine now'])
        ->assertForbidden();

    $this->actingAs($user)->patch(advertiserUrl("/lists/items/{$item->id}"), ['note' => 'nope'])
        ->assertForbidden();

    expect($theirs->fresh()->name)->toBe('Not yours')->and($item->fresh()->note)->toBeNull();
});

it('edits a blacklist reason in place', function (): void {
    $user = buyer();
    $entry = Blacklist::query()->create(['user_id' => $user->id, 'website_id' => site()->id]);

    $this->actingAs($user)
        ->patch(advertiserUrl("/lists/blacklist/{$entry->id}"), ['reason' => 'Too many outbound links'])
        ->assertRedirect();

    expect($entry->fresh()->reason)->toBe('Too many outbound links');

    $this->flushSession();

    $this->actingAs(buyer())
        ->patch(advertiserUrl("/lists/blacklist/{$entry->id}"), ['reason' => 'not mine'])
        ->assertForbidden();
});

it('unblocks every selected site at once, and only the caller’s own', function (): void {
    $user = buyer();
    $other = buyer();

    $mine = collect([site(), site()])->map(fn ($website) => Blacklist::query()->create([
        'user_id' => $user->id,
        'website_id' => $website->id,
    ]));

    $theirs = Blacklist::query()->create(['user_id' => $other->id, 'website_id' => site()->id]);

    $this->actingAs($user)
        ->post(advertiserUrl('/lists/blacklist/remove'), [
            // Their entry is in the payload too: the scope has to come from the
            // signed-in user, not from what the browser sent.
            'website_ids' => [...$mine->pluck('website_id'), $theirs->website_id],
        ])
        ->assertRedirect();

    expect(Blacklist::query()->whereIn('id', $mine->pluck('id'))->count())->toBe(0)
        ->and($theirs->fresh())->not->toBeNull();
});

it('imports a paste of domains and reports all three outcomes', function (): void {
    $user = buyer();

    $known = site(['domain' => 'known.test']);
    $already = site(['domain' => 'already.test']);

    Blacklist::query()->create(['user_id' => $user->id, 'website_id' => $already->id]);

    $this->actingAs($user)->post(advertiserUrl('/lists/blacklist/import'), [
        'domains' => "https://www.known.test/blog\nalready.test\nnowhere.test\n\n",
        'reason' => 'Exclusion sheet',
    ])->assertRedirect();

    $report = session('importReport');

    // Three named groups, not one count. "42 imported" out of 50 lines leaves
    // somebody to work out which eight were dropped and why.
    expect($report['blocked'])->toBe(['known.test'])
        ->and($report['already'])->toBe(['already.test'])
        ->and($report['unmatched'])->toBe(['nowhere.test'])
        ->and(Blacklist::query()->where('website_id', $known->id)->value('reason'))->toBe('Exclusion sheet');
});

it('reads a domain however it was pasted', function (): void {
    $parsed = app(ImportBlacklist::class)->parse(
        "HTTPS://WWW.Example.com/blog/post\n example.com \nhttp://second.io,third.net;fourth.org\n\n",
    );

    // People paste URLs, www prefixes, trailing paths and spreadsheet rows.
    // Rejecting those would be correct and would make the feature useless for
    // the input it actually receives. Duplicates collapse.
    expect($parsed)->toBe(['example.com', 'second.io', 'third.net', 'fourth.org']);
});

it('moves a site between lists in one action, and puts it back', function (): void {
    $user = buyer();
    $list = shortlist($user);
    $website = site(['domain' => 'moving.test']);

    WishlistItem::query()->create([
        'wishlist_id' => $list->id,
        'website_id' => $website->id,
        'note' => 'Was on the shortlist',
        'priority' => true,
    ]);

    $this->actingAs($user)->post(advertiserUrl('/lists/move'), [
        'website_id' => $website->id,
        'from' => 'wishlist',
        'to' => 'blacklist',
    ])->assertRedirect();

    expect(WishlistItem::query()->count())->toBe(0)
        ->and(Blacklist::query()->where('website_id', $website->id)->exists())->toBeTrue();

    $moved = session('moved');

    // The undo payload carries the note, because it is gone from the database
    // the moment the row is deleted and re-reading it would find nothing.
    expect($moved['note'])->toBe('Was on the shortlist')
        ->and($moved['priority'])->toBeTrue()
        ->and($moved['wishlistId'])->toBe($list->id);

    $this->actingAs($user)->post(advertiserUrl('/lists/move/undo'), $moved)->assertRedirect();

    $restored = WishlistItem::query()->firstOrFail();

    expect(Blacklist::query()->count())->toBe(0)
        ->and($restored->wishlist_id)->toBe($list->id)
        ->and($restored->note)->toBe('Was on the shortlist')
        ->and($restored->priority)->toBeTrue();
});

it('carries a blacklist reason through a move and back', function (): void {
    $user = buyer();
    $website = site();

    Blacklist::query()->create([
        'user_id' => $user->id,
        'website_id' => $website->id,
        'reason' => 'Too many outbound links',
    ]);

    $this->actingAs($user)->post(advertiserUrl('/lists/move'), [
        'website_id' => $website->id,
        'from' => 'blacklist',
        'to' => 'favorites',
    ]);

    expect(Favorite::query()->count())->toBe(1)->and(Blacklist::query()->count())->toBe(0);

    $this->actingAs($user)->post(advertiserUrl('/lists/move/undo'), session('moved'));

    expect(Blacklist::query()->value('reason'))->toBe('Too many outbound links')
        ->and(Favorite::query()->count())->toBe(0);
});

it('creates a wishlist on demand when a site is moved into one', function (): void {
    $user = buyer();
    $website = site();

    Favorite::query()->create(['user_id' => $user->id, 'website_id' => $website->id]);

    $this->actingAs($user)->post(advertiserUrl('/lists/move'), [
        'website_id' => $website->id,
        'from' => 'favorites',
        'to' => 'wishlist',
    ])->assertRedirect();

    // Making somebody name a list before they can move one site into it is a
    // step that exists only because the schema wanted a parent row.
    expect(Wishlist::query()->value('name'))->toBe('Saved sites')
        ->and(WishlistItem::query()->count())->toBe(1);
});

it('adds a whole list to the cart, skipping what cannot be bought', function (): void {
    $user = buyer();
    $project = Project::factory()->for($user, 'owner')->create();

    $buyable = site([], [], ['price_cents' => 200_00]);
    $alsoBuyable = site([], [], ['price_cents' => 150_00]);
    // The publisher withdrew the service after it was shortlisted.
    $withdrawn = Website::factory()->create(['is_active' => true]);

    $this->actingAs($user)->post(advertiserUrl('/lists/cart'), [
        'website_ids' => [$buyable->id, $alsoBuyable->id, $withdrawn->id],
        'project_id' => $project->id,
    ])->assertRedirect();

    // Refusing the other two because of the third would be the wrong trade for
    // a shortlist assembled over weeks.
    expect(CartItem::query()->count())->toBe(2)
        ->and(session('success'))->toContain('1 skipped');
});

it('does not add the same site to the cart twice', function (): void {
    $user = buyer();
    $project = Project::factory()->for($user, 'owner')->create();
    $website = site([], [], ['price_cents' => 200_00]);

    foreach ([1, 2] as $_) {
        $this->actingAs($user)->post(advertiserUrl('/lists/cart'), [
            'website_ids' => [$website->id],
            'project_id' => $project->id,
        ]);
    }

    expect(CartItem::query()->count())->toBe(1);
});

it('will not add to a project somebody else owns', function (): void {
    $user = buyer();
    $theirs = Project::factory()->for(buyer(), 'owner')->create();

    $this->actingAs($user)->post(advertiserUrl('/lists/cart'), [
        'website_ids' => [site()->id],
        'project_id' => $theirs->id,
    ])->assertRedirect()->assertSessionHas('error');

    expect(CartItem::query()->count())->toBe(0);
});

it('exports the list the screen is showing, filters and all', function (): void {
    $user = buyer();
    $finance = WebsiteCategory::factory()->create(['name' => 'Finance']);

    $wanted = site(['domain' => 'ledgerwise.com', 'category_id' => $finance->id], ['ahrefs_dr' => 61]);
    $other = site(['domain' => 'gadgetlab.nl']);

    foreach ([$wanted, $other] as $website) {
        Favorite::query()->create(['user_id' => $user->id, 'website_id' => $website->id]);
    }

    $body = $this->actingAs($user)
        ->get(advertiserUrl("/lists/export?tab=favorites&category={$finance->id}"))
        ->assertOk()
        ->streamedContent();

    // An export that ignores the search is an export of a different list from
    // the one somebody is looking at.
    expect($body)->toContain('ledgerwise.com')
        ->and($body)->not->toContain('gadgetlab.nl')
        ->and($body)->toContain('Domain,Category');
});

it('gives the blacklist export its own columns', function (): void {
    $user = buyer();

    Blacklist::query()->create([
        'user_id' => $user->id,
        'website_id' => site(['domain' => 'blocked.test'])->id,
        'reason' => 'Too many outbound links',
    ]);

    $body = $this->actingAs($user)
        ->get(advertiserUrl('/lists/export?tab=blacklist'))
        ->streamedContent();

    expect($body)->toContain('Reason,"Blocked by"')
        ->and($body)->toContain('Too many outbound links')
        ->and($body)->toContain('advertiser');
});
