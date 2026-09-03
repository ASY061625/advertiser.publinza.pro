<?php

declare(strict_types=1);

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function editorUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

/**
 * @return array{0: User, 1: Project, 2: ProjectFolder}
 */
function editorProject(): array
{
    $user = editorUser();

    $project = Project::factory()->for($user, 'owner')->create([
        'name' => 'Nomad Bank',
        'website_url' => 'https://nomadbank.test',
        'publisher_task' => '<p>House style: plain, no superlatives.</p>',
    ]);

    ProjectFolder::query()->create(['project_id' => $project->id, 'name' => 'General', 'sort_order' => 0]);

    $folder = ProjectFolder::query()->create([
        'project_id' => $project->id,
        'name' => 'Spring campaign',
        'sort_order' => 1,
    ]);

    return [$user, $project, $folder];
}

function editorPage(Project $project, ProjectFolder $folder, string $anchor, string $path, int $order = 0): LandingPage
{
    return LandingPage::query()->create([
        'project_id' => $project->id,
        'folder_id' => $folder->id,
        'anchor_text' => $anchor,
        'url' => "https://nomadbank.test{$path}",
        'sort_order' => $order,
    ]);
}

it('opens the editor with the folder, its pages and the project brief to copy', function (): void {
    [$user, $project, $folder] = editorProject();

    editorPage($project, $folder, 'pricing', '/pricing');
    editorPage($project, $folder, 'how we handle VAT', '/vat', 1);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}/edit"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Folders/Edit')
            ->where('folder.name', 'Spring campaign')
            ->where('folder.isOnlyFolder', false)
            ->where('folder.activePosts', 0)
            // Every target URL is validated against this, and "Copy from
            // project" is only offered when there is a brief to copy.
            ->where('project.websiteUrl', 'https://nomadbank.test')
            ->where('project.publisherTask', '<p>House style: plain, no superlatives.</p>')
            ->has('landingPages', 2)
            ->where('landingPages.0.anchor_text', 'pricing')
            ->where('landingPages.1.anchor_text', 'how we handle VAT'));
});

it('counts how many posts already point at each landing page', function (): void {
    [$user, $project, $folder] = editorProject();

    editorPage($project, $folder, 'pricing', '/pricing');
    editorPage($project, $folder, 'how we handle VAT', '/vat', 1);

    Post::factory()->count(3)->for($user, 'advertiser')->for($project)->status(PostStatus::Completed)->create([
        'anchor_text' => 'pricing',
        'target_url' => 'https://nomadbank.test/pricing',
    ]);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}/edit"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('landingPages.0.usage', 3)
            // A pair nothing points at is free to remove.
            ->where('landingPages.1.usage', 0)
            ->etc());
});

it('saves the name, the brief and the landing pages in the order they were dragged into', function (): void {
    [$user, $project, $folder] = editorProject();

    $first = editorPage($project, $folder, 'pricing', '/pricing');
    $second = editorPage($project, $folder, 'how we handle VAT', '/vat', 1);

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => 'Summer campaign',
            'publisher_task' => '<p>Mention the free trial.</p>',
            'landing_pages' => [
                // Swapped, plus one new row.
                ['id' => $second->id, 'anchor_text' => 'how we handle VAT', 'url' => 'https://nomadbank.test/vat'],
                ['id' => $first->id, 'anchor_text' => 'pricing and plans', 'url' => 'https://nomadbank.test/pricing'],
                ['anchor_text' => 'our API', 'url' => 'https://nomadbank.test/api'],
            ],
        ])
        ->assertRedirect(advertiserUrl("/projects/{$project->id}"))
        ->assertSessionHas('success', 'Saved')
        // Read once by the General tab so the row it wrote can be pointed at.
        ->assertSessionHas('folder_saved', $folder->id);

    $folder->refresh();
    expect($folder->name)->toBe('Summer campaign')
        ->and($folder->publisher_task)->toContain('free trial');

    $pages = LandingPage::query()
        ->where('folder_id', $folder->id)
        ->orderBy('sort_order')
        ->get(['id', 'anchor_text']);

    expect($pages->pluck('anchor_text')->all())
        ->toBe(['how we handle VAT', 'pricing and plans', 'our API'])
        // Reordered and renamed in place, not deleted and recreated: the ids
        // survive, so anything pointing at them still resolves.
        ->and($pages->pluck('id')->take(2)->all())->toBe([$second->id, $first->id]);
});

