<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\GetWebsiteDetail;
use App\Domain\Catalog\Enums\MetricSource;
use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteMetric;
use App\Domain\Catalog\Models\WebsiteSamplePost;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use Inertia\Testing\AssertableInertia;

/**
 * The one tile out of nine that a test wants to look at.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function tile(array $payload, string $key): array
{
    return collect($payload['metrics'])->firstWhere('key', $key) ?? [];
}

it('answers the same address as JSON for the drawer and as a page for a browser', function (): void {
    $user = buyer();
    $website = site(['domain' => 'dual.test', 'guidelines' => '<p>No affiliate links.</p>']);

    $this->actingAs($user)
        ->getJson(advertiserUrl("/catalog/website/{$website->slug}"))
        ->assertOk()
        ->assertJsonPath('domain', 'dual.test')
        ->assertJsonPath('homepage', 'https://dual.test')
        // The drawer gets what the buy popover needs in the same response, so
        // opening a row is one request rather than two.
        ->assertJsonStructure(['buying' => ['folders', 'landingPages']]);

    $this->actingAs($user)
        ->get(advertiserUrl("/catalog/website/{$website->slug}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Catalog/Website')
            ->where('site.domain', 'dual.test')
            // The standalone page has no catalog behind it, so it carries the
            // ranges the quant-bars are scaled against.
            ->has('ranges.traffic'),
        );
});

it('gives every metric tile its own source and date', function (): void {
    $user = buyer();
    $website = site([], [
        'monthly_traffic' => 42_000,
        'ahrefs_dr' => 61,
        'source' => MetricSource::Ahrefs,
        'fetched_at' => now()->subDays(3),
    ]);

    $payload = app(GetWebsiteDetail::class)->handle($website, $user, null);

    expect($payload['metrics'])->toHaveCount(9)
        ->and(tile($payload, 'traffic')['value'])->toBe(42_000)
        ->and(tile($payload, 'traffic')['source'])->toBe('ahrefs')
        ->and(tile($payload, 'traffic')['fetchedAt'])->not->toBeNull();
});

it('keeps an unmeasured site’s tiles in the grid rather than dropping them', function (): void {
    $user = buyer();

    // No metric row at all — which is how "nobody has measured this" is
    // actually stored, the columns themselves being non-null with a zero
    // default. A site nobody has crawled still gets nine tiles, because a grid
    // that changes shape per site loses the fact that nobody has looked.
    $website = Website::factory()->create(['is_active' => true]);

    $payload = app(GetWebsiteDetail::class)->handle($website, $user, null);

    expect($payload['metrics'])->toHaveCount(9)
        ->and(tile($payload, 'organicKeywords')['value'])->toBeNull()
        ->and(tile($payload, 'organicKeywords')['source'])->toBeNull()
        ->and(tile($payload, 'traffic')['sparkline'])->toBeNull();
});

it('draws a sparkline from one point per month, and none from a single point', function (): void {
    $user = buyer();
    $website = site([], ['monthly_traffic' => 10_000, 'fetched_at' => now()->subMonths(3)]);

    // Two rows in the same month: the newest wins, so three months of history
    // is three points rather than four.
    WebsiteMetric::factory()->create([
        'website_id' => $website->id,
        'monthly_traffic' => 20_000,
        'fetched_at' => now()->subMonths(2),
    ]);
    WebsiteMetric::factory()->create([
        'website_id' => $website->id,
        'monthly_traffic' => 25_000,
        'fetched_at' => now()->subMonths(2)->addDays(2),
    ]);
    WebsiteMetric::factory()->create([
        'website_id' => $website->id,
        'monthly_traffic' => 40_000,
        'fetched_at' => now(),
    ]);

    $payload = app(GetWebsiteDetail::class)->handle($website->fresh(), $user, null);

    expect(tile($payload, 'traffic')['sparkline'])->toBe([10_000, 25_000, 40_000]);

    $lonely = site([], ['monthly_traffic' => 5_000, 'fetched_at' => now()]);
    $second = app(GetWebsiteDetail::class)->handle($lonely, $user, null);

    // One point is not a trend, and a flat line reads as one.
    expect(tile($second, 'traffic')['sparkline'])->toBeNull();
});

it('takes country shares as a percentage of all traffic, not of the eight shown', function (): void {
    $user = buyer();
    // Ten countries, so the eight that are shown are genuinely a subset: the
    // shares must still be read against the whole hundred, or eight bars that
    // cover 90% of the traffic would each be inflated to fill the bar.
    $website = site([], [
        'traffic_by_country' => [
            'us' => 40, 'gb' => 20, 'de' => 10, 'fr' => 8, 'es' => 5,
            'it' => 3, 'nl' => 2, 'pl' => 2, 'se' => 5, 'no' => 5,
        ],
    ]);

    $payload = app(GetWebsiteDetail::class)->handle($website, $user, null);

    $rows = collect($payload['trafficByCountry']);

    expect($rows)->toHaveCount(8)
        ->and($rows->firstWhere('code', 'US')['percent'])->toBe(40.0)
        // Eight bars adding to 96, not to 100 — which is what lets the view say
        // "and 4% elsewhere" instead of implying the world is eight countries.
        ->and(round($rows->sum('percent'), 1))->toBe(96.0);
});

it('splits every sensitive topic into accepted and refused', function (): void {
    $user = buyer();
    $casino = SensitiveTopic::factory()->create(['name' => 'Casino', 'slug' => 'casino']);
    SensitiveTopic::factory()->create(['name' => 'Crypto', 'slug' => 'crypto']);

    $website = site(['accepts_sensitive_topics' => [$casino->slug]]);

    $payload = app(GetWebsiteDetail::class)->handle($website, $user, null);

    // Both halves, always: "refuses crypto" and "nobody asked about crypto" are
    // opposite answers for anyone shopping on it.
    expect(collect($payload['topics']['accepted'])->pluck('slug')->all())->toBe(['casino'])
        ->and(collect($payload['topics']['refused'])->pluck('slug')->all())->toBe(['crypto']);
});

it('shows this advertiser their own placements and nobody else’s', function (): void {
    $user = buyer();
    $stranger = buyer();
    $website = site(['domain' => 'history.test']);

    $mine = Project::factory()->for($user, 'owner')->create(['name' => 'Ledgerly']);

    Post::factory()->create([
        'user_id' => $user->id,
        'project_id' => $mine->id,
        'website_id' => $website->id,
        'anchor_text' => 'best invoicing app',
        'status' => PostStatus::Posted,
        'published_url' => 'https://history.test/best-invoicing-app',
    ]);

    Post::factory()->create([
        'user_id' => $stranger->id,
        'project_id' => Project::factory()->for($stranger, 'owner')->create()->id,
        'website_id' => $website->id,
    ]);

    $payload = app(GetWebsiteDetail::class)->handle($website, $user, null);

    expect($payload['myHistory'])->toHaveCount(1)
        ->and($payload['myHistory'][0]['project'])->toBe('Ledgerly')
        ->and($payload['myHistory'][0]['anchorText'])->toBe('best invoicing app');
});

it('shows at most three sample posts', function (): void {
    $user = buyer();
    $website = site();

    WebsiteSamplePost::factory()->count(5)->create(['website_id' => $website->id]);

    $payload = app(GetWebsiteDetail::class)->handle($website->fresh(), $user, null);

    expect($payload['samplePosts'])->toHaveCount(3);
});

it('offers the project’s folders and landing pages only in buying mode', function (): void {
    $user = buyer();
    $website = site(['domain' => 'buying.test']);
    $project = Project::factory()->for($user, 'owner')->create();
    $folder = ProjectFolder::query()->create([
        'project_id' => $project->id,
        'name' => 'Blog',
        'sort_order' => 0,
    ]);

    LandingPage::query()->create([
        'project_id' => $project->id,
        'folder_id' => $folder->id,
        'anchor_text' => 'invoicing software',
        'url' => 'https://ledgerly.test/invoicing',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->getJson(advertiserUrl("/catalog/website/{$website->slug}?project={$project->id}"))
        ->assertOk()
        ->assertJsonPath('buying.folders.0.name', 'Blog')
        ->assertJsonPath('buying.landingPages.0.anchorText', 'invoicing software')
        ->assertJsonPath('buying.landingPages.0.folderId', $folder->id);

    // Browse mode has no project to configure an order against, which is also
    // what disables the button.
    $this->actingAs($user)
        ->getJson(advertiserUrl("/catalog/website/{$website->slug}"))
        ->assertOk()
        ->assertJsonPath('buying.folders', [])
        ->assertJsonPath('buying.landingPages', []);
});

it('stores the reason someone blacklisted a site, and forgets it on the way back', function (): void {
    $user = buyer();
    $website = site(['domain' => 'hidden.test']);

    $this->actingAs($user)
        ->post(advertiserUrl("/sites/{$website->slug}/blacklist"), ['reason' => 'Too many outbound links'])
        ->assertRedirect();

    expect(Blacklist::query()->where('user_id', $user->id)->value('reason'))->toBe('Too many outbound links');

    // The same control undoes it, and un-hiding asks for nothing.
    $this->actingAs($user)->post(advertiserUrl("/sites/{$website->slug}/blacklist"))->assertRedirect();

    expect(Blacklist::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('opens a conversation when a site is reported', function (): void {
    $user = buyer();
    $website = site(['domain' => 'broken.test']);

    $this->actingAs($user)
        ->post(advertiserUrl("/sites/{$website->slug}/report"), [
            'message' => 'The DR on this listing is 46; Ahrefs says 12.',
        ])
        ->assertRedirect();

    $conversation = Conversation::query()->where('user_id', $user->id)->first();

    // A report filed somewhere the advertiser cannot see it is
    // indistinguishable from a report nobody read.
    expect($conversation)->not->toBeNull()
        ->and($conversation->website_id)->toBe($website->id)
        ->and($conversation->subject)->toBe('Problem with broken.test')
        ->and(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(1);
});

it('refuses a report too short to act on', function (): void {
    $user = buyer();
    $website = site(['domain' => 'terse.test']);

    $this->actingAs($user)
        ->post(advertiserUrl("/sites/{$website->slug}/report"), ['message' => 'bad'])
        ->assertSessionHasErrors('message');

    expect(Conversation::query()->count())->toBe(0);
});

it('keeps one advertiser out of another’s detail view', function (): void {
    $website = Website::factory()->create(['is_active' => false]);

    $this->actingAs(buyer())
        ->getJson(advertiserUrl("/catalog/website/{$website->slug}"))
        ->assertForbidden();
});
