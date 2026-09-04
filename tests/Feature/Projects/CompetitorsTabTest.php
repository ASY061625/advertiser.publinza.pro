<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Intelligence\Actions\AddCompetitor;
use App\Domain\Intelligence\Actions\EnsureProjectSiteTracked;
use App\Domain\Intelligence\Actions\FetchCompetitorMetrics;
use App\Domain\Intelligence\Actions\GetCompetitorComparison;
use App\Domain\Intelligence\Actions\RefreshCompetitor;
use App\Domain\Intelligence\Contracts\MetricsProvider;
use App\Domain\Intelligence\DTOs\DomainMetrics;
use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Intelligence\Exceptions\MetricsUnavailable;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Intelligence\Models\CompetitorMetric;
use App\Domain\Intelligence\Providers\AhrefsProvider;
use App\Domain\Intelligence\Providers\MozProvider;
use App\Domain\Intelligence\Providers\SemrushProvider;
use App\Domain\Intelligence\Support\MetricsProviderRegistry;
use App\Domain\Projects\Models\Project;
use App\Jobs\FetchCompetitorMetricsJob;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

function rivalUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function rivalProject(User $user, string $url = 'https://nomadbank.test'): Project
{
    return Project::factory()->for($user, 'owner')->create(['name' => 'Nomad Bank', 'website_url' => $url]);
}

/** A competitor with figures, without going near a provider. */
function trackedRival(Project $project, string $domain, array $metrics = [], bool $isSelf = false): Competitor
{
    $competitor = Competitor::factory()->create([
        'project_id' => $project->id,
        'domain' => $domain,
        'is_self' => $isSelf,
    ]);

    CompetitorMetric::factory()->create(['competitor_id' => $competitor->id] + $metrics);

    return $competitor->fresh();
}

it('adds a rival, normalises what was typed, and queues the fetch', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());

    $competitor = app(AddCompetitor::class)->handle($project, '  HTTPS://WWW.Rival.com/pricing?x=1 ', ' Main rival ');

    expect($competitor->domain)->toBe('www.rival.com')
        ->and($competitor->label)->toBe('Main rival')
        ->and($competitor->fetch_state)->toBe(FetchState::Pending);

    // The project's own site is tracked too, or the first comparison has
    // nothing to compare against.
    $self = Competitor::query()->where('project_id', $project->id)->where('is_self', true)->first();
    expect($self?->domain)->toBe('nomadbank.test');

    Queue::assertPushed(FetchCompetitorMetricsJob::class, 2);
});

it('refuses the four things a person can fix', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());
    $add = app(AddCompetitor::class);

    expect(fn () => $add->handle($project, 'not a website at all'))
        ->toThrow(ValidationException::class, 'That does not look like a website address.');

    expect(fn () => $add->handle($project, 'https://nomadbank.test/pricing'))
        ->toThrow(ValidationException::class);

    $add->handle($project, 'rival.com');

    expect(fn () => $add->handle($project, 'https://rival.com'))
        ->toThrow(ValidationException::class, 'already tracking');

    config()->set('publinza.competitors.max_per_project', 1);

    expect(fn () => $add->handle($project, 'second.com'))
        ->toThrow(ValidationException::class, 'Remove one to add another');
});

it('does not let the project’s own row occupy a competitor slot', function (): void {
    Queue::fake();

    config()->set('publinza.competitors.max_per_project', 2);
    $project = rivalProject(rivalUser());
    $add = app(AddCompetitor::class);

    $add->handle($project, 'one.com');
    $add->handle($project, 'two.com');

    // Three rows exist — two rivals and the project's own site — and the limit
    // counts the two.
    expect(Competitor::query()->where('project_id', $project->id)->count())->toBe(3);

    expect(fn () => $add->handle($project, 'three.com'))->toThrow(ValidationException::class);
});

it('repoints its own row when the promoted site changes', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());
    $ensure = app(EnsureProjectSiteTracked::class);

    $self = $ensure->handle($project);
    expect($self?->domain)->toBe('nomadbank.test');

    $project->forceFill(['website_url' => 'https://nomadbank.example'])->save();

    $again = $ensure->handle($project->fresh());

    expect($again?->id)->toBe($self?->id)
        ->and($again?->domain)->toBe('nomadbank.example')
        ->and($again?->fetch_state)->toBe(FetchState::Pending)
        ->and(Competitor::query()->where('project_id', $project->id)->where('is_self', true)->count())->toBe(1);
});

