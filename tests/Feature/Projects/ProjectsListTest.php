<?php

declare(strict_types=1);

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Actions\ListProjects;
use App\Domain\Projects\DTOs\ProjectFilters;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

function projectUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function projectPost(Project $project, PostStatus $status, int $cents, array $attributes = []): Post
{
    $post = Post::factory()->status($status)->create([
        'user_id' => $project->user_id,
        'project_id' => $project->id,
        'price_cents' => $cents,
    ]);

    if ($attributes !== []) {
        $post->forceFill($attributes)->save();
    }

    return $post;
}

it('renders the list with rows, totals and the view preference', function (): void {
    $user = projectUser();
    Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/projects'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Index')
            ->has('projects', 1)
            ->has('totals.posts')
            ->where('hasAnyProjects', true)
            ->where('isFiltering', false)
            ->where('view', 'table')
        );
});

it('never shows one advertiser another advertiser\'s projects', function (): void {
    $mine = projectUser();
    $theirs = projectUser();
    Project::factory()->create(['user_id' => $theirs->id]);

    $this->actingAs($mine)
        ->get(advertiserUrl('/projects'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('projects', 0)
            ->where('hasAnyProjects', false)
        );
});

it('splits the post mix into disjoint segments that sum to the total', function (): void {
    $user = projectUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    projectPost($project, PostStatus::New, 100_00);
    projectPost($project, PostStatus::InProgress, 100_00);
    projectPost($project, PostStatus::ContentReview, 100_00);
    projectPost($project, PostStatus::Completed, 100_00);
    // Live but still inside its verification window: money held, so Frozen.
    projectPost($project, PostStatus::Posted, 100_00, ['frozen_until' => now()->addDay()]);
    // Live and past the window: settled, so Posted.
    projectPost($project, PostStatus::Posted, 100_00, ['frozen_until' => now()->subDay()]);
    // Neither: falls into the remainder so the bar still adds up.
    projectPost($project, PostStatus::Draft, 100_00);
    projectPost($project, PostStatus::Rejected, 100_00);

    $row = $this->actingAs($user)->get(advertiserUrl('/projects'))
        ->viewData('page')['props']['projects'][0];

    expect($row['posts']['new'])->toBe(1)
        ->and($row['posts']['progress'])->toBe(2)
        ->and($row['posts']['posted'])->toBe(2)
        ->and($row['posts']['frozen'])->toBe(1)
        ->and($row['posts']['other'])->toBe(2)
        ->and($row['posts']['total'])->toBe(8);

    // The whole point of the fifth segment: the widths sum to the number
    // printed above them, whatever mix of statuses the project holds.
    $segments = $row['posts']['new'] + $row['posts']['progress'] + $row['posts']['posted']
        + $row['posts']['frozen'] + $row['posts']['other'];

    expect($segments)->toBe($row['posts']['total']);
});

it('reports frozen funds as everything the wallet is still holding', function (): void {
    $user = projectUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // New, in progress, content review and posted all hold funds.
    projectPost($project, PostStatus::New, 100_00);
    projectPost($project, PostStatus::InProgress, 200_00);
    projectPost($project, PostStatus::Posted, 300_00);
    // Completed has settled; cancelled was returned. Neither is held.
    projectPost($project, PostStatus::Completed, 900_00);
    projectPost($project, PostStatus::Cancelled, 900_00);

    $row = $this->actingAs($user)->get(advertiserUrl('/projects'))
        ->viewData('page')['props']['projects'][0];

    expect($row['frozenCents'])->toBe(600_00);
});

it('averages across completed posts only', function (): void {
    $user = projectUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    projectPost($project, PostStatus::Completed, 100_00);
    projectPost($project, PostStatus::Completed, 300_00);
    // Quoted, not paid: it must not drag the average.
    projectPost($project, PostStatus::InProgress, 5_000_00);

    $row = $this->actingAs($user)->get(advertiserUrl('/projects'))
        ->viewData('page')['props']['projects'][0];

    expect($row['averageCents'])->toBe(200_00);
});

it('shows no average rather than zero when nothing has completed', function (): void {
    $user = projectUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    projectPost($project, PostStatus::InProgress, 500_00);

    $row = $this->actingAs($user)->get(advertiserUrl('/projects'))
        ->viewData('page')['props']['projects'][0];

    // Zero would read as "these placements are free".
    expect($row['averageCents'])->toBeNull();
});

it('measures spend by month and quarter, with a delta against last month', function (): void {
    Carbon::setTestNow('2026-05-15 12:00:00');

    $user = projectUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    projectPost($project, PostStatus::Completed, 300_00, ['published_at' => '2026-05-02']);
    // Last month, and still inside this quarter: it counts towards the
    // quarter and towards the delta, but not towards this month.
    projectPost($project, PostStatus::Completed, 100_00, ['published_at' => '2026-04-20']);
    // Q1: outside every window on this row.
    projectPost($project, PostStatus::Completed, 700_00, ['published_at' => '2026-01-10']);

    $row = $this->actingAs($user)->get(advertiserUrl('/projects'))
        ->viewData('page')['props']['projects'][0];

    expect($row['spentMonthCents'])->toBe(300_00)
        // Q2 is April, May and June, so April's 100.00 is in it too.
        ->and($row['spentQuarterCents'])->toBe(400_00)
        ->and($row['spentMonthDeltaPct'])->toEqual(200.0);

    Carbon::setTestNow();
});

it('reports "new" rather than a percentage when last month was empty', function (): void {
    Carbon::setTestNow('2026-05-15 12:00:00');

    $user = projectUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    projectPost($project, PostStatus::Completed, 300_00, ['published_at' => '2026-05-02']);

    $row = $this->actingAs($user)->get(advertiserUrl('/projects'))
        ->viewData('page')['props']['projects'][0];

    expect($row['spentMonthDeltaPct'])->toBeNull();

    Carbon::setTestNow();
});

it('sums the footer totals from the rows on screen', function (): void {
    $user = projectUser();
    $alpha = Project::factory()->create(['user_id' => $user->id]);
    $beta = Project::factory()->create(['user_id' => $user->id]);

    projectPost($alpha, PostStatus::Posted, 100_00);
    projectPost($beta, PostStatus::Posted, 250_00);

    $props = $this->actingAs($user)->get(advertiserUrl('/projects'))->viewData('page')['props'];

    expect($props['totals']['posts'])->toBe(2)
        ->and($props['totals']['frozenCents'])->toBe(350_00)
        // The footer is summed from the rendered rows, so it can never
        // disagree with the column above it.
        ->and($props['totals']['frozenCents'])
        ->toBe(array_sum(array_column($props['projects'], 'frozenCents')));
});

it('filters by status and defaults to active', function (): void {
    $user = projectUser();
    Project::factory()->create(['user_id' => $user->id, 'status' => ProjectStatus::Active]);
    Project::factory()->create(['user_id' => $user->id, 'status' => ProjectStatus::Archived]);

    $count = fn (string $query): int => count(
        $this->actingAs($user)->get(advertiserUrl('/projects'.$query))->viewData('page')['props']['projects'],
    );

    expect($count(''))->toBe(1)
        ->and($count('?status=archived'))->toBe(1)
        ->and($count('?status=all'))->toBe(2);
});

it('searches name and promoted URL', function (): void {
    $user = projectUser();
    Project::factory()->create(['user_id' => $user->id, 'name' => 'Nomad Bank', 'website_url' => 'https://nomad.test']);
    Project::factory()->create(['user_id' => $user->id, 'name' => 'Acme SaaS', 'website_url' => 'https://acme.test']);

    $names = fn (string $q): array => array_column(
        $this->actingAs($user)->get(advertiserUrl('/projects?q='.$q))->viewData('page')['props']['projects'],
        'name',
    );

    expect($names('nomad'))->toBe(['Nomad Bank'])
        ->and($names('acme.test'))->toBe(['Acme SaaS']);
});

it('sorts by every offered column, in both directions', function (): void {
    $user = projectUser();
    $b = Project::factory()->create(['user_id' => $user->id, 'name' => 'Beta']);
    $a = Project::factory()->create(['user_id' => $user->id, 'name' => 'Alpha']);

    projectPost($b, PostStatus::Completed, 100_00, ['published_at' => now()]);

    $names = fn (string $query): array => array_column(
        $this->actingAs($user)->get(advertiserUrl('/projects?'.$query))->viewData('page')['props']['projects'],
        'name',
    );

    expect($names('sort=name&direction=asc'))->toBe(['Alpha', 'Beta'])
        ->and($names('sort=name&direction=desc'))->toBe(['Beta', 'Alpha'])
        ->and($names('sort=posts&direction=desc'))->toBe(['Beta', 'Alpha'])
        // The default: this month's spend, biggest first.
        ->and($names(''))->toBe(['Beta', 'Alpha']);
});

it('keeps the filter state round-tripping through the query string', function (): void {
    $query = ['status' => 'archived', 'q' => 'nomad', 'sort' => 'name', 'direction' => 'asc'];
    $filters = ProjectFilters::fromRequest(Request::create('/projects', 'GET', $query));

    expect($filters->toQuery())->toBe($query)
        ->and(ProjectFilters::fromRequest(Request::create('/projects'))->toQuery())->toBe([]);
});

it('tells an empty account apart from an empty filter result', function (): void {
    $user = projectUser();

    $this->actingAs($user)->get(advertiserUrl('/projects'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hasAnyProjects', false)
            ->where('isFiltering', false)
        );

    Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(advertiserUrl('/projects?q=nothingmatches'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('projects', 0)
            ->where('hasAnyProjects', true)
            ->where('isFiltering', true)
        );
});

it('persists the table or cards choice per account', function (): void {
    $user = projectUser();

    $this->actingAs($user)->patch(advertiserUrl('/projects/view'), ['view' => 'cards'])->assertRedirect();

    $this->actingAs($user)->get(advertiserUrl('/projects'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'cards'));

    // Anything else is refused rather than stored.
    $this->actingAs($user)->patch(advertiserUrl('/projects/view'), ['view' => 'spreadsheet'])
        ->assertSessionHasErrors('view');
});

it('runs a fixed number of queries however many projects there are', function (): void {
    $user = projectUser();

    foreach (range(1, 6) as $i) {
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => "Project {$i}"]);
        projectPost($project, PostStatus::Completed, 100_00);
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(ListProjects::class)->handle(
        $user,
        ProjectFilters::fromRequest(Request::create('/projects')),
    );

    // The projects, their categories, and one grouped aggregate over posts.
    // A count per project per column would be forty.
    expect($queries)->toBeLessThanOrEqual(3);
});
