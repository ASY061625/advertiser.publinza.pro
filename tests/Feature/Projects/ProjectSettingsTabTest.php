<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\System\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function settingsUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

/**
 * @return array{0: User, 1: Project, 2: ProjectFolder}
 */
function settingsProject(): array
{
    $user = settingsUser();

    $project = Project::factory()->for($user, 'owner')->create([
        'name' => 'Nomad Bank',
        'website_url' => 'https://nomadbank.test',
        'publisher_task' => '<p>Plain, no superlatives.</p>',
    ]);

    $folder = ProjectFolder::query()->create([
        'project_id' => $project->id, 'name' => 'General', 'sort_order' => 0,
    ]);

    return [$user, $project, $folder];
}

function settingsPayload(Project $project, array $overrides = []): array
{
    return array_merge([
        'name' => $project->name,
        'website_url' => $project->website_url,
        'category_id' => $project->category_id,
        'color' => $project->color,
        'publisher_task' => $project->publisher_task ?? '',
        'sensitive_topic_ids' => [],
        'country_ids' => [],
        'language_ids' => [],
        'landing_pages' => [],
    ], $overrides);
}

it('builds the settings payload only for its own tab', function (): void {
    [$user, $project, $folder] = settingsProject();

    LandingPage::query()->create([
        'project_id' => $project->id, 'folder_id' => $folder->id,
        'anchor_text' => 'pricing', 'url' => 'https://nomadbank.test/pricing',
    ]);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=settings"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.values.name', 'Nomad Bank')
            ->where('settings.values.website_url', 'https://nomadbank.test')
            ->has('settings.values.landing_pages', 1)
            ->where('settings.values.landing_pages.0.anchor_text', 'pricing')
            ->where('settings.folderName', 'General')
            ->where('settings.retentionDays', DeleteProject::RETENTION_DAYS)
            ->has('settings.options.topics')
            ->has('settings.options.countries')
            ->etc());

    // Eleven option queries the other tabs have no use for.
    foreach (['general', 'statistics', 'history'] as $tab) {
        $this->actingAs($user)
            ->get(advertiserUrl("/projects/{$project->id}?tab={$tab}"))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('settings', null)->etc());
    }
});

it('saves every section in one submit', function (): void {
    [$user, $project, $folder] = settingsProject();

    $category = WebsiteCategory::factory()->create(['name' => 'Finance']);
    $topic = SensitiveTopic::query()->create(['name' => 'Crypto', 'slug' => 'crypto']);
    $country = Country::factory()->create();
    $language = Language::factory()->create();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, [
            'name' => 'Nomad Bank EU',
            'category_id' => $category->id,
            'color' => '#0ea5e9',
            'publisher_task' => '<p>Mention the free trial.</p>',
            'sensitive_topic_ids' => [$topic->id],
            'country_ids' => [$country->id],
            'language_ids' => [$language->id],
            'landing_pages' => [
                ['anchor_text' => 'pricing', 'url' => 'https://nomadbank.test/pricing'],
                ['anchor_text' => 'our API', 'url' => 'https://nomadbank.test/api'],
            ],
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Saved');

    $project->refresh()->load(['sensitiveTopics', 'countries', 'languages']);

    expect($project->name)->toBe('Nomad Bank EU')
        ->and($project->category_id)->toBe($category->id)
        ->and($project->color)->toBe('#0ea5e9')
        ->and($project->publisher_task)->toContain('free trial')
        ->and($project->sensitiveTopics->pluck('id')->all())->toBe([$topic->id])
        ->and($project->countries->pluck('id')->all())->toBe([$country->id])
        ->and($project->languages->pluck('id')->all())->toBe([$language->id])
        // The landing pages land in the project's first folder.
        ->and(LandingPage::query()->where('folder_id', $folder->id)->pluck('anchor_text')->all())
        ->toBe(['pricing', 'our API']);
});

it('writes one history entry per field that actually changed', function (): void {
    [$user, $project] = settingsProject();

    $this->actingAs($user)->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, [
        'name' => 'Nomad Bank EU',
    ]));

    $actions = AuditLog::query()
        ->where('auditable_type', Project::class)
        ->where('auditable_id', $project->id)
        ->pluck('action')
        ->all();

    // The name moved; nothing else did, so nothing else is recorded.
    expect($actions)->toBe(['project.name.updated']);

    $entry = AuditLog::query()->latest('id')->first();
    expect($entry->changes)->toBe(['field' => 'name', 'from' => 'Nomad Bank', 'to' => 'Nomad Bank EU']);

    // Saving the same values again records nothing at all.
    $this->actingAs($user)->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project->fresh()));

    expect(AuditLog::query()->where('auditable_id', $project->id)->count())->toBe(1);
});

it('records targeting by name, and ignores a reorder', function (): void {
    [$user, $project] = settingsProject();

    $a = Country::factory()->create(['name' => 'Argentina']);
    $b = Country::factory()->create(['name' => 'Belgium']);

    $this->actingAs($user)->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, [
        'country_ids' => [$a->id, $b->id],
    ]));

    $entry = AuditLog::query()->where('action', 'project.countries.updated')->latest('id')->firstOrFail();
    // Names, not ids: "countries: 3 → 7" is a database record, not history.
    expect($entry->changes['to'])->toBe(['Argentina', 'Belgium']);

    $before = AuditLog::query()->where('auditable_id', $project->id)->count();

    // The same countries in the other order is not a change to which
    // countries were picked.
    $this->actingAs($user)->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project->fresh(), [
        'country_ids' => [$b->id, $a->id],
    ]));

    expect(AuditLog::query()->where('auditable_id', $project->id)->count())->toBe($before);
});