it('gives up a rival row when the project moves onto that domain', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());
    app(AddCompetitor::class)->handle($project, 'rival.com');

    $project->forceFill(['website_url' => 'https://rival.com'])->save();

    app(EnsureProjectSiteTracked::class)->handle($project->fresh());

    // One row for the domain, and it is the project's own — a site cannot be
    // its own competitor, and two rows cannot hold one domain.
    $rows = Competitor::query()->where('project_id', $project->id)->where('domain', 'rival.com')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()?->is_self)->toBeTrue();
});

it('stores what the provider said and files the links by catalog category', function (): void {
    $project = rivalProject(rivalUser());
    $competitor = Competitor::factory()->pending()->create([
        'project_id' => $project->id,
        'domain' => 'rival.com',
    ]);

    $technology = WebsiteCategory::factory()->create(['name' => 'Technology']);
    $finance = WebsiteCategory::factory()->create(['name' => 'Finance']);

    Website::factory()->for($technology, 'category')->create(['domain' => 'tech-one.test', 'is_active' => true]);
    Website::factory()->for($technology, 'category')->create(['domain' => 'tech-two.test', 'is_active' => true]);
    Website::factory()->for($finance, 'category')->create(['domain' => 'money.test', 'is_active' => true]);

    fakeProvider(new DomainMetrics(
        domain: 'rival.com',
        provider: 'ahrefs',
        organicTraffic: 120_000,
        organicKeywords: 9_000,
        dr: 61,
        referringDomains: 3_400,
        backlinks: 88_000,
        trafficValueCents: 4_200_000,
        referringDomainNames: ['tech-one.test', 'tech-two.test', 'money.test', 'not-in-catalog.test'],
    ));

    expect(app(FetchCompetitorMetrics::class)->handle($competitor))->toBeTrue();

    $metric = $competitor->fresh()?->latestMetric;

    expect($competitor->fresh()?->fetch_state)->toBe(FetchState::Ready)
        ->and($metric?->organic_traffic)->toBe(120_000)
        ->and($metric?->dr)->toBe(61)
        // No provider sells both scores, and the one it does not sell stays
        // null rather than becoming the worst score on the scale.
        ->and($metric?->da)->toBeNull()
        ->and($metric?->provider)->toBe('ahrefs')
        // A domain outside the catalog is a real link but not an offerable
        // one, so it is not counted towards any recommendation.
        ->and($metric?->link_categories)->toBe(['Technology' => 2, 'Finance' => 1]);
});

it('fetches for a row that came out of a collection, without a lazy load', function (): void {
    $project = rivalProject(rivalUser());

    Competitor::factory()->pending()->create(['project_id' => $project->id, 'domain' => 'rival.com']);
    Competitor::factory()->pending()->create(['project_id' => $project->id, 'domain' => 'second.com']);

    fakeProvider(new DomainMetrics(domain: 'rival.com', provider: 'ahrefs', organicTraffic: 4_200));

    // Two rows, fetched as a collection, and read back rather than reused.
    // Both halves are load-bearing: Eloquent arms the lazy-loading guard only
    // on models hydrated from a query that returned more than one row, and
    // exempts models created moments ago. A single find() here would pass with
    // or without the fix, and prove nothing.
    $competitor = Competitor::query()->where('project_id', $project->id)->get()->firstWhere('domain', 'rival.com');

    expect(app(FetchCompetitorMetrics::class)->handle($competitor))->toBeTrue()
        ->and($competitor->fresh()?->latestMetric?->organic_traffic)->toBe(4_200);
});

it('keeps the last good figures when the provider fails', function (): void {
    $project = rivalProject(rivalUser());
    $competitor = trackedRival($project, 'rival.com', ['organic_traffic' => 90_000]);

    fakeProvider(new MetricsUnavailable('ahrefs', 'rival.com', 'the API answered 503'));

    expect(app(FetchCompetitorMetrics::class)->handle($competitor))->toBeFalse();

    $fresh = $competitor->fresh();

    expect($fresh?->fetch_state)->toBe(FetchState::Failed)
        ->and($fresh?->fetch_error)->toContain('503')
        // The point of the amber notice: an outage must not cost the figures
        // that are still true, and must not write a row of zeros over them.
        ->and($fresh?->latestMetric?->organic_traffic)->toBe(90_000)
        ->and(CompetitorMetric::query()->where('competitor_id', $competitor->id)->count())->toBe(1);
});

