<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Posts\DTOs\PostFilters;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Enums\PostTab;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;

function gridUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function gridPost(User $user, Project $project, array $attributes = []): Post
{
    return Post::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'website_id' => Website::factory()->create()->id,
        ...$attributes,
    ]);
}

it('every post status belongs to exactly one tab', function (): void {
    foreach (PostStatus::cases() as $status) {
        $tabs = array_filter(
            PostTab::cases(),
            fn (PostTab $tab): bool => in_array($status, $tab->statuses(), true),
        );

        expect($tabs)->toHaveCount(1, "{$status->value} is not in exactly one tab");
    }
});

it('renders the grid with its filters, counts and options', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    gridPost($user, $project);

    $this->actingAs($user)
        ->get(advertiserUrl('/posts'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Posts/Index')
            ->has('posts.data', 1)
            ->has('tabCounts.all')
            ->has('options.projects')
            ->has('columns.order')
            ->where('hasAnyPosts', true)
            ->where('isFiltering', false)
        );
});

it('never shows one advertiser another advertiser\'s posts', function (): void {
    $mine = gridUser();
    $theirs = gridUser();
    $project = Project::factory()->create(['user_id' => $theirs->id]);
    gridPost($theirs, $project);

    $this->actingAs($mine)
        ->get(advertiserUrl('/posts'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('posts.data', 0)
            ->where('hasAnyPosts', false)
        );
});

it('groups completed with posted and refunded with cancelled so the tabs sum to all', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Post::factory()->status(PostStatus::Posted)->create(['user_id' => $user->id, 'project_id' => $project->id]);
    Post::factory()->status(PostStatus::Completed)->create(['user_id' => $user->id, 'project_id' => $project->id]);
    Post::factory()->status(PostStatus::Cancelled)->create(['user_id' => $user->id, 'project_id' => $project->id]);
    Post::factory()->status(PostStatus::Refunded)->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $counts = $this->actingAs($user)->get(advertiserUrl('/posts'))
        ->viewData('page')['props']['tabCounts'];

    expect($counts['posted'])->toBe(2)
        ->and($counts['cancelled'])->toBe(2)
        ->and($counts['all'])->toBe(4)
        // The All count is the sum of the rest, always.
        ->and(array_sum(array_diff_key($counts, ['all' => null])))->toBe($counts['all']);
});

it('filters by tab, and the tab counts ignore the tab itself', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Post::factory()->count(3)->status(PostStatus::Posted)->create(['user_id' => $user->id, 'project_id' => $project->id]);
    Post::factory()->count(2)->status(PostStatus::New)->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/posts?tab=posted'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('posts.data', 3)
            // Still says how many New there are — otherwise the number on a tab
            // you are not standing on would always read zero.
            ->where('tabCounts.new', 2)
        );
});

it('searches domain, anchor, target URL and post id', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $site = Website::factory()->create(['domain' => 'quietledger.test']);
    $wanted = Post::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'website_id' => $site->id,
        'anchor_text' => 'best invoicing tool',
        'target_url' => 'https://acme.test/pricing',
    ]);
    gridPost($user, $project, ['anchor_text' => 'unrelated', 'target_url' => 'https://other.test/']);

    foreach (['quietledger', 'invoicing', 'pricing', (string) $wanted->id] as $term) {
        $this->actingAs($user)
            ->get(advertiserUrl('/posts?q='.urlencode($term)))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $wanted->id)
            );
    }
});

it('treats a wildcard in the search term as text, not as a pattern', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    gridPost($user, $project, ['anchor_text' => 'plain anchor']);

    // A bare % would match every row if it reached SQL unescaped.
    $this->actingAs($user)
        ->get(advertiserUrl('/posts?q=%25'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('posts.data', 0));
});

it('filters by unread messages from the publisher only', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $unread = gridPost($user, $project);
    $mineOnly = gridPost($user, $project);

    foreach ([[$unread, SenderType::Admin, null], [$mineOnly, SenderType::User, null]] as [$post, $sender, $readAt]) {
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'subject' => 'About this post',
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => $sender,
            'body' => 'Hello',
            'read_at' => $readAt,
        ]);
    }

    // The advertiser's own unread message is not unread *to them*.
    $this->actingAs($user)
        ->get(advertiserUrl('/posts?unread=1'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $unread->id)
        );
});