it('normalises the promoted URL and refuses one it cannot read', function (): void {
    [$user, $project] = settingsProject();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, [
            'website_url' => 'NomadBank.test/',
        ]))
        ->assertSessionHasNoErrors();

    expect($project->fresh()->website_url)->toBe('https://nomadbank.test');

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, [
            'website_url' => 'not a url at all',
        ]))
        ->assertSessionHasErrors('website_url');
});

it('measures landing pages against the URL being saved, not the stored one', function (): void {
    [$user, $project] = settingsProject();

    // Moving the project to a new domain in the same save: the pages have to
    // be judged against the new one, or the move could never be made.
    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, [
            'website_url' => 'https://nomadbank.eu',
            'landing_pages' => [['anchor_text' => 'pricing', 'url' => 'https://nomadbank.eu/pricing']],
        ]))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project->fresh(), [
            'landing_pages' => [['anchor_text' => 'elsewhere', 'url' => 'https://somewhere-else.test/x']],
        ]))
        ->assertSessionHasErrors('landing_pages.0.url');
});

it('holds the project name to 60 characters', function (): void {
    [$user, $project] = settingsProject();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, ['name' => str_repeat('a', 60)]))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, ['name' => str_repeat('a', 61)]))
        ->assertSessionHasErrors('name');
});

it('sanitises the brief on the way in', function (): void {
    [$user, $project] = settingsProject();

    $this->actingAs($user)->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, [
        'publisher_task' => '<p onclick="steal()">Hi <a href="javascript:alert(1)">there</a></p><script>x()</script>',
    ]));

    $stored = (string) $project->fresh()->publisher_task;

    expect($stored)->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->toContain('Hi');
});

it('names the posts standing in the way of a delete', function (): void {
    [$user, $project] = settingsProject();

    $website = Website::factory()->create(['domain' => 'kuhn.test']);

    $blocking = Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::InProgress)
        ->create(['website_id' => $website->id, 'anchor_text' => 'pricing']);
    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::Completed)->create();

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=settings"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('settings.blockingPosts', 1)
            ->where('settings.blockingPosts.0.id', $blocking->id)
            ->where('settings.blockingPosts.0.domain', 'kuhn.test')
            ->where('settings.blockingPosts.0.statusLabel', 'In progress')
            ->etc());

    // And the server refuses, naming the count rather than saying "cannot".
    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$project->id}"), ['name' => 'Nomad Bank'])
        ->assertSessionHas('error');

    expect(session('error'))->toContain('1 post')
        ->and(Project::query()->find($project->id))->not->toBeNull();
});

it('records archiving, restoring and deleting as their own history entries', function (): void {
    [$user, $project] = settingsProject();

    $this->actingAs($user)->post(advertiserUrl("/projects/{$project->id}/archive"));
    $this->actingAs($user)->post(advertiserUrl("/projects/{$project->id}/restore"));
    $this->actingAs($user)->delete(advertiserUrl("/projects/{$project->id}"), ['name' => 'Nomad Bank']);

    expect(AuditLog::query()->where('auditable_id', $project->id)->pluck('action')->all())
        ->toBe(['project.archived', 'project.restored', 'project.deleted']);

    // Soft delete: the row survives its retention window.
    expect(Project::withTrashed()->find($project->id)->deleted_at)->not->toBeNull();
});

it('counts the catalog sites a targeting selection would show', function (): void {
    [$user, $project] = settingsProject();

    $uk = Country::factory()->create();
    $de = Country::factory()->create();
    $english = Language::factory()->create();

    $crypto = SensitiveTopic::query()->create(['name' => 'Crypto', 'slug' => 'crypto']);

    $site = fn (Country $country, array $topics, bool $active = true) => Website::factory()->create([
        'country_id' => $country->id,
        'primary_language_id' => $english->id,
        'accepts_sensitive_topics' => $topics,
        'is_active' => $active,
    ]);

    $site($uk, ['crypto']);
    $site($uk, []);
    $site($de, ['crypto']);
    // Inactive sites are not in the catalog, so they are not in the count.
    $site($uk, ['crypto'], false);

    $count = fn (array $query): int => $this->actingAs($user)
        ->getJson(advertiserUrl("/projects/{$project->id}/match-count?".http_build_query($query)))
        ->json('count');

    expect($count([]))->toBe(3)
        ->and($count(['countries' => [$uk->id]]))->toBe(2)
        ->and($count(['topics' => [$crypto->id]]))->toBe(2)
        ->and($count(['countries' => [$uk->id], 'topics' => [$crypto->id]]))->toBe(1);
});

it('keeps another advertiser out of both the form and the count', function (): void {
    [, $project] = settingsProject();
    $intruder = settingsUser();

    $this->actingAs($intruder)
        ->get(advertiserUrl("/projects/{$project->id}?tab=settings"))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->getJson(advertiserUrl("/projects/{$project->id}/match-count"))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->put(advertiserUrl("/projects/{$project->id}"), settingsPayload($project, ['name' => 'Theirs now']))
        ->assertForbidden();

    expect($project->fresh()->name)->toBe('Nomad Bank');
});