it('rations manual refreshes to one a day per competitor', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());
    $competitor = trackedRival($project, 'rival.com');
    $refresh = app(RefreshCompetitor::class);

    $refresh->handle($competitor);

    expect($competitor->fresh()?->refreshed_at)->not->toBeNull()
        ->and($competitor->fresh()?->cooldownSeconds())->toBeGreaterThan(23 * 3600);

    expect(fn () => $refresh->handle($competitor->fresh()))
        ->toThrow(ValidationException::class, 'refreshed recently');

    // And it is a day, not forever.
    $competitor->forceFill(['refreshed_at' => now()->subHours(25)])->save();

    expect($competitor->fresh()?->cooldownSeconds())->toBe(0);

    $refresh->handle($competitor->fresh());

    Queue::assertPushed(FetchCompetitorMetricsJob::class, 2);
});

it('refetches figures older than the cache window, once', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());
    $stale = trackedRival($project, 'old.com', ['fetched_at' => now()->subDays(9)]);
    $fresh = trackedRival($project, 'new.com', ['fetched_at' => now()->subDay()]);

    $competitors = Competitor::query()->with('latestMetric')->get();

    app(RefreshCompetitor::class)->refill($competitors);

    expect($stale->fresh()?->fetch_state)->toBe(FetchState::Pending)
        ->and($fresh->fresh()?->fetch_state)->toBe(FetchState::Ready);

    Queue::assertPushed(FetchCompetitorMetricsJob::class, 1);

    // Opening the tab again does not queue the same billable call twice.
    app(RefreshCompetitor::class)->refill(Competitor::query()->with('latestMetric')->get());

    Queue::assertPushed(FetchCompetitorMetricsJob::class, 1);
});

it('measures every delta against your own site', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());

    trackedRival($project, 'nomadbank.test', [
        'organic_traffic' => 100_000, 'organic_keywords' => 5_000, 'dr' => 50, 'da' => null,
    ], isSelf: true);

    trackedRival($project, 'ahead.com', ['organic_traffic' => 150_000, 'dr' => 70]);
    trackedRival($project, 'behind.com', ['organic_traffic' => 50_000, 'dr' => 50]);

    $payload = app(GetCompetitorComparison::class)->handle($project);

    expect($payload['self']['domain'])->toBe('nomadbank.test')
        // Your own site is never one of the rivals, and never one of the slots.
        ->and($payload['competitors'])->toHaveCount(2)
        ->and($payload['slots']['used'])->toBe(2);

    $ahead = collect($payload['competitors'])->firstWhere('domain', 'ahead.com');
    $behind = collect($payload['competitors'])->firstWhere('domain', 'behind.com');

    expect($ahead['deltas']['organicTraffic']['percent'])->toBe(50.0)
        ->and($ahead['deltas']['organicTraffic']['leading'])->toBeFalse()
        ->and($behind['deltas']['organicTraffic']['percent'])->toBe(-50.0)
        ->and($behind['deltas']['organicTraffic']['leading'])->toBeTrue()
        // Level on a measure is neither ahead nor behind.
        ->and($behind['deltas']['dr']['leading'])->toBeNull()
        // A measure the provider did not sell has no delta at all, rather than
        // a delta computed against a zero nobody measured.
        ->and($ahead['deltas']['da']['percent'])->toBeNull()
        ->and($payload['self']['deltas'])->toBeNull();
});

it('splits keyword overlap three ways using both sides of the comparison', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());

    trackedRival($project, 'nomadbank.test', ['organic_keywords' => 5_000], isSelf: true);
    trackedRival($project, 'rival.com', ['organic_keywords' => 8_000, 'shared_keywords' => 2_000]);

    $overlap = app(GetCompetitorComparison::class)->handle($project)['overlap'];

    expect($overlap)->toHaveCount(1)
        ->and($overlap[0]['shared'])->toBe(2_000)
        ->and($overlap[0]['theirs'])->toBe(6_000)
        ->and($overlap[0]['yours'])->toBe(3_000);
});

