<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

function actionsUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

it('creates a project that can be read back', function (): void {
    $user = actionsUser();
    $category = WebsiteCategory::factory()->create();

    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), [
            'name' => 'Nomad Bank',
            'website_url' => 'https://nomad.test',
            'category_id' => $category->id,
            'publisher_task' => 'Keep it plain.',
            // /projects is the wizard's submit, and a project is not finished
            // without somewhere for its links to point.
            'landing_pages' => [['anchor_text' => 'pricing', 'url' => 'https://nomad.test/pricing']],
        ])
        ->assertRedirect();

    $project = Project::query()->where('user_id', $user->id)->firstOrFail();

    // Reading the row back is the whole assertion: it used to be written with
    // a status the enum has never had, and with post columns that do not exist
    // on this table, so the create either threw or produced an unreadable row.
    expect($project->name)->toBe('Nomad Bank')
        ->and($project->website_url)->toBe('https://nomad.test')
        ->and($project->category_id)->toBe($category->id)
        ->and($project->publisher_task)->toContain('Keep it plain.')
        ->and($project->status)->toBe(ProjectStatus::Active);

    // And the list renders it, which is what actually reads the status back.
    $this->actingAs($user)->get(advertiserUrl('/projects'))->assertOk();
});

it('says what is wrong with a project that will not save', function (): void {
    $user = actionsUser();

    $this->actingAs($user)
        ->post(advertiserUrl('/projects'), ['name' => '', 'website_url' => 'not-a-url'])
        ->assertSessionHasErrors(['name', 'website_url']);

    expect(Project::query()->count())->toBe(0);
});

it('lets an owner edit an active project and refuses an archived one', function (): void {
    $user = actionsUser();
    $active = Project::factory()->create(['user_id' => $user->id, 'status' => ProjectStatus::Active]);
    $archived = Project::factory()->create(['user_id' => $user->id, 'status' => ProjectStatus::Archived]);

    // The policy compared status against the string 'draft', so under a strict
    // comparison against the enum it denied every edit that ever reached it.
    expect(Gate::forUser($user)->allows('update', $active))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $archived))->toBeFalse()
        ->and(Gate::forUser($user)->allows('restore', $archived))->toBeTrue();

    $this->actingAs($user)
        ->put(advertiserUrl("/projects/{$active->id}"), [
            'name' => 'Renamed',
            'website_url' => 'https://renamed.test',
        ])
        ->assertSessionHasNoErrors();

    expect($active->fresh()->name)->toBe('Renamed');
});

it('archives and restores a project', function (): void {
    $user = actionsUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(advertiserUrl("/projects/{$project->id}/archive"))->assertRedirect();
    expect($project->fresh()->status)->toBe(ProjectStatus::Archived);

    $this->actingAs($user)->post(advertiserUrl("/projects/{$project->id}/restore"))->assertRedirect();
    expect($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('leaves in-flight posts alone when a project is archived', function (): void {
    $user = actionsUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $post = Post::factory()->status(PostStatus::InProgress)
        ->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $this->actingAs($user)->post(advertiserUrl("/projects/{$project->id}/archive"));

    // Archiving says "I have finished adding to this", not "abandon the work
    // I have already paid for" — cancelling would move money.
    expect($post->fresh()->status)->toBe(PostStatus::InProgress);
});

it('refuses to delete a project whose posts are still in flight, and says why', function (): void {
    $user = actionsUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Post::factory()->status(PostStatus::InProgress)
        ->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$project->id}"), ['name' => $project->name])
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '1 post')
            && str_contains($message, 'still in progress'));

    expect(Project::query()->find($project->id))->not->toBeNull();
});

it('deletes a project once every post has reached a terminal state', function (): void {
    $user = actionsUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $completed = Post::factory()->status(PostStatus::Completed)
        ->create(['user_id' => $user->id, 'project_id' => $project->id]);
    Post::factory()->status(PostStatus::Rejected)
        ->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$project->id}"), ['name' => $project->name])
        ->assertRedirect(advertiserUrl('/projects'))
        ->assertSessionHas('success');

    expect(Project::query()->find($project->id))->toBeNull()
        // Soft deleted, and its posts still resolve: an invoice referencing a
        // placement has to keep working after the project is gone.
        ->and(Project::withTrashed()->find($project->id))->not->toBeNull()
        ->and($completed->fresh())->not->toBeNull();
});

it('refuses a delete whose typed name does not match, server-side', function (): void {
    $user = actionsUser();
    $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Nomad Bank']);

    // A confirmation only enforced in the browser is not a confirmation.
    $this->actingAs($user)
        ->delete(advertiserUrl("/projects/{$project->id}"), ['name' => 'nomad bank'])
        ->assertSessionHas('error');

    expect(Project::query()->find($project->id))->not->toBeNull();
});

it('will not let one advertiser touch another\'s project', function (): void {
    $mine = actionsUser();
    $theirs = actionsUser();
    $project = Project::factory()->create(['user_id' => $theirs->id]);

    $this->actingAs($mine)->get(advertiserUrl("/projects/{$project->id}"))->assertForbidden();
    $this->actingAs($mine)->post(advertiserUrl("/projects/{$project->id}/archive"))->assertForbidden();
    $this->actingAs($mine)
        ->delete(advertiserUrl("/projects/{$project->id}"), ['name' => $project->name])
        ->assertForbidden();

    expect(Project::query()->find($project->id))->not->toBeNull();
});

it('renders every page component the project routes serve', function (): void {
    $user = actionsUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Inertia resolves page names against resources/js/advertiser/Pages and
    // throws in the browser when one is missing — which no assertion about the
    // response status would ever catch.
    foreach (['Projects/Index', 'Projects/Create', 'Projects/Show'] as $component) {
        expect(base_path("resources/js/advertiser/Pages/{$component}.tsx"))->toBeFile(
            "Inertia page {$component} has no component file",
        );
    }

    $this->actingAs($user)->get(advertiserUrl('/projects/create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Projects/Create')->has('categories'));

    $this->actingAs($user)->get(advertiserUrl("/projects/{$project->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Projects/Show'));
});
