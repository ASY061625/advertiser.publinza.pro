<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteMetric;
use App\Domain\Trading\Enums\ServiceType;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function catalogSite(int $traffic, int $dr, int $priceCents, bool $active = true): Website
{
    $website = Website::factory()->create(['is_active' => $active]);

    WebsiteMetric::query()->create([
        'website_id' => $website->id,
        'monthly_traffic' => $traffic,
        'ahrefs_dr' => $dr,
        'moz_da' => $dr - 3,
        'spam_score' => 4,
        'fetched_at' => now(),
    ]);

    $website->prices()->create([
        'service_type' => ServiceType::ArticlePlacement,
        'price_cents' => $priceCents,
    ]);

    return $website;
}

it('requires authentication', function (): void {
    $this->get(advertiserUrl('/catalog'))->assertRedirect();
});

it('lists active sites with the catalog-wide metric ranges', function (): void {
    catalogSite(traffic: 1_000, dr: 20, priceCents: 5_000);
    catalogSite(traffic: 90_000, dr: 70, priceCents: 40_000);
    catalogSite(traffic: 500_000, dr: 90, priceCents: 90_000, active: false);

    $this->actingAs(User::factory()->create())
        ->get(advertiserUrl('/catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Catalog/Index')
            ->has('sites.data', 2)
            // The quant-bars scale against active sites only, so the inactive
            // 500k row must not stretch the range.
            ->where('ranges.traffic', [1_000, 90_000]),
        );
});

it('hides sites the advertiser has blacklisted', function (): void {
    $user = User::factory()->create();
    $wanted = catalogSite(traffic: 5_000, dr: 30, priceCents: 10_000);
    $unwanted = catalogSite(traffic: 6_000, dr: 32, priceCents: 11_000);

    Blacklist::query()->create(['user_id' => $user->id, 'website_id' => $unwanted->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sites.data', 1)
            ->where('sites.data.0.domain', $wanted->domain),
        );
});

it('renders the advertiser surface into its own root template', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(advertiserUrl('/catalog'))
        ->assertOk()
        // The advertiser page never references the admin entry point.
        ->assertDontSee('resources/js/admin/main.tsx');
});