it('plots the months anyone has, and breaks a line rather than inventing one', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());

    trackedRival($project, 'nomadbank.test', [
        'traffic_history' => [
            ['month' => '2026-01', 'traffic' => 10],
            ['month' => '2026-02', 'traffic' => 20],
            ['month' => '2026-03', 'traffic' => 30],
        ],
    ], isSelf: true);

    trackedRival($project, 'rival.com', [
        'traffic_history' => [['month' => '2026-01', 'traffic' => 90], ['month' => '2026-03', 'traffic' => 70]],
    ]);

    $trend = app(GetCompetitorComparison::class)->handle($project)['trend'];

    expect($trend['months'])->toBe(['2026-01', '2026-02', '2026-03']);

    $rival = collect($trend['series'])->firstWhere('domain', 'rival.com');

    // February is a month nobody measured for this domain, not a month with no
    // traffic — so it is a hole in the line, not a plunge to the floor.
    expect($rival['points'])->toBe([90, null, 70])
        ->and(collect($trend['series'])->firstWhere('isSelf', true)['points'])->toBe([10, 20, 30]);
});

it('suggests only categories the catalog can actually sell', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());
    WebsiteCategory::factory()->create(['name' => 'Technology']);

    trackedRival($project, 'nomadbank.test', ['link_categories' => ['Technology' => 6]], isSelf: true);
    trackedRival($project, 'rival.com', ['link_categories' => ['Technology' => 40, 'Ghosts' => 12]]);

    $suggestions = app(GetCompetitorComparison::class)->handle($project)['recommendations'];

    // 40 minus your 6. And "Ghosts" is a category with no catalog id, so its
    // card would open an unfiltered catalog — it still appears, but with a null
    // id the link falls back to the project's catalog rather than a dead filter.
    $technology = collect($suggestions)->firstWhere('category', 'Technology');

    expect($technology['count'])->toBe(34)
        ->and($technology['competitor'])->toBe('rival.com')
        ->and($technology['categoryId'])->not->toBeNull()
        ->and(collect($suggestions)->firstWhere('category', 'Ghosts')['categoryId'])->toBeNull();
});

it('says nothing when there is no gap to report', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());

    trackedRival($project, 'nomadbank.test', ['link_categories' => ['Technology' => 40]], isSelf: true);
    trackedRival($project, 'rival.com', ['link_categories' => ['Technology' => 12]]);

    expect(app(GetCompetitorComparison::class)->handle($project)['recommendations'])->toBe([]);
});

it('flags cached figures when a fetch failed, and names the provider that produced them', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());
    trackedRival($project, 'nomadbank.test', ['provider' => 'ahrefs'], isSelf: true);

    $competitor = trackedRival($project, 'rival.com', ['provider' => 'ahrefs']);
    $competitor->forceFill(['fetch_state' => FetchState::Failed, 'fetch_error' => 'Ahrefs answered 503'])->save();

    $source = app(GetCompetitorComparison::class)->handle($project)['source'];

    expect($source['showingCached'])->toBeTrue()
        // The provider that produced what is on screen, which after a switch is
        // not the same as the one configured now.
        ->and($source['provider'])->toBe('Ahrefs')
        ->and($source['updatedAt'])->not->toBeNull();
});

