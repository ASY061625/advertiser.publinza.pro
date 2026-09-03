<?php

declare(strict_types=1);

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function generalUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function generalProject(User $user): Project
{
    $project = Project::factory()->for($user, 'owner')->create(['name' => 'Nomad Bank']);

    ProjectFolder::query()->create(['project_id' => $project->id, 'name' => 'General', 'sort_order' => 0]);

    return $project;
}

it('shows the general tab by default and for an unknown tab value', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    foreach (['', '?tab=general', '?tab=nonsense'] as $suffix) {
        $this->actingAs($user)
            ->get(advertiserUrl("/projects/{$project->id}{$suffix}"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Projects/Show')
                // An unrecognised tab lands on General rather than rendering an
                // empty panel or echoing the value back into the page.
                ->where('tab', 'general'));
    }
});

it('deep links every built tab', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    foreach (['settings', 'statistics', 'history', 'competitors'] as $tab) {
        $this->actingAs($user)
            ->get(advertiserUrl("/projects/{$project->id}?tab={$tab}"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tab', $tab));
    }
});

it('sends post management to the posts grid already filtered to the project', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    // Post management is the posts grid scoped to this project, not a second
    // copy of it living on the project page.
    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=posts"))
        ->assertRedirect(advertiserUrl('/posts?projects%5B0%5D='.$project->id));
});

it('counts the post mix into the four tiles', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    $make = function (PostStatus $status, int $count) use ($user, $project): void {
        Post::factory()->count($count)->for($user, 'advertiser')->for($project)->status($status)->create();
    };

    $make(PostStatus::New, 2);
    $make(PostStatus::InProgress, 1);
    $make(PostStatus::ContentReview, 1);
    $make(PostStatus::Completed, 3);
    // Not one of the four named buckets, but part of the total.
    $make(PostStatus::Cancelled, 1);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.posts.total', 8)
            ->where('stats.posts.new', 2)
            // In progress is two statuses, and the tile has to carry both.
            ->where('stats.posts.progress', 2)
            ->where('stats.posts.posted', 3)
            ->where('stats.posts.other', 1)
            ->etc());
});

it('keeps the stacked bar summing to the total it is printed beside', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    foreach ([PostStatus::New, PostStatus::InProgress, PostStatus::Completed, PostStatus::Rejected] as $status) {
        Post::factory()->for($user, 'advertiser')->for($project)->status($status)->create();
    }

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}"))
        ->assertInertia(function (AssertableInertia $page): void {
            $mix = $page->toArray()['props']['stats']['posts'];

            // The segments are disjoint and the fifth carries the remainder, so
            // the widths always add up to the number on the Tasks tile. A bar
            // that does not is describing a population that does not exist.
            expect($mix['new'] + $mix['progress'] + $mix['posted'] + $mix['frozen'] + $mix['other'])
                ->toBe($mix['total']);
        });
});

it('reports spend, frozen funds and an average that is null before anything completes', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create(['price_cents' => 20_000]);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.spentCents', 0)
            ->where('stats.frozenCents', 20_000)
            // Null, not zero. Zero would read as "these placements are free".
            ->where('stats.averageCents', null)
            ->etc());

    Post::factory()->count(2)->for($user, 'advertiser')->for($project)
        ->status(PostStatus::Completed)->create(['price_cents' => 30_000]);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.spentCents', 60_000)
            ->where('stats.averageCents', 30_000)
            ->etc());
});

it('describes each folder with what depends on it', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    $folder = ProjectFolder::query()->create([
        'project_id' => $project->id,
        'name' => 'Spring campaign',
        'publisher_task' => '<p>Mention the free trial and link the pricing page from the second paragraph.</p>',
        'sort_order' => 1,
    ]);

    LandingPage::query()->create([
        'project_id' => $project->id,
        'folder_id' => $folder->id,
        'anchor_text' => 'pricing',
        'url' => 'https://nomad.test/pricing',
    ]);

    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::InProgress)
        ->create(['folder_id' => $folder->id]);
    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::Completed)
        ->create(['folder_id' => $folder->id]);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}"))
        ->assertInertia(function (AssertableInertia $page) use ($folder): void {
            $rows = collect($page->toArray()['props']['folders']);
            $row = $rows->firstWhere('id', $folder->id);

            expect($row['landingPages'])->toBe(1)
                ->and($row['posts'])->toBe(2)
                // Only the non-terminal one blocks deletion.
                ->and($row['activePosts'])->toBe(1)
                // Tags stripped, truncated to 60 characters plus an ellipsis.
                ->and($row['taskExcerpt'])->toBe('Mention the free trial and link the pricing page from the…');
        });
});

