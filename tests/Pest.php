<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteMetric;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Each surface answers on its own hostname, so a feature test has to say which
 * one it is talking to. These helpers keep that explicit at the call site.
 */
function marketingUrl(string $path = '/'): string
{
    return 'http://'.config('publinza.domains.marketing').$path;
}

function advertiserUrl(string $path = '/'): string
{
    return 'http://'.config('publinza.domains.app').$path;
}

function adminUrl(string $path = ''): string
{
    return 'http://'.config('publinza.domains.marketing').'/'.config('publinza.admin_prefix').$path;
}

/**
 * A verified advertiser. Verification matters: the advertiser surface puts the
 * catalog behind it, so an unverified user is redirected before any of these
 * tests reach the thing they are testing.
 */
function buyer(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

/**
 * A site with everything the catalog reads. Named arguments at the call site
 * keep each test's intent legible against a model with eighteen columns.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $metrics
 * @param  array<string, mixed>  $price
 */
function site(array $attributes = [], array $metrics = [], array $price = []): Website
{
    $website = Website::factory()->create($attributes + ['is_active' => true]);

    WebsiteMetric::factory()->create($metrics + ['website_id' => $website->id, 'fetched_at' => now()]);

    WebsitePrice::factory()->create($price + [
        'website_id' => $website->id,
        'service_type' => ServiceType::ArticlePlacement,
    ]);

    return $website->fresh();
}

/**
 * A cart with one line on it. Named arguments keep each test's intent legible
 * against a line with a dozen columns.
 *
 * @param  array<string, mixed>  $attributes
 */
function line(User $user, Website $website, array $attributes = []): CartItem
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

    return CartItem::query()->create($attributes + [
        'cart_id' => $cart->id,
        'website_id' => $website->id,
        'service_type' => ServiceType::ArticlePlacement,
        'content_mode' => ContentMode::AdvertiserProvides,
        'unit_price_cents' => $website->priceFor(ServiceType::ArticlePlacement)?->price_cents ?? 0,
    ]);
}

/**
 * A site with a known price and both optional fees.
 *
 * @param  array<string, mixed>  $attributes
 */
function priced(int $price, int $writing = 0, int $express = 0, array $attributes = []): Website
{
    $website = Website::factory()->create($attributes + ['is_active' => true]);

    WebsitePrice::factory()->create([
        'website_id' => $website->id,
        'service_type' => ServiceType::ArticlePlacement,
        'price_cents' => $price,
        'writing_fee_cents' => $writing,
        'express_fee_cents' => $express,
    ]);

    return $website->fresh();
}