it('maps an Ahrefs answer into the shape the tab reads', function (): void {
    config()->set('services.ahrefs.token', 'test-token');

    Http::fake([
        '*/domain-rating*' => Http::response(['domain_rating' => ['domain_rating' => 61.4]]),
        // Before the plain metrics pattern, which would otherwise swallow it:
        // Laravel offers every request to every stub and takes the first match.
        '*/metrics-history*' => Http::response([
            'metrics' => [
                ['date' => '2026-02-01', 'org_traffic' => 90_000],
                ['date' => '2026-01-01', 'org_traffic' => 80_000],
            ],
        ]),
        '*/site-explorer/metrics*' => Http::response([
            'metrics' => ['org_traffic' => 120_000, 'org_keywords' => 9_000, 'org_cost' => 42_000],
        ]),
        '*/backlinks-stats*' => Http::response(['metrics' => ['live' => 88_000, 'live_refdomains' => 3_400]]),
        '*/organic-competitors-keywords*' => Http::response([
            'keywords' => [
                ['keyword' => 'shared term', 'both_rank' => true, 'best_position' => 3, 'volume' => 900],
                ['keyword' => 'their term', 'both_rank' => false, 'best_position' => 5, 'volume' => 400,
                    'keyword_difficulty' => 22, 'best_position_url' => 'https://rival.com/guide'],
            ],
        ]),
        '*/refdomains*' => Http::response(['refdomains' => [['domain' => 'WWW.Linker.test']]]),
    ]);

    $metrics = app(AhrefsProvider::class)->fetch('rival.com', 'nomadbank.test');

    expect($metrics->organicTraffic)->toBe(120_000)
        ->and($metrics->dr)->toBe(61)
        ->and($metrics->da)->toBeNull()
        ->and($metrics->backlinks)->toBe(88_000)
        // Dollars in, cents stored — money is minor units everywhere here.
        ->and($metrics->trafficValueCents)->toBe(4_200_000)
        // Oldest first, whatever order the vendor sent them in.
        ->and($metrics->trafficHistory)->toBe([
            ['month' => '2026-01', 'traffic' => 80_000],
            ['month' => '2026-02', 'traffic' => 90_000],
        ])
        ->and($metrics->sharedKeywords)->toBe(1)
        // The gap is what they rank for and you do not, so the shared row is out.
        ->and($metrics->gapKeywords)->toHaveCount(1)
        ->and($metrics->gapKeywords[0]->keyword)->toBe('their term')
        ->and($metrics->referringDomainNames)->toBe(['www.linker.test']);
});

it('reads SEMrush’s semicolon tables by header, not by position', function (): void {
    config()->set('services.semrush.key', 'test-key');

    Http::fake([
        '*api.semrush.com/analytics/v1*' => Http::sequence()
            ->push("ascore;total;domains_num\n44;88000;3400")
            ->push("domain;domain_ascore\nlinker.test;51"),
        // Anchored on the query string, so it cannot also match /analytics/v1:
        // every stub is invoked on every request, so two overlapping sequences
        // would each advance on a call only one of them answers.
        '*api.semrush.com/?*' => Http::sequence()
            // Deliberately not the column order asked for: SEMrush adds and
            // reorders columns, and reading $parts[4] is how a release starts
            // reporting traffic in the keywords column.
            ->push("Dn;Ot;Or;Oc;Db\nrival.com;120000;9000;42000;us")
            ->push("Rk;Or;Ot;Oc;Dt\n5;9000;90000;40000;20260201")
            ->push("Ph;P0;Nq;Kd;Ur\ntheir term;5;400;22;https://rival.com/guide")
            ->push("Ph\nshared term"),
    ]);

    $metrics = app(SemrushProvider::class)->fetch('rival.com', 'nomadbank.test');

    expect($metrics->organicTraffic)->toBe(120_000)
        ->and($metrics->organicKeywords)->toBe(9_000)
        ->and($metrics->da)->toBe(44)
        ->and($metrics->dr)->toBeNull()
        ->and($metrics->referringDomains)->toBe(3_400)
        ->and($metrics->trafficHistory)->toBe([['month' => '2026-02', 'traffic' => 90_000]])
        ->and($metrics->gapKeywords[0]->url)->toBe('https://rival.com/guide');
});

it('turns any provider failure into one exception rather than a half-filled row', function (): void {
    config()->set('services.moz.access_id', 'id');
    config()->set('services.moz.secret_key', 'secret');

    Http::fake(['*' => Http::response(['error' => 'quota'], 429)]);

    expect(fn () => app(MozProvider::class)->fetch('rival.com', 'nomadbank.test'))
        ->toThrow(MetricsUnavailable::class, 'the API answered 429');
});

it('will not run on a vendor whose credentials are missing', function (): void {
    config()->set('publinza.competitors.provider', 'ahrefs');
    config()->set('services.ahrefs.token', null);

    // Falling back rather than throwing: the tab is readable on labelled sample
    // data, and every row says where its figures came from.
    expect(app(MetricsProviderRegistry::class)->current()->key())->toBe('sample');

    config()->set('services.ahrefs.token', 'a-token');

    expect(app(MetricsProviderRegistry::class)->current()->key())->toBe('ahrefs');
});

