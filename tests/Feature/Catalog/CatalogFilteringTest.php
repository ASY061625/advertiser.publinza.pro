<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\GetCatalogFacets;
use App\Domain\Catalog\Actions\SearchCatalog;
use App\Domain\Catalog\Actions\SuggestRelaxations;
use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Enums\LinkType;
use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Catalog\Models\WebsiteMetric;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Catalog\Support\CatalogPresenter;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Enums\ServiceType;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;

function buyer(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

/**
 * A site with everything the catalog reads. Named arguments at the call site
 * keep each test's intent legible against a model with eighteen columns.
 */
function site(array $attributes = [], array $metrics = [], array $price = []): Website
{
    $website = Website::factory()->create($attributes + ['is_active' => true]);

    if ($metrics !== false) {
        WebsiteMetric::factory()->create($metrics + ['website_id' => $website->id, 'fetched_at' => now()]);
    }

    WebsitePrice::factory()->create($price + [
        'website_id' => $website->id,
        'service_type' => ServiceType::ArticlePlacement,
    ]);

    return $website->fresh();
}

function filters(array $query = []): CatalogFilters
{
    return CatalogFilters::fromRequest(new Request($query));
}

function found(array $query, ?int $userId = null): array
{
    return collect(app(SearchCatalog::class)->handle(filters($query), $userId)->items())
        ->pluck('domain')
        ->all();
}

it('reads every filter group out of the query string and back into it', function (): void {
    $applied = filters([
        'q' => '  finance blog  ',
        'domain' => 'HTTPS://WWW.Example.com/pricing',
        'categories' => ['3', '3', '0', 'nonsense'],
        'price' => '50-250',
        'traffic' => '1000-90000',
        'max_spam' => '20',
        'speed' => ['day', 'not-a-band'],
        'link_type' => 'dofollow',
        'topics' => ['gambling'],
        'favorites' => '1',
        'sort' => 'price_asc',
        'per_page' => '25',
        'view' => 'cards',
    ]);

    expect($applied->query)->toBe('finance blog')
        // Run through the same normaliser the rest of the app uses, so a
        // pasted URL finds the site.
        ->and($applied->domain)->toBe('www.example.com')
        // Deduplicated, and nothing that is not an id.
        ->and($applied->categories)->toBe([3])
        // Dollars in the URL, cents in the filter.
        ->and($applied->price)->toBe([5_000, 25_000])
        ->and($applied->traffic)->toBe([1_000, 90_000])
        ->and($applied->maxSpam)->toBe(20)
        ->and($applied->speeds)->toBe(['day'])
        ->and($applied->topics)->toBe(['gambling'])
        ->and($applied->onlyFavorites)->toBeTrue()
        // On by default, and only off when asked.
        ->and($applied->hideBlacklisted)->toBeTrue();

    // And the whole thing round-trips: the URL is the state, so what it parses
    // has to be what it re-emits.
    expect($applied->toQuery())->toMatchArray([
        'q' => 'finance blog',
        'domain' => 'www.example.com',
        'price' => '50-250',
        'traffic' => '1000-90000',
        'sort' => 'price_asc',
        'per_page' => 25,
        'view' => 'cards',
    ]);
});

it('does not count the blacklist toggle as a filter', function (): void {
    // Otherwise the catalog is never unfiltered, and "no filters and no
    // results" — the state that means something is broken — is unreachable.
    expect(filters([])->isFiltering())->toBeFalse()
        ->and(filters(['show_blacklisted' => '1'])->isFiltering())->toBeFalse()
        ->and(filters(['q' => 'anything'])->isFiltering())->toBeTrue();
});

it('reverses a range someone typed backwards rather than returning nothing', function (): void {
    expect(filters(['price' => '250-50'])->price)->toBe([5_000, 25_000]);
});

it('filters on price, traffic, scores and spam', function (): void {
    $cheap = site(['domain' => 'cheap.test'], ['monthly_traffic' => 1_000, 'ahrefs_dr' => 10, 'moz_da' => 12, 'spam_score' => 5], ['price_cents' => 5_000]);
    $mid = site(['domain' => 'mid.test'], ['monthly_traffic' => 50_000, 'ahrefs_dr' => 50, 'moz_da' => 48, 'spam_score' => 40], ['price_cents' => 20_000]);
    $dear = site(['domain' => 'dear.test'], ['monthly_traffic' => 900_000, 'ahrefs_dr' => 90, 'moz_da' => 88, 'spam_score' => 2], ['price_cents' => 90_000]);

    expect(found(['price' => '100-500']))->toBe(['mid.test'])
        ->and(found(['traffic' => '40000-100000']))->toBe(['mid.test'])
        ->and(found(['dr' => '80-100']))->toBe(['dear.test'])
        ->and(found(['da' => '0-20']))->toBe(['cheap.test'])
        // A ceiling, not a range: nobody filters for a minimum amount of spam.
        ->and(found(['max_spam' => '10', 'sort' => 'price_asc']))->toBe(['cheap.test', 'dear.test']);

    expect([$cheap->domain, $mid->domain, $dear->domain])->toHaveCount(3);
});

it('puts a site in exactly one publication band', function (): void {
    site(['domain' => 'sameday.test', 'publication_period_hours' => 24]);
    site(['domain' => 'boundary.test', 'publication_period_hours' => 72]);
    site(['domain' => 'slow.test', 'publication_period_hours' => 400]);

    // 72 hours is the boundary between "1–3 days" and "3–7 days". The bands are
    // half-open, so it belongs to the first and is not counted twice when both
    // boxes are ticked.
    expect(found(['speed' => ['fast']]))->toBe(['boundary.test'])
        ->and(found(['speed' => ['week']]))->toBe([])
        ->and(found(['speed' => ['day']]))->toBe(['sameday.test'])
        ->and(found(['speed' => ['slow']]))->toBe(['slow.test']);

    $both = found(['speed' => ['day', 'fast'], 'sort' => 'newest']);
    expect($both)->toHaveCount(2)->and(array_unique($both))->toHaveCount(2);
});

it('requires every sensitive topic asked for, not any of them', function (): void {
    site(['domain' => 'both.test', 'accepts_sensitive_topics' => ['gambling', 'crypto']]);
    site(['domain' => 'one.test', 'accepts_sensitive_topics' => ['gambling']]);
    site(['domain' => 'none.test', 'accepts_sensitive_topics' => []]);

    // A publisher who takes one of the two is not a partial answer — it is a
    // site the order would be rejected on.
    expect(found(['topics' => ['gambling']]))->toContain('both.test', 'one.test')
        ->and(found(['topics' => ['gambling', 'crypto']]))->toBe(['both.test']);
});

it('filters by link type and by whether traffic was ever measured', function (): void {
    site(['domain' => 'follow.test', 'link_type' => LinkType::Dofollow]);
    site(['domain' => 'nofollow.test', 'link_type' => LinkType::Nofollow]);

    expect(found(['link_type' => 'nofollow']))->toBe(['nofollow.test']);

    // A site measured at zero has been assessed. The filter is about whether
    // anybody looked, so the two have to be told apart.
    site(['domain' => 'measured-zero.test'], ['monthly_traffic' => 0]);

    expect(found(['has_traffic' => '1']))->not->toContain('measured-zero.test');
});

it('keeps one advertiser’s lists out of another’s catalog', function (): void {
    $mine = buyer();
    $theirs = buyer();

    $hidden = site(['domain' => 'hidden.test']);
    $loved = site(['domain' => 'loved.test']);

    Blacklist::query()->create(['user_id' => $mine->id, 'website_id' => $hidden->id]);
    Favorite::query()->create(['user_id' => $mine->id, 'website_id' => $loved->id]);

    expect(found([], $mine->id))->not->toContain('hidden.test')
        ->and(found([], $theirs->id))->toContain('hidden.test')
        ->and(found(['favorites' => '1'], $mine->id))->toBe(['loved.test'])
        ->and(found(['favorites' => '1'], $theirs->id))->toBe([]);

    // Shown on request, so a blacklist is reviewable rather than a trapdoor.
    expect(found(['show_blacklisted' => '1'], $mine->id))->toContain('hidden.test');
});

it('excludes sites already used on the project, and only inside one', function (): void {
    $user = buyer();
    $project = Project::factory()->for($user, 'owner')->create();

    $used = site(['domain' => 'used.test']);
    site(['domain' => 'fresh.test']);

    Post::factory()->for($user, 'advertiser')->for($project)->create(['website_id' => $used->id]);

    expect(found(['unused' => '1', 'project' => $project->id], $user->id))->toBe(['fresh.test']);

    // Without a project the toggle has nothing to mean, and silently filtering
    // on "used somewhere else" would be a different question than the one asked.
    expect(found(['unused' => '1'], $user->id))->toContain('used.test');
});

it('counts each facet against every filter except its own', function (): void {
    $finance = WebsiteCategory::factory()->create(['name' => 'Finance']);
    $tech = WebsiteCategory::factory()->create(['name' => 'Technology']);

    site(['domain' => 'a.test', 'category_id' => $finance->id]);
    site(['domain' => 'b.test', 'category_id' => $finance->id]);
    site(['domain' => 'c.test', 'category_id' => $tech->id]);

    $facets = app(GetCatalogFacets::class)->handle(filters(['categories' => [$finance->id]]), null, 100_000);
    $counts = collect($facets['categories'])->pluck('count', 'name')->all();

    // With Finance ticked, Technology still says what ticking it too would add.
    // Counting a dimension against itself is the bug that makes every
    // unselected option read zero and the list a dead end.
    expect($counts['Finance'])->toBe(2)->and($counts['Technology'])->toBe(1);
});

it('draws the price histogram over the unfiltered range', function (): void {
    site(['domain' => 'cheap.test'], [], ['price_cents' => 1_000]);
    site(['domain' => 'dear.test'], [], ['price_cents' => 99_000]);

    // The price filter is deliberately ignored: the histogram is what the
    // handles are aimed at, so drawing only what is already selected would
    // empty the bars outside the selection — the one thing it exists to show.
    $facets = app(GetCatalogFacets::class)->handle(filters(['price' => '10-20']), null, 100_000);

    expect(array_sum($facets['priceHistogram']))->toBe(2)
        ->and($facets['priceHistogram'][0])->toBe(1)
        ->and($facets['priceHistogram'][23])->toBe(1);
});

it('pages by cursor over a joined sort, with ties', function (): void {
    $user = buyer();

    foreach (range(1, 12) as $i) {
        site(['domain' => "site{$i}.test"], ['monthly_traffic' => $i <= 6 ? 1_000 : 2_000]);
    }

    $search = app(SearchCatalog::class);
    $seen = [];
    $cursor = null;

    for ($page = 0; $page < 6; $page++) {
        $result = $search->handle(filters(['sort' => 'traffic', 'per_page' => 25, 'cursor' => $cursor])->with(['perPage' => 5]), $user->id);

        foreach ($result->items() as $site) {
            $seen[] = $site->domain;
        }

        $cursor = $result->nextCursor()?->encode();

        if ($cursor === null) {
            break;
        }
    }

    // Half the sites share a traffic figure, which is where a cursor with no
    // tiebreak repeats one row and drops another.
    expect($seen)->toHaveCount(12)->and(array_unique($seen))->toHaveCount(12);
});

it('aims a relaxation at where the results actually are', function (): void {
    site(['domain' => 'affordable.test'], [], ['price_cents' => 20_000]);
    site(['domain' => 'pricey.test'], [], ['price_cents' => 30_000]);

    // A ceiling of $100 against sites at $200 and $300. A fixed multiplier —
    // 1.5x, say — lands at $150 and opens nothing, so the card would either not
    // appear or promise zero sites.
    $suggestions = app(SuggestRelaxations::class)->handle(filters(['price' => '0-100']), null);

    $price = collect($suggestions)->first(
        static fn (array $s): bool => str_contains($s['label'], 'maximum price'),
    );

    expect($price)->not->toBeNull()
        // $200 exactly: the cheapest site above the ceiling, rounded to a
        // number a person would say.
        ->and($price['label'])->toBe('Raise your maximum price to $200')
        // And the promised count is the count the relaxed filter returns.
        ->and($price['count'])->toBe(1)
        ->and($price['query']['price'])->toBe('0-200');
});

it('says nothing when a relaxation would open nothing', function (): void {
    site(['domain' => 'only.test'], [], ['price_cents' => 20_000]);

    // Everything above the ceiling is already visible, so there is no boundary
    // to move and no card to draw.
    expect(app(SuggestRelaxations::class)->handle(filters(['price' => '150-500']), null))->toBe([]);
});

it('flags a project mismatch without hiding the row', function (): void {
    $user = buyer();
    $english = Language::factory()->create(['code' => 'en', 'name' => 'English']);
    $german = Language::factory()->create(['code' => 'de', 'name' => 'German']);
    $gambling = SensitiveTopic::factory()->create(['name' => 'Gambling', 'slug' => 'gambling']);

    $project = Project::factory()->for($user, 'owner')->create();
    $project->languages()->attach($english->id);
    $project->sensitiveTopics()->attach($gambling->id);

    $mismatch = site([
        'domain' => 'german.test',
        'primary_language_id' => $german->id,
        'accepts_sensitive_topics' => [],
    ]);

    $rows = app(CatalogPresenter::class)->handle(
        [$mismatch->load(['category', 'primaryLanguage', 'country', 'latestMetric', 'prices'])],
        $user->id,
        $project->load(['languages:id,name', 'sensitiveTopics:id,name,slug']),
    );

    // Both mismatches named, and the row is still a row: a publisher who does
    // not take this project's topic may still be right for a different article.
    expect($rows)->toHaveCount(1)
        ->and(collect($rows[0]['warnings'])->pluck('kind')->all())->toBe(['language', 'topic'])
        ->and($rows[0]['warnings'][0]['message'])->toContain('German')
        ->and($rows[0]['warnings'][1]['message'])->toContain('Gambling');
});

it('resolves every per-advertiser flag in bulk', function (): void {
    $user = buyer();
    $project = Project::factory()->for($user, 'owner')->create();

    $sites = collect(range(1, 5))->map(fn (int $i): Website => site(['domain' => "bulk{$i}.test"]));

    Favorite::query()->create(['user_id' => $user->id, 'website_id' => $sites[0]->id]);
    Blacklist::query()->create(['user_id' => $user->id, 'website_id' => $sites[1]->id]);
    Post::factory()->for($user, 'advertiser')->for($project)->create(['website_id' => $sites[2]->id]);

    $loaded = Website::query()
        ->whereIn('id', $sites->pluck('id'))
        ->with(['category', 'primaryLanguage', 'country', 'latestMetric', 'prices'])
        ->get();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $rows = app(CatalogPresenter::class)->handle($loaded, $user->id, $project);

    // Four lookups for five sites, not five lookups per site. This is the N+1
    // that only appears once the catalog is worth scrolling.
    expect($queries)->toBeLessThanOrEqual(6)
        ->and(collect($rows)->firstWhere('domain', 'bulk1.test')['isFavorite'])->toBeTrue()
        ->and(collect($rows)->firstWhere('domain', 'bulk2.test')['isBlacklisted'])->toBeTrue()
        ->and(collect($rows)->firstWhere('domain', 'bulk3.test')['usedInProject'])->toBeTrue();
});

it('serves browse mode without a project and buying mode with one', function (): void {
    $user = buyer();
    site(['domain' => 'somewhere.test']);

    $this->actingAs($user)
        ->get(advertiserUrl('/catalog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project', null)
            ->where('canBuy', false)
            ->has('facets.categories')
            ->has('ranges')
            ->etc());

    $project = Project::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(advertiserUrl("/catalog?project={$project->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.id', $project->id)
            ->where('canBuy', true)
            ->etc());
});

it('seeds the filters from the project, once', function (): void {
    $user = buyer();
    $category = WebsiteCategory::factory()->create();
    $country = Country::factory()->create();

    $project = Project::factory()->for($user, 'owner')->create(['category_id' => $category->id]);
    $project->countries()->attach($country->id);

    $this->actingAs($user)
        ->get(advertiserUrl("/catalog?project={$project->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.categories', [$category->id])
            ->where('filters.countries', [$country->id])
            ->etc());

    // Seeded, not forced. Once the buyer has chosen anything at all, the
    // project's targeting stops re-applying — otherwise removing a seeded
    // filter would put it straight back.
    $this->actingAs($user)
        ->get(advertiserUrl("/catalog?project={$project->id}&dr=40-100"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('filters.categories')
            ->where('filters.dr', '40-100')
            ->etc());
});

it('drops quietly back to browse mode for a project that is not yours', function (): void {
    $stranger = Project::factory()->for(buyer(), 'owner')->create();

    // A bookmark outlives access to a project, and a 403 on the catalog would
    // be a confusing way to find that out.
    $this->actingAs(buyer())
        ->get(advertiserUrl("/catalog?project={$stranger->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('project', null)->where('canBuy', false)->etc());
});

it('offers relaxations only when filters returned nothing', function (): void {
    $user = buyer();
    site(['domain' => 'exists.test'], [], ['price_cents' => 50_000]);

    $this->actingAs($user)
        ->get(advertiserUrl('/catalog?price=1-2'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sites', 0)
            ->where('isFiltering', true)
            ->has('suggestions')
            ->etc());

    // An empty catalog with no filters is not something a buyer can fix by
    // clicking anything, so there is nothing to suggest.
    $this->actingAs($user)
        ->get(advertiserUrl('/catalog'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('suggestions', [])->etc());
});

it('serves one site as JSON for the drawer', function (): void {
    $user = buyer();
    $website = site(['domain' => 'detail.test', 'guidelines' => 'No affiliate links.']);

    $this->actingAs($user)
        ->getJson(advertiserUrl("/catalog/{$website->slug}"))
        ->assertOk()
        ->assertJsonPath('domain', 'detail.test')
        ->assertJsonPath('guidelines', 'No affiliate links.')
        ->assertJsonPath('services.0.type', 'article_placement');
});

it('toggles favorites and the blacklist from one control each', function (): void {
    $user = buyer();
    $website = site(['domain' => 'toggle.test']);

    $this->actingAs($user)->post(advertiserUrl("/sites/{$website->slug}/favorite"))->assertRedirect();
    expect(Favorite::query()->where('user_id', $user->id)->count())->toBe(1);

    // The same control undoes it: a heart that only works one way leaves people
    // with no way back except a settings page they have to go and find.
    $this->actingAs($user)->post(advertiserUrl("/sites/{$website->slug}/favorite"))->assertRedirect();
    expect(Favorite::query()->where('user_id', $user->id)->count())->toBe(0);

    $this->actingAs($user)->post(advertiserUrl("/sites/{$website->slug}/blacklist"))->assertRedirect();
    expect(Blacklist::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('saves to a wishlist without asking anyone to name one first', function (): void {
    $user = buyer();
    $website = site(['domain' => 'wish.test']);

    $this->actingAs($user)->post(advertiserUrl("/sites/{$website->slug}/wishlist"))->assertRedirect();
    $this->actingAs($user)->post(advertiserUrl("/sites/{$website->slug}/wishlist"))->assertRedirect();

    // Created on demand, and idempotent: one menu item clicked twice is one
    // saved site, not two rows nobody asked for.
    expect(DB::table('wishlists')->where('user_id', $user->id)->count())->toBe(1)
        ->and(DB::table('wishlist_items')->count())->toBe(1);
});

it('reproduces the exact view from the URL alone', function (): void {
    $user = buyer();
    $category = WebsiteCategory::factory()->create(['name' => 'Finance']);

    site(['domain' => 'match.test', 'category_id' => $category->id], ['monthly_traffic' => 40_000], ['price_cents' => 15_000]);
    site(['domain' => 'miss.test'], ['monthly_traffic' => 900_000], ['price_cents' => 90_000]);

    $url = "/catalog?categories[]={$category->id}&price=100-200&traffic=1000-100000&sort=price_asc&view=cards&per_page=25";

    // The same link, twice, gives the same page — which is the whole promise of
    // keeping the state in the URL rather than in the component.
    foreach ([1, 2] as $_) {
        $this->actingAs($user)
            ->get(advertiserUrl($url))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('sites', 1)
                ->where('sites.0.domain', 'match.test')
                ->where('filters.view', 'cards')
                ->where('filters.per_page', 25)
                ->where('filters.sort', 'price_asc')
                ->etc());
    }
});