it('filters by deadline window and by overdue', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $soon = gridPost($user, $project, ['deadline_at' => now()->addHours(10)]);
    $later = gridPost($user, $project, ['deadline_at' => now()->addDays(5)]);
    $late = gridPost($user, $project, ['deadline_at' => now()->subDays(2)]);

    $ids = fn (string $window): array => array_column(
        $this->actingAs($user)->get(advertiserUrl("/posts?deadline={$window}"))
            ->viewData('page')['props']['posts']['data'],
        'id',
    );

    expect($ids('24h'))->toBe([$soon->id])
        ->and($ids('7d'))->toEqualCanonicalizing([$soon->id, $later->id])
        ->and($ids('overdue'))->toBe([$late->id]);
});

it('sorts nulls last so "not published yet" does not top a descending list', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $published = gridPost($user, $project, ['published_at' => now()->subDay()]);
    $pending = gridPost($user, $project, ['published_at' => null]);

    $ids = array_column(
        $this->actingAs($user)->get(advertiserUrl('/posts?sort=published_at&direction=desc'))
            ->viewData('page')['props']['posts']['data'],
        'id',
    );

    expect($ids)->toBe([$published->id, $pending->id]);
});

it('paginates server-side at 25, 50 or 100 and refuses anything else', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Post::factory()->count(30)->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $this->actingAs($user)->get(advertiserUrl('/posts'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('posts.data', 25));

    $this->actingAs($user)->get(advertiserUrl('/posts?per_page=50'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('posts.data', 30));

    // 5000 rows in one response is a denial of service with extra steps.
    $this->actingAs($user)->get(advertiserUrl('/posts?per_page=5000'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('posts.data', 25));
});

it('tells an empty account apart from an empty filter result', function (): void {
    $user = gridUser();

    $this->actingAs($user)->get(advertiserUrl('/posts'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hasAnyPosts', false)
            ->where('isFiltering', false)
        );

    $project = Project::factory()->create(['user_id' => $user->id]);
    gridPost($user, $project);

    $this->actingAs($user)->get(advertiserUrl('/posts?q=nothingmatchesthis'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('posts.data', 0)
            ->where('hasAnyPosts', true)
            ->where('isFiltering', true)
        );
});

it('round-trips every filter through the query string without losing one', function (): void {
    $query = [
        'tab' => 'posted', 'q' => 'news', 'projects' => [1, 2], 'statuses' => ['posted'],
        'date_field' => 'published', 'from' => '2026-01-01', 'to' => '2026-06-30',
        'categories' => [3], 'countries' => [5], 'languages' => [2],
        'min_price' => 50, 'max_price' => 900, 'content_mode' => 'publisher_writes',
        'anchor' => 'best', 'target' => '/pricing', 'min_dr' => 20, 'max_dr' => 80,
        'min_traffic' => 1000, 'max_traffic' => 500000, 'folder' => 7, 'unread' => 1,
        'deadline' => '3d', 'sort' => 'price', 'direction' => 'asc', 'per_page' => 50,
    ];

    $filters = PostFilters::fromRequest(Request::create('/posts', 'GET', $query));
    $out = $filters->toQuery();

    // Nothing dropped, and applying the output reproduces itself exactly — a
    // shared link has to mean the same thing to the person who receives it.
    expect(array_diff_key($query, $out))->toBe([])
        ->and(PostFilters::fromRequest(Request::create('/posts', 'GET', $out))->toQuery())->toBe($out);
});

it('leaves a plain grid with a plain URL', function (): void {
    expect(PostFilters::fromRequest(Request::create('/posts'))->toQuery())->toBe([]);
});

it('renders only page components that exist in the advertiser bundle', function (): void {
    // Inertia resolves page names against resources/js/advertiser/Pages and
    // throws in the browser when one is missing — which no server-side
    // assertion about the response would ever catch.
    expect(base_path('resources/js/advertiser/Pages/Posts/Index.tsx'))->toBeFile();
});

it('sends an old per-post URL to the grid with that row open', function (): void {
    $user = gridUser();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $post = gridPost($user, $project);

    $this->actingAs($user)
        ->get(advertiserUrl("/posts/{$post->id}"))
        ->assertRedirect(advertiserUrl("/posts?post={$post->id}"));
});

it('refuses an old per-post URL for someone else\'s post', function (): void {
    $mine = gridUser();
    $theirs = gridUser();
    $project = Project::factory()->create(['user_id' => $theirs->id]);
    $post = gridPost($theirs, $project);

    $this->actingAs($mine)->get(advertiserUrl("/posts/{$post->id}"))->assertForbidden();
});
