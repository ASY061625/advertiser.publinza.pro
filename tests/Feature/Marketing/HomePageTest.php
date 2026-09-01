<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteMetric;
use App\Domain\System\Models\BlogPost;
use App\Domain\Trading\Enums\ServiceType;
use Illuminate\Support\Facades\Cache;

function marketingSite(int $traffic, int $dr, int $priceCents, bool $active = true): Website
{
    $website = Website::factory()->create(['is_active' => $active]);

    WebsiteMetric::query()->create([
        'website_id' => $website->id,
        'monthly_traffic' => $traffic,
        'ahrefs_dr' => $dr,
        'moz_da' => max(1, $dr - 4),
        'spam_score' => 3,
        'fetched_at' => now(),
    ]);

    $website->prices()->create([
        'service_type' => ServiceType::ArticlePlacement,
        'price_cents' => $priceCents,
    ]);

    return $website;
}

beforeEach(function (): void {
    // The marketing pages cache aggressively; a stale entry from another test
    // would make these assertions meaningless.
    Cache::flush();
});

it('renders the home page as server-rendered HTML', function (): void {
    marketingSite(traffic: 400_000, dr: 88, priceCents: 120_000);

    $response = $this->get(marketingUrl('/'));

    $response->assertOk()
        // The content is in the response body, not fetched by a client bundle.
        ->assertSee('Every site in this catalog belongs to us')
        ->assertSee('Three steps from signing up to a live link')
        ->assertDontSee('data-page', escape: false);
});

it('shows real catalog rows in the hero', function (): void {
    $website = marketingSite(traffic: 400_000, dr: 88, priceCents: 120_000);
    marketingSite(traffic: 5_000, dr: 30, priceCents: 9_000);

    $this->get(marketingUrl('/'))
        ->assertOk()
        ->assertSee($website->domain)
        ->assertSee('$1,200.00');
});

it('never shows an inactive site', function (): void {
    $hidden = marketingSite(traffic: 900_000, dr: 95, priceCents: 200_000, active: false);

    $this->get(marketingUrl('/'))->assertOk()->assertDontSee($hidden->domain);
    $this->get(marketingUrl('/catalog'))->assertOk()->assertDontSee($hidden->domain);
});

it('carries the structured data crawlers need', function (): void {
    marketingSite(traffic: 400_000, dr: 88, priceCents: 120_000);

    $response = $this->get(marketingUrl('/'));

    $response->assertOk()
        ->assertSee('"@type":"Organization"', escape: false)
        ->assertSee('"@type":"WebSite"', escape: false)
        ->assertSee('"@type":"FAQPage"', escape: false)
        // Every FAQ answer must be in the HTML, not revealed by script.
        ->assertSee('Every domain listed is owned and operated by Publinza Media Ltd');
});

it('does not load analytics before consent', function (): void {
    config(['services.analytics.script' => 'https://analytics.example/script.js']);
    marketingSite(traffic: 400_000, dr: 88, priceCents: 120_000);

    $response = $this->get(marketingUrl('/'));

    // The URL is present as data for the island to use, but never as a script
    // tag the browser would fetch on load.
    $response->assertOk()
        ->assertSee('data-analytics="https://analytics.example/script.js"', escape: false)
        ->assertDontSee('<script src="https://analytics.example/script.js"', escape: false);
});

it('serves a sitemap listing the public pages and published posts', function (): void {
    BlogPost::query()->create([
        'title' => 'A published post',
        'slug' => 'a-published-post',
        'excerpt' => 'Short summary.',
        'body_html' => '<p>Body.</p>',
        'author' => 'Ruth Kelleher',
        'reading_minutes' => 3,
        'published_at' => now()->subDay(),
    ]);

    BlogPost::query()->create([
        'title' => 'A scheduled post',
        'slug' => 'a-scheduled-post',
        'excerpt' => 'Not out yet.',
        'body_html' => '<p>Body.</p>',
        'author' => 'Ruth Kelleher',
        'reading_minutes' => 3,
        'published_at' => now()->addWeek(),
    ]);

    $this->get(marketingUrl('/sitemap.xml'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('catalog'), escape: false)
        ->assertSee('a-published-post')
        // A scheduled post is not public, so it is not in the sitemap.
        ->assertDontSee('a-scheduled-post');
});

it('keeps a scheduled post out of the blog and returns 404 for it', function (): void {
    BlogPost::query()->create([
        'title' => 'A scheduled post',
        'slug' => 'a-scheduled-post',
        'excerpt' => 'Not out yet.',
        'body_html' => '<p>Body.</p>',
        'author' => 'Ruth Kelleher',
        'reading_minutes' => 3,
        'published_at' => now()->addWeek(),
    ]);

    $this->get(marketingUrl('/blog'))->assertOk()->assertDontSee('A scheduled post');
    $this->get(marketingUrl('/blog/a-scheduled-post'))->assertNotFound();
});

it('does not serve advertiser routes on the apex domain', function (): void {
    $this->get(marketingUrl('/projects'))->assertNotFound();
});

it('accepts a contact message and rejects a short one', function (): void {
    $this->post(marketingUrl('/contact'), [
        'name' => 'Dana Okafor',
        'email' => 'dana@northwind.test',
        'message' => 'We are looking for placements in the finance category this quarter.',
    ])->assertRedirect()->assertSessionHas('status');

    $this->post(marketingUrl('/contact'), [
        'name' => 'Dana Okafor',
        'email' => 'dana@northwind.test',
        'message' => 'Too short',
    ])->assertSessionHasErrors('message');
});

it('silently swallows a submission that fills the honeypot', function (): void {
    $this->post(marketingUrl('/contact'), [
        'name' => 'Bot',
        'email' => 'bot@example.test',
        'message' => 'Buy cheap links from us right now, best prices anywhere.',
        'website' => 'http://spam.example',
    ])->assertRedirect()->assertSessionHas('status');
});
