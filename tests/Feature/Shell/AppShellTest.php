<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Wallet;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Projects\Models\Project;
use App\Domain\System\Models\ChangelogEntry;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function shellUser(array $attributes = []): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'last_login_at' => now()->subDay(),
        ...$attributes,
    ]);

    Wallet::query()->create([
        'user_id' => $user->id,
        'available_cents' => 124_000,
        'frozen_cents' => 32_000,
    ]);

    return $user;
}

it('shares the shell with every authenticated page', function (): void {
    $user = shellUser();
    Project::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('shell.projects', 2)
            ->where('shell.balance.availableCents', 124_000)
            ->where('shell.balance.frozenCents', 32_000)
            ->has('shell.counts')
            ->has('shell.version')
            // Null unless a broadcaster is configured; the client then polls.
            ->where('shell.echo', null),
        );
});

it('gives each project a stable colour', function (): void {
    $user = shellUser();
    Project::factory()->create(['user_id' => $user->id]);

    $first = $this->actingAs($user)->get(advertiserUrl('/dashboard'));
    $second = $this->actingAs($user)->get(advertiserUrl('/dashboard'));

    $colourOf = fn ($response): string => $response->viewData('page')['props']['shell']['projects'][0]['color'];

    expect($colourOf($first))->toBe($colourOf($second))
        ->and($colourOf($first))->toStartWith('#');
});

it('counts the cart, favourites, unread conversations and unread changelog', function (): void {
    $user = shellUser();
    $website = Website::factory()->create();
    $cart = Cart::query()->create(['user_id' => $user->id]);

    WebsitePrice::factory()->create([
        'website_id' => $website->id,
        'service_type' => ServiceType::ArticlePlacement,
        'price_cents' => 200_00,
        'writing_fee_cents' => 40_00,
        'express_fee_cents' => 0,
    ]);

    CartItem::query()->create([
        'cart_id' => $cart->id,
        'website_id' => $website->id,
        'service_type' => ServiceType::ArticlePlacement,
        'content_mode' => ContentMode::PublisherWrites,
        // What the line was quoted. The header shows the live price, not this
        // one, so that it cannot disagree with the cart page about the same
        // lines — see CartPricer.
        'unit_price_cents' => 180_00,
    ]);

    Favorite::query()->create(['user_id' => $user->id, 'website_id' => $website->id]);

    $conversation = Conversation::query()->create([
        'user_id' => $user->id,
        'subject' => 'About a placement',
        'last_message_at' => now(),
    ]);

    // Sent by an admin and unread: this is what the badge counts.
    Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'admin',
        'body' => 'We published your post this morning.',
    ]);

    ChangelogEntry::query()->create([
        'title' => 'Something new',
        'slug' => 'something-new',
        'body' => 'A change.',
        'category' => 'new',
        'published_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(advertiserUrl('/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('shell.counts.cart', 1)
            ->where('shell.counts.favorites', 1)
            ->where('shell.counts.conversations', 1)
            ->where('shell.counts.changelog', 1)
            // The live price plus the writing fee this line takes, not the
            // $180 it was quoted at.
            ->where('shell.cart.subtotalCents', 240_00),
        );
});

it('does not count the advertiser\'s own messages as unread', function (): void {
    $user = shellUser();
    $conversation = Conversation::query()->create([
        'user_id' => $user->id,
        'subject' => 'About a placement',
        'last_message_at' => now(),
    ]);

    Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'user',
        'body' => 'Any update on this?',
    ]);

    $this->actingAs($user)
        ->get(advertiserUrl('/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('shell.counts.conversations', 0));
});

it('persists the sidebar state to the users table', function (): void {
    $user = shellUser(['sidebar_collapsed' => false]);

    $this->actingAs($user)
        ->patchJson(advertiserUrl('/shell/sidebar'), ['collapsed' => true])
        ->assertOk();

    expect($user->fresh()->sidebar_collapsed)->toBeTrue();
});

it('serves badge counts for the poll fallback', function (): void {
    $user = shellUser();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/shell/counts'))
        ->assertOk()
        ->assertJsonStructure(['cart', 'conversations', 'changelog', 'favorites']);
});

it('marks the changelog read on open, but still flags what was unread', function (): void {
    $user = shellUser(['changelog_read_at' => null]);

    ChangelogEntry::query()->create([
        'title' => 'Something new',
        'slug' => 'something-new',
        'body' => 'A change.',
        'category' => 'new',
        'published_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($user)->getJson(advertiserUrl('/shell/changelog'))->assertOk();

    // The entry was unread when the drawer opened, so it renders as unread…
    expect($response->json('entries.0.unread'))->toBeTrue()
        // …and is read from now on.
        ->and($user->fresh()->changelog_read_at)->not->toBeNull();

    $this->actingAs($user->fresh())
        ->get(advertiserUrl('/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('shell.counts.changelog', 0));
});

it('scopes the command palette to the signed-in advertiser', function (): void {
    $user = shellUser();
    $other = shellUser();

    Project::factory()->create(['user_id' => $user->id, 'name' => 'Northwind CRM launch']);
    Project::factory()->create(['user_id' => $other->id, 'name' => 'Northwind rival campaign']);

    $response = $this->actingAs($user)->getJson(advertiserUrl('/search?q=Northwind'))->assertOk();

    $titles = collect($response->json('groups'))
        ->flatMap(fn (array $group): array => $group['items'])
        ->pluck('title');

    expect($titles)->toContain('Northwind CRM launch')
        ->and($titles)->not->toContain('Northwind rival campaign');
});

it('does not search on a single character', function (): void {
    $this->actingAs(shellUser())
        ->getJson(advertiserUrl('/search?q=a'))
        ->assertOk()
        ->assertJson(['groups' => []]);
});

it('keeps the shell off the auth screens', function (): void {
    // The shell frames authenticated routes only; a guest has no projects,
    // cart or balance to render.
    $this->get(advertiserUrl('/login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('shell', null));
});
