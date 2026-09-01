<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\GetDashboardMetrics;
use App\Domain\Analytics\DTOs\DateRange;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Catalog\Models\Website;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

function dashboardUser(array $attributes = []): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        ...$attributes,
    ]);

    Wallet::query()->create([
        'user_id' => $user->id,
        'available_cents' => 250_000,
        'frozen_cents' => 40_000,
    ]);

    return $user;
}

/** A post that reached Posted on a given day, for a given price. */
function dashboardPlacement(User $user, Project $project, Website $website, string $on, int $cents): Post
{
    $post = Post::factory()
        ->status(PostStatus::Posted)
        ->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'website_id' => $website->id,
            'price_cents' => $cents,
        ]);

    $post->forceFill(['published_at' => CarbonImmutable::parse($on)])->save();

    return $post;
}

it('renders the dashboard with its first payload already inlined', function (): void {
    $user = dashboardUser(['name' => 'Rosa Nunes']);
    Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('firstName', 'Rosa')
            ->has('metrics.stats.totalSpent')
            ->has('metrics.series')
            ->where('metrics.range.key', 'last_30')
        );
});

it('greets the user by first name only', function (): void {
    $user = dashboardUser(['name' => 'Rosa Maria Nunes']);

    $this->actingAs($user)
        ->get(advertiserUrl('/dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('firstName', 'Rosa'));
});

it('serves the same payload as JSON for range changes', function (): void {
    $user = dashboardUser();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics?range=last_7'))
        ->assertOk()
        ->assertJsonPath('range.key', 'last_7')
        ->assertJsonPath('granularity', 'day')
        ->assertJsonStructure(['stats', 'series', 'statusBreakdown', 'recentPosts', 'topWebsites', 'deadlines']);
});

it('refuses the dashboard to guests', function (): void {
    $this->get(advertiserUrl('/dashboard'))->assertRedirect(advertiserUrl('/login'));
    $this->getJson(advertiserUrl('/dashboard/metrics'))->assertUnauthorized();
});

it('never shows one advertiser another advertiser\'s figures', function (): void {
    $mine = dashboardUser();
    $theirs = dashboardUser();

    $project = Project::factory()->create(['user_id' => $theirs->id]);
    dashboardPlacement($theirs, $project, Website::factory()->create(), now()->subDays(2)->toDateString(), 500_00);

    $this->actingAs($mine)
        ->getJson(advertiserUrl('/dashboard/metrics'))
        ->assertJsonPath('stats.totalSpent.value', 0)
        ->assertJsonPath('recentPosts', [])
        ->assertJsonPath('topWebsites', []);
});

it('scopes every panel to the project in the URL', function (): void {
    $user = dashboardUser();
    $alpha = Project::factory()->create(['user_id' => $user->id, 'name' => 'Alpha']);
    $beta = Project::factory()->create(['user_id' => $user->id, 'name' => 'Beta']);

    dashboardPlacement($user, $alpha, Website::factory()->create(), now()->subDays(2)->toDateString(), 100_00);
    dashboardPlacement($user, $beta, Website::factory()->create(), now()->subDays(2)->toDateString(), 900_00);

    $this->actingAs($user)
        ->getJson(advertiserUrl("/dashboard/metrics?project={$alpha->id}"))
        ->assertJsonPath('projectId', $alpha->id)
        ->assertJsonPath('stats.totalSpent.value', 100_00)
        ->assertJsonCount(1, 'topWebsites');
});

it('reports a delta against the previous equivalent period, not the previous month', function (): void {
    CarbonImmutable::setTestNow('2026-03-15 12:00:00');

    $user = dashboardUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $website = Website::factory()->create();

    // 200.00 inside the last 7 days, 100.00 in the 7 days before that.
    dashboardPlacement($user, $project, $website, '2026-03-12', 200_00);
    dashboardPlacement($user, $project, $website, '2026-03-05', 100_00);

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics?range=last_7'))
        ->assertJsonPath('stats.totalSpent.value', 200_00)
        // JSON collapses 100.0 to 100, so compare on value rather than type —
        // the client's type for this field is `number | null` either way.
        ->assertJsonPath('stats.totalSpent.deltaPct', fn (int|float $pct): bool => $pct == 100);

    CarbonImmutable::setTestNow();
});

it('reports "new" rather than a percentage when the previous period was empty', function (): void {
    CarbonImmutable::setTestNow('2026-03-15 12:00:00');

    $user = dashboardUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    dashboardPlacement($user, $project, Website::factory()->create(), '2026-03-12', 200_00);

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics?range=last_7'))
        ->assertJsonPath('stats.totalSpent.deltaPct', null);

    CarbonImmutable::setTestNow();
});

it('reports a zero delta, not null, when both periods were empty', function (): void {
    $user = dashboardUser();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics?range=last_7'))
        ->assertJsonPath('stats.totalSpent.deltaPct', 0);
});

it('distinguishes "no projects" from "no posts" from "nothing in this range"', function (): void {
    CarbonImmutable::setTestNow('2026-03-15 12:00:00');

    $user = dashboardUser();

    // No projects at all.
    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics'))
        ->assertJsonPath('hasProjects', false)
        ->assertJsonPath('hasAnyPosts', false);

    // A project, still nothing bought.
    $project = Project::factory()->create(['user_id' => $user->id]);
    Cache::flush();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics'))
        ->assertJsonPath('hasProjects', true)
        ->assertJsonPath('hasAnyPosts', false);

    // History, but none of it inside a 7-day window.
    dashboardPlacement($user, $project, Website::factory()->create(), '2026-01-10', 300_00);
    Cache::flush();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics?range=last_7'))
        ->assertJsonPath('hasProjects', true)
        ->assertJsonPath('hasAnyPosts', true)
        ->assertJsonPath('stats.totalSpent.value', 0)
        ->assertJsonPath('topWebsites', []);

    CarbonImmutable::setTestNow();
});

it('buckets the series by day, week or month to suit the range length', function (): void {
    $user = dashboardUser();

    $this->actingAs($user)->getJson(advertiserUrl('/dashboard/metrics?range=last_7'))
        ->assertJsonPath('granularity', 'day')
        ->assertJsonCount(7, 'series');

    $this->actingAs($user)->getJson(advertiserUrl('/dashboard/metrics?range=quarter'))
        ->assertJsonPath('granularity', 'week');

    $this->actingAs($user)->getJson(advertiserUrl('/dashboard/metrics?range=year'))
        ->assertJsonPath('granularity', 'month')
        ->assertJsonCount(13, 'series');
});

it('honours an explicit granularity over the default for the range', function (): void {
    $user = dashboardUser();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics?range=year&granularity=week'))
        ->assertJsonPath('granularity', 'week');
});

it('falls back to the default range when the range is not one it knows', function (): void {
    $user = dashboardUser();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics?range=all_time_forever'))
        ->assertJsonPath('range.key', 'last_30');
});

it('accepts a custom range and straightens a backwards one', function (): void {
    $user = dashboardUser();

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics?range=custom&from=2026-02-20&to=2026-02-01'))
        ->assertJsonPath('range.key', 'custom')
        ->assertJsonPath('range.from', '2026-02-01')
        ->assertJsonPath('range.to', '2026-02-20');
});

it('shows deadlines inside seven days, soonest first, flagging those under 48 hours', function (): void {
    $user = dashboardUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $soon = Post::factory()->status(PostStatus::InProgress)->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'deadline_at' => now()->addHours(10),
    ]);
    $later = Post::factory()->status(PostStatus::InProgress)->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'deadline_at' => now()->addDays(5),
    ]);
    Post::factory()->status(PostStatus::InProgress)->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'deadline_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->getJson(advertiserUrl('/dashboard/metrics'))
        ->assertJsonCount(2, 'deadlines')
        ->assertJsonPath('deadlines.0.id', $soon->id)
        ->assertJsonPath('deadlines.0.urgent', true)
        ->assertJsonPath('deadlines.1.id', $later->id)
        ->assertJsonPath('deadlines.1.urgent', false);
});

