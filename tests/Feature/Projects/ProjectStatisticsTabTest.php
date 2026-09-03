<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\BuildStatisticsExport;
use App\Domain\Analytics\Actions\GetProjectStatistics;
use App\Domain\Analytics\DTOs\DateRange;
use App\Domain\Catalog\Enums\LinkType;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\System\Models\ExportJob;
use App\Http\Controllers\Advertiser\ExportController;
use App\Jobs\BuildStatisticsExportJob;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

function statsUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function statsProject(User $user): Project
{
    $project = Project::factory()->for($user, 'owner')->create(['name' => 'Nomad Bank']);
    ProjectFolder::query()->create(['project_id' => $project->id, 'name' => 'General', 'sort_order' => 0]);

    return $project;
}

function statsPost(
    User $user,
    Project $project,
    string $publishedAt,
    int $priceCents = 20_000,
    LinkType $link = LinkType::Dofollow,
    ?WebsiteCategory $category = null,
    ?ProjectFolder $folder = null,
): Post {
    $website = Website::factory()->create([
        'link_type' => $link,
        ...($category === null ? [] : ['category_id' => $category->id]),
    ]);

    return Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::Completed)->create([
        'website_id' => $website->id,
        'price_cents' => $priceCents,
        'published_at' => $publishedAt,
        'created_at' => $publishedAt,
        'folder_id' => $folder?->id,
    ]);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-03-31 12:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('buckets spend and placements by the period a link went live', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    statsPost($user, $project, '2026-03-30 10:00:00', 25_000);
    statsPost($user, $project, '2026-03-30 14:00:00', 15_000);
    statsPost($user, $project, '2026-03-31 09:00:00', 30_000);

    $data = app(GetProjectStatistics::class)->handle(
        $project,
        DateRange::fromRequest(new Request(['range' => 'last_7'])),
        'day',
    );

    $byIso = collect($data['series'])->keyBy('iso');

    expect($byIso['2026-03-30']['publishedCount'])->toBe(2)
        ->and($byIso['2026-03-30']['spendCents'])->toBe(40_000)
        ->and($byIso['2026-03-31']['spendCents'])->toBe(30_000)
        // Empty days are kept: a gap is information, and dropping it would
        // draw a line straight over it.
        ->and($byIso['2026-03-28']['spendCents'])->toBe(0)
        ->and($byIso->count())->toBe(7);
});

it('runs the cumulative spend and the live-link total forward across periods', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    // Live before the range opens: the running total has to start from it.
    statsPost($user, $project, '2026-01-05 10:00:00', 10_000);

    statsPost($user, $project, '2026-03-29 10:00:00', 20_000);
    statsPost($user, $project, '2026-03-31 10:00:00', 30_000);

    $data = app(GetProjectStatistics::class)->handle(
        $project,
        DateRange::fromRequest(new Request(['range' => 'last_7'])),
        'day',
    );

    $byIso = collect($data['series'])->keyBy('iso');

    expect($byIso['2026-03-29']['cumulativeSpendCents'])->toBe(20_000)
        ->and($byIso['2026-03-31']['cumulativeSpendCents'])->toBe(50_000)
        // Two inside the range, plus the one that was already live.
        ->and($byIso['2026-03-29']['liveLinks'])->toBe(2)
        ->and($byIso['2026-03-31']['liveLinks'])->toBe(3);
});

it('splits links by whether the site passes authority', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    statsPost($user, $project, '2026-03-30 10:00:00', 10_000, LinkType::Dofollow);
    statsPost($user, $project, '2026-03-30 11:00:00', 10_000, LinkType::Nofollow);
    statsPost($user, $project, '2026-03-30 12:00:00', 10_000, LinkType::Dofollow);

    $data = app(GetProjectStatistics::class)->handle(
        $project,
        DateRange::fromRequest(new Request(['range' => 'last_7'])),
        'day',
    );

    $day = collect($data['series'])->firstWhere('iso', '2026-03-30');

    expect($day['dofollow'])->toBe(2)
        ->and($day['nofollow'])->toBe(1)
        // The split has to add up to the placements beside it.
        ->and($day['dofollow'] + $day['nofollow'])->toBe($day['publishedCount']);
});

it('compares the summary against the equivalent window before it', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    // Previous 7 days: one post at $100.
    statsPost($user, $project, '2026-03-20 10:00:00', 10_000);
    // This 7 days: two posts at $150 total.
    statsPost($user, $project, '2026-03-29 10:00:00', 10_000);
    statsPost($user, $project, '2026-03-30 10:00:00', 5_000);

    $data = app(GetProjectStatistics::class)->handle(
        $project,
        DateRange::fromRequest(new Request(['range' => 'last_7'])),
        'day',
    );

    expect($data['summary']['spentCents'])->toBe(15_000)
        ->and($data['summary']['spentDeltaPct'])->toBe(50.0)
        ->and($data['summary']['published'])->toBe(2)
        // Live links is a running total, not a count inside the window — all
        // three posts are live, so it is not the same number as the card
        // beside it.
        ->and($data['summary']['links'])->toBe(3)
        ->and($data['summary']['averageCents'])->toBe(7_500);
});