it('serves the tab, and only for its own tab', function (): void {
    Queue::fake();

    $user = rivalUser();
    $project = rivalProject($user);
    trackedRival($project, 'nomadbank.test', [], isSelf: true);
    trackedRival($project, 'rival.com');

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=competitors"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tab', 'competitors')
            ->has('competitors.competitors', 1)
            ->where('competitors.self.domain', 'nomadbank.test')
            ->where('competitors.slots.limit', 10)
            ->etc());

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=general"))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('competitors', null)->etc());
});

it('keeps one advertiser’s competitors away from another', function (): void {
    Queue::fake();

    $project = rivalProject(rivalUser());
    $competitor = trackedRival($project, 'rival.com');
    $outsider = rivalUser();

    $this->actingAs($outsider)
        ->get(advertiserUrl("/projects/{$project->id}?tab=competitors"))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(advertiserUrl("/projects/{$project->id}/competitors"), ['domain' => 'sneaky.com'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->get(advertiserUrl("/projects/{$project->id}/competitors/{$competitor->id}/gap-keywords"))
        ->assertForbidden();
});

it('will not touch a competitor through another project’s URL', function (): void {
    Queue::fake();

    $user = rivalUser();
    $mine = rivalProject($user);
    $other = Project::factory()->for($user, 'owner')->create(['website_url' => 'https://other.test']);

    $competitor = trackedRival($other, 'rival.com');

    // Same owner, so the policy passes — and the row still must not be
    // reachable, or a refresh would spend a metered vendor call booked to a
    // project that does not track it.
    $this->actingAs($user)
        ->post(advertiserUrl("/projects/{$mine->id}/competitors/{$competitor->id}/refresh"))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$mine->id}/competitors/{$competitor->id}"))
        ->assertNotFound();

    expect(Competitor::query()->find($competitor->id))->not->toBeNull();
});

it('serves the gap keywords on demand', function (): void {
    Queue::fake();

    $user = rivalUser();
    $project = rivalProject($user);

    $competitor = trackedRival($project, 'rival.com', [
        'gap_keywords' => [
            ['keyword' => 'their term', 'position' => 4, 'volume' => 900, 'difficulty' => 30,
                'url' => 'https://rival.com/guide'],
        ],
    ]);

    $this->actingAs($user)
        ->getJson(advertiserUrl("/projects/{$project->id}/competitors/{$competitor->id}/gap-keywords"))
        ->assertOk()
        ->assertJsonPath('domain', 'rival.com')
        ->assertJsonPath('keywords.0.keyword', 'their term')
        ->assertJsonPath('keywords.0.url', 'https://rival.com/guide');
});

it('marks a row failed when its job dies outright', function (): void {
    $project = rivalProject(rivalUser());
    $competitor = Competitor::factory()->pending()->create(['project_id' => $project->id, 'domain' => 'rival.com']);

    // A spinner that never resolves is the one state the tab cannot explain, so
    // even an unhandled failure has to land somewhere the reader can see.
    (new FetchCompetitorMetricsJob($competitor->id))->failed(new RuntimeException('worker restarted'));

    expect($competitor->fresh()?->fetch_state)->toBe(FetchState::Failed)
        ->and($competitor->fresh()?->fetch_error)->toContain('worker restarted');
});

/**
 * Makes Ahrefs answer with whatever the test says, or throw.
 *
 * Swapped in the container rather than by subclassing the registry: the
 * registry resolves each provider through `app()`, so binding the concrete
 * class is the seam that already exists. It also means the test exercises the
 * real selection path — config names ahrefs, the registry checks credentials
 * and hands back what the container holds.
 */
function fakeProvider(DomainMetrics|MetricsUnavailable $answer): void
{
    config()->set('publinza.competitors.provider', 'ahrefs');
    config()->set('services.ahrefs.token', 'test-token');

    app()->instance(AhrefsProvider::class, new class($answer) implements MetricsProvider
    {
        public function __construct(private readonly DomainMetrics|MetricsUnavailable $answer) {}

        public function key(): string
        {
            return 'ahrefs';
        }

        public function label(): string
        {
            return 'Ahrefs';
        }

        public function isConfigured(): bool
        {
            return true;
        }

        public function fetch(string $domain, string $ownDomain): DomainMetrics
        {
            if ($this->answer instanceof MetricsUnavailable) {
                throw $this->answer;
            }

            return $this->answer;
        }
    });
}