it('returns at most eight recent posts, newest first', function (): void {
    $user = dashboardUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    foreach (range(1, 12) as $day) {
        Post::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'created_at' => now()->subDays($day),
        ]);
    }

    $response = $this->actingAs($user)->getJson(advertiserUrl('/dashboard/metrics'))->assertJsonCount(8, 'recentPosts');

    $dates = array_column($response->json('recentPosts'), 'createdAt');
    expect($dates)->toEqual(collect($dates)->sortDesc()->values()->all());
});

it('never sends an advertiser\'s domains to a third-party favicon service', function (): void {
    $user = dashboardUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Post::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $favicons = array_column(
        $this->actingAs($user)->getJson(advertiserUrl('/dashboard/metrics'))->json('recentPosts'),
        'favicon',
    );

    expect($favicons)->each->toBeNull();
});

it('breaks posts down by status with shares that account for every post', function (): void {
    $user = dashboardUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Post::factory()->count(3)->status(PostStatus::Posted)
        ->create(['user_id' => $user->id, 'project_id' => $project->id]);
    Post::factory()->status(PostStatus::InProgress)
        ->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $slices = $this->actingAs($user)->getJson(advertiserUrl('/dashboard/metrics'))->json('statusBreakdown');

    expect(array_sum(array_column($slices, 'count')))->toBe(4)
        ->and(round(array_sum(array_column($slices, 'pct'))))->toBe(100.0);
});

it('caches the payload for five minutes per user, range and scope', function (): void {
    $user = dashboardUser();
    $range = new DateRange('last_30', CarbonImmutable::now()->subDays(29), CarbonImmutable::now(), 'Last 30 days');

    app(GetDashboardMetrics::class)->handle($user, $range, 'day', null);

    expect(Cache::has(sprintf('dashboard:%d:%s:day:all', $user->id, $range->cacheKey())))->toBeTrue();
});

it('does not serve one range\'s cached payload for another', function (): void {
    $user = dashboardUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    dashboardPlacement($user, $project, Website::factory()->create(), now()->subDays(20)->toDateString(), 400_00);

    $this->actingAs($user)->getJson(advertiserUrl('/dashboard/metrics?range=last_7'))
        ->assertJsonPath('stats.totalSpent.value', 0);

    $this->actingAs($user)->getJson(advertiserUrl('/dashboard/metrics?range=last_30'))
        ->assertJsonPath('stats.totalSpent.value', 400_00);
});