it('folds everything past the top ten into Other, so the bars still add up', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    foreach (range(1, 12) as $i) {
        $category = WebsiteCategory::factory()->create(['name' => "Category {$i}", 'slug' => "category-{$i}"]);
        statsPost($user, $project, '2026-03-30 10:00:00', $i * 1_000, LinkType::Dofollow, $category);
    }

    $data = app(GetProjectStatistics::class)->handle(
        $project,
        DateRange::fromRequest(new Request(['range' => 'last_7'])),
        'day',
    );

    $rows = collect($data['byCategory']);

    expect($rows)->toHaveCount(11)
        ->and($rows->last()['label'])->toBe('Other')
        // The two smallest, folded together.
        ->and($rows->last()['placements'])->toBe(2)
        // And the whole breakdown still sums to what the card says was spent.
        ->and($rows->sum('spentCents'))->toBe($data['summary']['spentCents']);
});

it('narrows every figure to one folder', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    $spring = ProjectFolder::query()->create(['project_id' => $project->id, 'name' => 'Spring', 'sort_order' => 1]);

    statsPost($user, $project, '2026-03-30 10:00:00', 10_000, LinkType::Dofollow, null, $spring);
    statsPost($user, $project, '2026-03-30 11:00:00', 90_000);

    $all = app(GetProjectStatistics::class)->handle(
        $project,
        DateRange::fromRequest(new Request(['range' => 'last_7'])),
        'day',
    );

    $scoped = app(GetProjectStatistics::class)->handle(
        $project,
        DateRange::fromRequest(new Request(['range' => 'last_7'])),
        'day',
        $spring->id,
    );

    expect($all['summary']['spentCents'])->toBe(100_000)
        ->and($scoped['summary']['spentCents'])->toBe(10_000)
        ->and($scoped['summary']['published'])->toBe(1);
});

it('serves the tab, and only for its own tab', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    statsPost($user, $project, '2026-03-30 10:00:00');

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=statistics&range=last_7&granularity=day"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tab', 'statistics')
            ->has('statistics.series', 7)
            ->where('statistics.granularity', 'day')
            ->where('statistics.hasEverHadPosts', true)
            ->has('statistics.folders', 1)
            ->etc());

    foreach (['general', 'settings'] as $tab) {
        $this->actingAs($user)
            ->get(advertiserUrl("/projects/{$project->id}?tab={$tab}"))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('statistics', null)->etc());
    }
});

it('ignores a folder filter belonging to another project', function (): void {
    $user = statsUser();
    $mine = statsProject($user);
    $theirs = statsProject($user);

    $theirFolder = ProjectFolder::query()->where('project_id', $theirs->id)->firstOrFail();
    statsPost($user, $mine, '2026-03-30 10:00:00', 10_000);

    // Silently emptying every chart would be worse than ignoring it.
    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$mine->id}?tab=statistics&range=last_7&folder={$theirFolder->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('statistics.folderId', null)
            ->where('statistics.summary.spentCents', 10_000)
            ->etc());
});

it('says a project has never had a post, so the tab can say so too', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=statistics"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('statistics.hasEverHadPosts', false)
            ->etc());

    // A post that has not gone live still counts as having had one: the tab
    // shows an empty range, not the first-run invitation.
    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create();

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=statistics"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('statistics.hasEverHadPosts', true)
            ->where('statistics.summary.spentCents', 0)
            ->etc());
});

it('queues an export rather than building it in the request', function (): void {
    Queue::fake();

    $user = statsUser();
    $project = statsProject($user);

    $this->actingAs($user)
        ->postJson(advertiserUrl("/projects/{$project->id}/statistics/export?range=last_7&granularity=day"), [
            'format' => 'xlsx',
        ])
        ->assertStatus(202)
        ->assertJson(['status' => 'queued', 'format' => 'xlsx']);

    Queue::assertPushed(BuildStatisticsExportJob::class);

    $export = ExportJob::query()->latest('id')->firstOrFail();

    expect($export->user_id)->toBe($user->id)
        ->and($export->type)->toBe('project.statistics')
        ->and($export->filters['project_id'])->toBe($project->id)
        ->and($export->filters['format'])->toBe('xlsx');
});

it('refuses a format it cannot write', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    $this->actingAs($user)
        ->postJson(advertiserUrl("/projects/{$project->id}/statistics/export"), ['format' => 'docx'])
        ->assertStatus(422);
});

it('builds each format from one set of rows', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    statsPost($user, $project, '2026-03-30 10:00:00', 25_000);

    $range = DateRange::fromRequest(new Request(['range' => 'last_7']));
    $directory = storage_path('framework/testing/exports');

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $result = app(BuildStatisticsExport::class)->handle($project, $range, 'day', null, $format, $directory);

        expect(file_exists($result['path']))->toBeTrue()
            ->and($result['rows'])->toBe(7)
            ->and(filesize($result['path']))->toBeGreaterThan(100);

        $contents = (string) file_get_contents($result['path']);

        match ($format) {
            // A real ZIP, a real PDF, and a CSV with the header the table shows.
            'xlsx' => expect(substr($contents, 0, 2))->toBe('PK'),
            'pdf' => expect(substr($contents, 0, 5))->toBe('%PDF-'),
            default => expect($contents)->toContain('Posts published')->toContain('250'),
        };

        @unlink($result['path']);
    }
});