it('adds, edits and deletes a folder', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    $this->actingAs($user)
        ->post(advertiserUrl("/projects/{$project->id}/folders"), [
            'name' => 'Spring campaign',
            'publisher_task' => '<p>Keep it plain.</p>',
        ])
        ->assertRedirect(advertiserUrl("/projects/{$project->id}"));

    $folder = ProjectFolder::query()->where('name', 'Spring campaign')->firstOrFail();
    expect($folder->publisher_task)->toContain('Keep it plain.');

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}/edit"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Folders/Edit')
            ->where('folder.name', 'Spring campaign'));

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"), [
            'name' => 'Summer campaign',
            'publisher_task' => '',
        ])
        ->assertRedirect();

    expect($folder->fresh()->name)->toBe('Summer campaign')
        // An emptied brief is null, so the folder falls back to the project's
        // rather than overriding it with an empty paragraph.
        ->and($folder->fresh()->publisher_task)->toBeNull();

    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"))
        ->assertSessionHas('success');

    expect(ProjectFolder::query()->find($folder->id))->toBeNull();
});

it('refuses to delete a folder that posts are still being written against', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    $folder = ProjectFolder::query()->create([
        'project_id' => $project->id, 'name' => 'Spring campaign', 'sort_order' => 1,
    ]);

    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::InProgress)
        ->create(['folder_id' => $folder->id]);

    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"))
        ->assertSessionHas('error');

    expect(ProjectFolder::query()->find($folder->id))->not->toBeNull()
        // And the reason names the count rather than saying "cannot delete".
        ->and(session('error'))->toContain('1 post');
});

it('refuses to delete a folder that still holds landing pages', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    $folder = ProjectFolder::query()->create([
        'project_id' => $project->id, 'name' => 'Spring campaign', 'sort_order' => 1,
    ]);

    LandingPage::query()->create([
        'project_id' => $project->id,
        'folder_id' => $folder->id,
        'anchor_text' => 'pricing',
        'url' => 'https://nomad.test/pricing',
    ]);

    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$project->id}/folders/{$folder->id}"))
        ->assertSessionHas('error');

    expect(ProjectFolder::query()->find($folder->id))->not->toBeNull();
});

it('refuses to delete the last folder, because landing pages have to live in one', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    $only = ProjectFolder::query()->where('project_id', $project->id)->firstOrFail();

    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$project->id}/folders/{$only->id}"))
        ->assertSessionHas('error');

    expect(ProjectFolder::query()->find($only->id))->not->toBeNull();
});

it('sanitises a folder brief on the way in', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    $this->actingAs($user)->post(advertiserUrl("/projects/{$project->id}/folders"), [
        'name' => 'Spring campaign',
        'publisher_task' => '<p onclick="steal()">Link <a href="javascript:alert(1)">here</a></p><script>x()</script>',
    ]);

    $stored = (string) ProjectFolder::query()->where('name', 'Spring campaign')->value('publisher_task');

    // Sanitised once, on write. The brief is publisher-facing, so a payload
    // typed here would otherwise run in somebody else's browser.
    expect($stored)->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->toContain('Link');
});

it('will not touch a folder belonging to someone else’s project', function (): void {
    $mine = generalProject(generalUser());
    $theirs = generalProject(generalUser());

    $theirFolder = ProjectFolder::query()->where('project_id', $theirs->id)->firstOrFail();

    // A folder id from another project is not this project's business, and
    // "forbidden" would confirm the id exists. It is a 404.
    $this->actingAs($mine->owner)
        ->get(advertiserUrl("/projects/{$mine->id}/folders/{$theirFolder->id}/edit"))
        ->assertNotFound();

    $this->actingAs($mine->owner)
        ->get(advertiserUrl("/projects/{$theirs->id}/folders/{$theirFolder->id}/edit"))
        ->assertForbidden();
});

it('says what is wrong with a folder that will not save', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    $this->actingAs($user)
        ->post(advertiserUrl("/projects/{$project->id}/folders"), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(session('errors')->first('name'))->toContain('Give the folder a name');
});

it('reports the same numbers on the project page as in the projects list', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    Post::factory()->count(3)->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create();
    Post::factory()->count(2)->for($user, 'advertiser')->for($project)->status(PostStatus::Completed)->create();

    $list = $this->actingAs($user)->get(advertiserUrl('/projects'))->viewData('page')['props']['projects'];
    $page = $this->actingAs($user)->get(advertiserUrl("/projects/{$project->id}"))->viewData('page')['props'];

    // The list showing 5 posts and the page showing 4 is a support ticket, so
    // both read the same aggregate and this test pins that they still do.
    expect($page['stats']['posts'])->toBe($list[0]['posts']);
});

it('holds a folder brief to the same limit the editor counts', function (): void {
    $user = generalUser();
    $project = generalProject($user);

    // Thirty bolded paragraphs of a hundred characters each: 3,000 characters
    // of text, and rather more markup around it. The limit is on what was
    // typed, so this passes — and one character more does not.
    $paragraph = fn (int $chars): string => '<p><strong>'.str_repeat('a', $chars).'</strong></p>';
    $brief = str_repeat($paragraph(100), 30);

    $this->actingAs($user)
        ->post(advertiserUrl("/projects/{$project->id}/folders"), [
            'name' => 'Long brief',
            'publisher_task' => $brief,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(advertiserUrl("/projects/{$project->id}/folders"), [
            'name' => 'Longer brief',
            'publisher_task' => $brief.$paragraph(1),
        ])
        ->assertSessionHasErrors('publisher_task');

    expect(session('errors')->first('publisher_task'))->toContain('3,000 characters');
});