it('deletes a landing page the browser stopped sending', function (): void {
    [$user, $project, $folder] = editorProject();

    $keep = editorPage($project, $folder, 'pricing', '/pricing');
    $drop = editorPage($project, $folder, 'how we handle VAT', '/vat', 1);

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => 'Spring campaign',
            'landing_pages' => [
                ['id' => $keep->id, 'anchor_text' => 'pricing', 'url' => 'https://nomadbank.test/pricing'],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(LandingPage::query()->find($drop->id))->toBeNull()
        ->and(LandingPage::query()->find($keep->id))->not->toBeNull();
});

it('refuses to remove a landing page that posts already point at', function (): void {
    [$user, $project, $folder] = editorProject();

    $used = editorPage($project, $folder, 'pricing', '/pricing');

    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::Completed)->create([
        'anchor_text' => 'pricing',
        'target_url' => 'https://nomadbank.test/pricing',
    ]);

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => 'Spring campaign',
            'landing_pages' => [],
        ])
        ->assertSessionHasErrors('landing_pages');

    // The whole save is rolled back, not just the removal — a half-applied
    // form is worse than a refused one.
    expect(LandingPage::query()->find($used->id))->not->toBeNull()
        ->and($folder->fresh()->name)->toBe('Spring campaign')
        ->and(session('errors')->first('landing_pages'))->toContain('1 post');
});

it('refuses a landing page pointing at another site', function (): void {
    [$user, $project, $folder] = editorProject();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => 'Spring campaign',
            'landing_pages' => [
                ['anchor_text' => 'pricing', 'url' => 'https://nomadbank.test/pricing'],
                ['anchor_text' => 'a competitor', 'url' => 'https://somewhere-else.test/pricing'],
            ],
        ])
        ->assertSessionHasErrors('landing_pages.1.url');

    expect(session('errors')->first('landing_pages.1.url'))
        ->toContain('nomadbank.test')
        ->toContain('somewhere-else.test');

    // A subdomain of the promoted site is the same site and is allowed.
    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => 'Spring campaign',
            'landing_pages' => [['anchor_text' => 'help', 'url' => 'https://help.nomadbank.test/getting-started']],
        ])
        ->assertSessionHasNoErrors();
});

it('says what is wrong with a landing page row, by row', function (): void {
    [$user, $project, $folder] = editorProject();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => '',
            'landing_pages' => [
                ['anchor_text' => '', 'url' => 'https://nomadbank.test/pricing'],
                ['anchor_text' => 'vat', 'url' => ''],
            ],
        ])
        ->assertSessionHasErrors(['name', 'landing_pages.0.anchor_text', 'landing_pages.1.url']);

    $errors = session('errors');

    expect($errors->first('name'))->toContain('Give the folder a name')
        ->and($errors->first('landing_pages.0.anchor_text'))->toContain('anchor text')
        ->and($errors->first('landing_pages.1.url'))->toContain('target URL');
});

it('holds the folder name to 80 characters', function (): void {
    [$user, $project, $folder] = editorProject();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => str_repeat('a', 80),
            'landing_pages' => [],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => str_repeat('a', 81),
            'landing_pages' => [],
        ])
        ->assertSessionHasErrors('name');
});

it('creates a folder and its landing pages in one submit', function (): void {
    [$user, $project] = editorProject();

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}/folders/create"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Folders/Edit')
            ->where('folder', null)
            ->has('landingPages', 0));

    $this->actingAs($user)
        ->post(advertiserUrl("/projects/{$project->id}/folders"), [
            'name' => 'Autumn campaign',
            'publisher_task' => '',
            'landing_pages' => [
                ['anchor_text' => 'pricing', 'url' => 'https://nomadbank.test/pricing'],
            ],
        ])
        ->assertRedirect(advertiserUrl("/projects/{$project->id}"))
        ->assertSessionHas('success', 'Saved');

    $folder = ProjectFolder::query()->where('name', 'Autumn campaign')->firstOrFail();

    expect($folder->publisher_task)->toBeNull()
        ->and(LandingPage::query()->where('folder_id', $folder->id)->count())->toBe(1)
        // Appended, so it does not displace the order already arranged.
        ->and($folder->sort_order)->toBe(2);
});

it('ignores a landing page id that belongs to another folder', function (): void {
    [$user, $project, $folder] = editorProject();

    $other = ProjectFolder::query()->where('project_id', $project->id)->where('name', 'General')->firstOrFail();
    $theirs = editorPage($project, $other, 'their page', '/theirs');

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => 'Spring campaign',
            'landing_pages' => [
                ['id' => $theirs->id, 'anchor_text' => 'hijacked', 'url' => 'https://nomadbank.test/hijacked'],
            ],
        ])
        ->assertSessionHasNoErrors();

    // Created fresh in this folder rather than editing a row that belongs
    // somewhere else. The other folder's page is untouched.
    expect($theirs->fresh()->anchor_text)->toBe('their page')
        ->and($theirs->fresh()->folder_id)->toBe($other->id)
        ->and(LandingPage::query()->where('folder_id', $folder->id)->value('anchor_text'))->toBe('hijacked');
});

it('will not save a folder belonging to someone else', function (): void {
    [, $project, $folder] = editorProject();
    $intruder = editorUser();

    $this->actingAs($intruder)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => 'Theirs now',
            'landing_pages' => [],
        ])
        ->assertForbidden();

    expect($folder->fresh()->name)->toBe('Spring campaign');
});