it('writes an xlsx a spreadsheet can open, with numbers as numbers', function (): void {
    $user = statsUser();
    $project = statsProject($user);

    statsPost($user, $project, '2026-03-30 10:00:00', 25_000);

    $range = DateRange::fromRequest(new Request(['range' => 'last_7']));
    $path = app(BuildStatisticsExport::class)
        ->handle($project, $range, 'day', null, 'xlsx', storage_path('framework/testing/exports'))['path'];

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($sheet)->toContain('<t xml:space="preserve">Posts published</t>')
        // 250.00 as a number, not a string: a money column that arrives as
        // text is a column nobody can sum.
        ->and($sheet)->toContain('<v>250</v>');

    @unlink($path);
});

it('notifies the advertiser and stores the file when the job runs', function (): void {
    Notification::fake();
    Storage::fake('local');

    $user = statsUser();
    $project = statsProject($user);
    statsPost($user, $project, '2026-03-30 10:00:00');

    $export = ExportJob::query()->create([
        'user_id' => $user->id,
        'type' => 'project.statistics',
        'status' => 'queued',
        'filters' => [
            'project_id' => $project->id, 'format' => 'csv', 'granularity' => 'day',
            'range' => 'last_7', 'from' => '2026-03-25', 'to' => '2026-03-31',
        ],
    ]);

    app(BuildStatisticsExportJob::class, ['export' => $export])->handle(app(BuildStatisticsExport::class));

    $export->refresh();

    expect($export->status)->toBe('ready')
        ->and($export->file_path)->not->toBeNull()
        ->and($export->row_count)->toBe(7);

    Storage::disk('local')->assertExists($export->file_path);

    // The bytes, not just the path. The build directory and the storage
    // directory were the same place once, so the cleanup deleted the file it
    // had just stored and every export arrived empty.
    expect(Storage::disk('local')->get($export->file_path))->toContain('Posts published');

    Notification::assertSentTo($user, ExportReadyNotification::class);
});

it('hands the file over to its owner', function (): void {
    Storage::fake('local');

    $user = statsUser();
    Storage::disk('local')->put('exports/report.csv', 'Period,Spend');

    $export = ExportJob::query()->create([
        'user_id' => $user->id,
        'type' => 'project.statistics',
        'status' => 'ready',
        'file_path' => 'exports/report.csv',
        'completed_at' => now(),
    ]);

    $this->actingAs($user)->get(advertiserUrl("/exports/{$export->id}/download"))->assertOk();
});

it('will not hand somebody else’s export over, or even confirm it exists', function (): void {
    Storage::fake('local');

    $owner = statsUser();
    Storage::disk('local')->put('exports/report.csv', 'Period,Spend');

    $export = ExportJob::query()->create([
        'user_id' => $owner->id,
        'type' => 'project.statistics',
        'status' => 'ready',
        'file_path' => 'exports/report.csv',
        'completed_at' => now(),
    ]);

    $this->actingAs(statsUser())
        ->get(advertiserUrl("/exports/{$export->id}/download"))
        ->assertNotFound();
});

it('lets a download link expire after 24 hours', function (): void {
    Storage::fake('local');

    $user = statsUser();
    Storage::disk('local')->put('exports/report.csv', 'Period,Spend');

    $export = ExportJob::query()->create([
        'user_id' => $user->id,
        'type' => 'project.statistics',
        'status' => 'ready',
        'file_path' => 'exports/report.csv',
        'completed_at' => now()->subHours(ExportController::LINK_TTL_HOURS + 1),
    ]);

    // 410, not 404: "gone" and "never existed" are different answers, and the
    // message tells them to export it again rather than leaving them guessing.
    $this->actingAs($user)
        ->get(advertiserUrl("/exports/{$export->id}/download"))
        ->assertStatus(410);
});

it('keeps a built export when the notification cannot be sent', function (): void {
    Storage::fake('local');

    $user = statsUser();
    $project = statsProject($user);
    statsPost($user, $project, '2026-03-30 10:00:00');

    // A mail server having a bad minute is not a reason to tell somebody their
    // export failed when the file is already written and stored.
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp is down'));

    $export = ExportJob::query()->create([
        'user_id' => $user->id,
        'type' => 'project.statistics',
        'status' => 'queued',
        'filters' => [
            'project_id' => $project->id, 'format' => 'csv', 'granularity' => 'day',
            'range' => 'last_7', 'from' => '2026-03-25', 'to' => '2026-03-31',
        ],
    ]);

    app(BuildStatisticsExportJob::class, ['export' => $export])->handle(app(BuildStatisticsExport::class));

    $export->refresh();

    expect($export->status)->toBe('ready')
        ->and($export->file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($export->file_path);
});
