<?php

declare(strict_types=1);

use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Http\Middleware\HandleAdvertiserInertiaRequests;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function tabUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

it('locks the grid to the project however the query string is crafted', function (): void {
    $user = tabUser();
    $mine = Project::factory()->for($user, 'owner')->create();
    $theirs = Project::factory()->for($user, 'owner')->create();

    Post::factory()->count(2)->for($user, 'advertiser')->for($mine)->status(PostStatus::New)->create();
    Post::factory()->count(5)->for($user, 'advertiser')->for($theirs)->status(PostStatus::New)->create();

    // A `projects[]` naming another project cannot widen the scope, and is
    // dropped from the echoed filters rather than left on screen as a chip
    // for a filter that changes nothing.
    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$mine->id}?tab=posts&projects[]={$theirs->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('grid.posts.total', 2)
            ->missing('grid.filters.projects')
            ->where('grid.isFiltering', false)
            ->etc());
});

it('reads the status tab from its own key, leaving tab to the page', function (): void {
    $user = tabUser();
    $project = Project::factory()->for($user, 'owner')->create();

    Post::factory()->count(2)->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create();
    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::Completed)->create();

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=posts&posts_tab=posted"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            // The page tab is still Post management…
            ->where('tab', 'posts')
            // …and the grid's own tab is the one in posts_tab.
            ->where('grid.filters.tab', 'posted')
            ->where('grid.posts.total', 1)
            ->etc());
});

it('counts the status tabs for this project only', function (): void {
    $user = tabUser();
    $mine = Project::factory()->for($user, 'owner')->create();
    $theirs = Project::factory()->for($user, 'owner')->create();

    Post::factory()->count(2)->for($user, 'advertiser')->for($mine)->status(PostStatus::New)->create();
    Post::factory()->count(4)->for($user, 'advertiser')->for($theirs)->status(PostStatus::New)->create();

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$mine->id}?tab=posts"))
        ->assertInertia(function (AssertableInertia $page): void {
            $counts = $page->toArray()['props']['grid']['tabCounts'];

            expect($counts['all'])->toBe(2)
                ->and($counts['new'])->toBe(2)
                ->and($counts['posted'])->toBe(0);
        });
});

it('offers only this project’s folders to the promoted folder filter', function (): void {
    $user = tabUser();
    $mine = Project::factory()->for($user, 'owner')->create();
    $theirs = Project::factory()->for($user, 'owner')->create();

    ProjectFolder::query()->create(['project_id' => $mine->id, 'name' => 'Spring', 'sort_order' => 0]);
    ProjectFolder::query()->create(['project_id' => $theirs->id, 'name' => 'Theirs', 'sort_order' => 0]);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$mine->id}?tab=posts"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('grid.folders', 1)
            ->where('grid.folders.0.name', 'Spring')
            ->etc());
});

it('separates having no posts from matching no filters', function (): void {
    $user = tabUser();
    $project = Project::factory()->for($user, 'owner')->create();

    // Nothing at all: the invitation.
    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=posts"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('grid.hasAnyPosts', false)
            ->where('grid.isFiltering', false)
            ->etc());

    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create([
        'anchor_text' => 'best invoicing software',
    ]);

    // Posts, but none matching: a filter problem with a different fix.
    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=posts&q=nothing-like-this"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('grid.hasAnyPosts', true)
            ->where('grid.isFiltering', true)
            ->where('grid.posts.total', 0)
            ->etc());
});

it('marks a row the advertiser has unread messages on', function (): void {
    $user = tabUser();
    $project = Project::factory()->for($user, 'owner')->create();

    $quiet = Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create();
    $noisy = Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create();

    $talk = function (Post $post, SenderType $sender): void {
        $conversation = Conversation::query()->create([
            'user_id' => $post->user_id,
            'post_id' => $post->id,
            'website_id' => $post->website_id,
            'subject' => 'About this placement',
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => $sender,
            'body' => 'Anything at all.',
            'read_at' => null,
        ]);
    };

    $talk($noisy, SenderType::Admin);
    // The advertiser's own unread message is not news to them.
    $talk($quiet, SenderType::User);

    $this->actingAs($user)
        ->get(advertiserUrl("/projects/{$project->id}?tab=posts"))
        ->assertInertia(function (AssertableInertia $page) use ($noisy, $quiet): void {
            $rows = collect($page->toArray()['props']['grid']['posts']['data'])->keyBy('id');

            expect($rows[$noisy->id]['hasUnread'])->toBeTrue()
                ->and($rows[$quiet->id]['hasUnread'])->toBeFalse();
        });
});

it('will not open another advertiser’s project', function (): void {
    $project = Project::factory()->for(tabUser(), 'owner')->create();

    $this->actingAs(tabUser())
        ->get(advertiserUrl("/projects/{$project->id}?tab=posts"))
        ->assertForbidden();
});

it('keeps the global grid unscoped', function (): void {
    $user = tabUser();
    $a = Project::factory()->for($user, 'owner')->create();
    $b = Project::factory()->for($user, 'owner')->create();

    Post::factory()->for($user, 'advertiser')->for($a)->status(PostStatus::New)->create();
    Post::factory()->for($user, 'advertiser')->for($b)->status(PostStatus::New)->create();

    // The same component renders both surfaces; only the project page is
    // locked, and this pins that /posts did not inherit the lock.
    $this->actingAs($user)
        ->get(advertiserUrl('/posts'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('posts.total', 2)->etc());
});

it('answers a partial reload asking for the grid', function (): void {
    $user = tabUser();
    $project = Project::factory()->for($user, 'owner')->create();

    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create([
        'anchor_text' => 'best invoicing software',
    ]);
    Post::factory()->for($user, 'advertiser')->for($project)->status(PostStatus::New)->create([
        'anchor_text' => 'something else entirely',
    ]);

    // Every filter change is a partial reload asking for one prop. The grid is
    // nested under `grid` here, not spread across the top level the way /posts
    // has it — asking for the wrong name returns nothing and leaves the browser
    // showing the previous result under a URL that says otherwise, which is
    // exactly what happened before this was pinned.
    $response = $this->actingAs($user)->get(
        advertiserUrl("/projects/{$project->id}?tab=posts&q=invoicing"),
        [
            'X-Inertia' => 'true',
            // The middleware's own version, or Inertia answers 409 instead.
            'X-Inertia-Version' => (string) app(HandleAdvertiserInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Data' => 'grid',
            'X-Inertia-Partial-Component' => 'Projects/Show',
        ],
    );

    $props = $response->json('props');

    expect($props)->toHaveKey('grid')
        ->and($props['grid']['posts']['total'])->toBe(1)
        ->and($props['grid']['posts']['data'][0]['anchorText'])->toBe('best invoicing software');
});
