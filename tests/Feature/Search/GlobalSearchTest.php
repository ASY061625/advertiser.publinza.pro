<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\DTOs\SearchQuery;
use App\Domain\Search\Engines\DatabaseEngine;
use App\Domain\Search\Engines\MeilisearchEngine;
use App\Domain\Search\Models\RecentlyViewed;
use App\Domain\Search\Support\GlobalSearch;
use App\Domain\Search\Support\RecentlyViewedRecorder;
use App\Domain\Trading\Enums\ServiceType;
use App\Models\User;
use Meilisearch\Client;

/**
 * The groups keyed by their kind, for assertions that do not care about order.
 *
 * @param  list<array<string, mixed>>  $payload
 * @return array<string, array<string, mixed>>
 */
function groups(array $payload): array
{
    return collect($payload)->keyBy('key')->all();
}

/**
 * @return list<array<string, mixed>>
 */
function searchAs(User $user, string $term): array
{
    return app(GlobalSearch::class)->search($user, $term);
}

// -------------------------------------------------------------------- scoping

it('never returns one advertiser’s work to another', function (): void {
    $mine = buyer();
    $theirs = buyer();

    Project::factory()->create(['user_id' => $theirs->id, 'name' => 'Nordwind campaign']);

    $site = site(['domain' => 'nordwind-news.com']);

    Post::factory()->create([
        'user_id' => $theirs->id,
        'website_id' => $site->id,
        'anchor_text' => 'nordwind sleep guide',
    ]);

    Conversation::query()->create([
        'user_id' => $theirs->id,
        'website_id' => $site->id,
        'subject' => 'Nordwind placement question',
        'last_message_at' => now(),
    ]);

    $found = groups(searchAs($mine, 'nordwind'));

    // The catalog is shared, so the website is fair game. Everything that
    // belongs to somebody is not.
    expect($found)->toHaveKey('websites')
        ->and($found)->not->toHaveKey('projects')
        ->and($found)->not->toHaveKey('posts')
        ->and($found)->not->toHaveKey('conversations');
});

it('sends a user filter to the engine, not only to the hydration query', function (): void {
    $user = buyer();
    $seen = [];

    // Two locks on the same door. Hydration is the one that must hold, but a
    // search that asks the engine for everybody's rows wastes its limit on
    // them — and would leak if the hydration scope were ever dropped.
    $spy = new class($seen) implements SearchEngine
    {
        /**
         * @param  array<string, array<string, int|string>>  $captured
         */
        public function __construct(public array &$captured) {}

        /**
         * @return array<string, list<int>>
         */
        public function multiSearch(SearchQuery ...$queries): array
        {
            foreach ($queries as $query) {
                $this->captured[$query->key] = $query->filters;
            }

            return [];
        }
    };

    app()->instance(SearchEngine::class, $spy);

    searchAs($user, 'anything');

    $captured = $spy->captured;

    expect($captured['projects'])->toBe(['user_id' => $user->id])
        ->and($captured['posts'])->toBe(['user_id' => $user->id])
        ->and($captured['conversations'])->toBe(['user_id' => $user->id])
        // The catalog is not scoped, because it is the same for everybody.
        ->and($captured['websites'])->toBe([]);
});

// --------------------------------------------------------------- the groups

it('returns the five groups in the specified order', function (): void {
    $user = buyer();
    $site = site(['domain' => 'techweekly.com']);

    Project::factory()->create(['user_id' => $user->id, 'name' => 'Techweekly push']);

    Post::factory()->create([
        'user_id' => $user->id,
        'website_id' => $site->id,
        'anchor_text' => 'techweekly tooling',
    ]);

    Conversation::query()->create([
        'user_id' => $user->id,
        'website_id' => $site->id,
        'subject' => 'Techweekly brief',
        'last_message_at' => now(),
    ]);

    // Actions are assembled in the browser, so the server sends four.
    expect(array_column(searchAs($user, 'techweekly'), 'key'))
        ->toBe(['websites', 'projects', 'posts', 'conversations']);
});

it('caps every group at five and offers a way to the rest', function (): void {
    $user = buyer();

    foreach (range(1, 8) as $i) {
        Project::factory()->create(['user_id' => $user->id, 'name' => "Falcon {$i}"]);
    }

    $projects = groups(searchAs($user, 'falcon'))['projects'];

    expect($projects['items'])->toHaveCount(GlobalSearch::PER_GROUP)
        ->and($projects['seeAll'])->toBe('/projects');
});

it('carries what each group’s template renders', function (): void {
    $user = buyer();

    $site = site(['domain' => 'techweekly.com'], price: ['price_cents' => 240_00]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'name' => 'Techweekly push',
        'website_url' => 'https://nordwind.example',
        'color' => '#1D4ED8',
    ]);

    Post::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'website_id' => $site->id,
        'anchor_text' => 'techweekly tooling',
        'status' => PostStatus::Posted,
    ]);

    $conversation = Conversation::query()->create([
        'user_id' => $user->id,
        'website_id' => $site->id,
        'subject' => 'Techweekly brief',
        'last_message_at' => now(),
    ]);

    Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'admin',
        'body' => 'The publisher wants two links rather than three.',
    ]);

    $found = groups(searchAs($user, 'techweekly'));

    expect($found['websites']['items'][0])
        ->toMatchArray(['title' => 'techweekly.com', 'meta' => 240_00, 'href' => '/catalog/website/'.$site->slug])
        ->and($found['projects']['items'][0])
        ->toMatchArray(['title' => 'Techweekly push', 'subtitle' => 'https://nordwind.example', 'meta' => 1, 'color' => '#1D4ED8'])
        ->and($found['posts']['items'][0])
        // Opens the drawer over the post manager, which is that row's own link.
        ->toMatchArray(['title' => 'techweekly.com', 'subtitle' => 'techweekly tooling', 'status' => 'posted'])
        ->and($found['conversations']['items'][0]['excerpt'])
        ->toContain('two links rather than three');
});

it('sends a badge key the chip vocabulary actually has', function (): void {
    $user = buyer();
    $site = site(['domain' => 'techweekly.com']);

    Post::factory()->create([
        'user_id' => $user->id,
        'website_id' => $site->id,
        'anchor_text' => 'techweekly tooling',
        'status' => PostStatus::Completed,
    ]);

    $item = groups(searchAs($user, 'techweekly'))['posts']['items'][0];

    /*
     * Nine lifecycle states, eight badge colours. Completed reads in Posted's
     * green — and sending the raw value hands the Badge a key it does not have,
     * which it used to destructure blind. One completed post in the results
     * took the entire palette down.
     */
    expect($item['status'])->toBe('posted')
        ->and($item['statusLabel'])->toBe(PostStatus::Completed->label())
        ->and(array_values(array_unique(array_map(fn (PostStatus $s): string => $s->badgeKey(), PostStatus::cases()))))
        ->each->toBeIn(['draft', 'new', 'in_progress', 'content_review', 'posted', 'frozen', 'rejected', 'refunded']);
});

it('finds a post by the domain it is placed on, not only its anchor', function (): void {
    $user = buyer();
    $site = site(['domain' => 'gesundleben.at']);

    Post::factory()->create([
        'user_id' => $user->id,
        'website_id' => $site->id,
        'anchor_text' => 'better sleep',
    ]);

    // "That post on gesundleben" is how people look for one, and the posts
    // table has no such column — the domain is denormalised into the index.
    expect(groups(searchAs($user, 'gesundleben')))->toHaveKey('posts');
});

it('leaves a withdrawn site out even when the index still has it', function (): void {
    $user = buyer();
    $live = site(['domain' => 'techweekly.com']);
    $gone = site(['domain' => 'techweekly-old.com', 'is_active' => false]);

    $domains = array_column(groups(searchAs($user, 'techweekly'))['websites']['items'], 'title');

    // Hydration re-applies the real scoping, because an index is eventually
    // consistent and this is the difference between a live listing and a 404.
    expect($domains)->toBe([$live->domain])
        ->and($domains)->not->toContain($gone->domain);
});

it('says nothing at all for one character', function (): void {
    expect(searchAs(buyer(), 'a'))->toBe([]);
});

// ------------------------------------------------------------ recently viewed

it('opens on what this person looked at last', function (): void {
    $user = buyer();
    $first = site(['domain' => 'one.com']);
    $second = site(['domain' => 'two.com']);
    $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Nordwind']);

    $recorder = app(RecentlyViewedRecorder::class);
    $recorder->record($user, RecentlyViewedRecorder::WEBSITE, $first->id);
    $this->travel(1)->minute();
    $recorder->record($user, RecentlyViewedRecorder::WEBSITE, $second->id);
    $recorder->record($user, RecentlyViewedRecorder::PROJECT, $project->id);

    $found = groups(app(GlobalSearch::class)->recent($user));

    expect(array_column($found['websites']['items'], 'title'))
        // Newest first.
        ->toBe(['two.com', 'one.com'])
        ->and($found['projects']['items'][0]['title'])->toBe('Nordwind')
        // "See all" under a list of things you looked at yesterday has nowhere
        // honest to point.
        ->and($found['websites']['seeAll'])->toBeNull();
});

it('counts nine views of one site as one site', function (): void {
    $user = buyer();
    $site = site(['domain' => 'one.com']);
    $recorder = app(RecentlyViewedRecorder::class);

    foreach (range(1, 9) as $i) {
        $recorder->record($user, RecentlyViewedRecorder::WEBSITE, $site->id);
    }

    expect(RecentlyViewed::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('records a view when the website drawer is opened', function (): void {
    $user = buyer();
    $site = site(['domain' => 'techweekly.com']);

    $this->actingAs($user)->getJson(advertiserUrl("/catalog/website/{$site->slug}"))->assertOk();

    expect(app(RecentlyViewedRecorder::class)->recent($user, RecentlyViewedRecorder::WEBSITE))
        ->toBe([$site->id]);
});

it('records a view when a project is opened', function (): void {
    $user = buyer();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(advertiserUrl("/projects/{$project->id}"))->assertOk();

    expect(app(RecentlyViewedRecorder::class)->recent($user, RecentlyViewedRecorder::PROJECT))
        ->toBe([$project->id]);
});

// ------------------------------------------------------------- the endpoint

it('answers an empty box with recent work rather than nothing', function (): void {
    $user = buyer();
    $site = site(['domain' => 'techweekly.com']);

    app(RecentlyViewedRecorder::class)->record($user, RecentlyViewedRecorder::WEBSITE, $site->id);

    $response = $this->actingAs($user)->getJson(advertiserUrl('/search?q='))->assertOk();

    expect($response->json('recent'))->toBeTrue()
        ->and($response->json('groups.0.items.0.title'))->toBe('techweekly.com');
});

it('answers a real query with matches', function (): void {
    $user = buyer();
    site(['domain' => 'techweekly.com']);

    $response = $this->actingAs($user)->getJson(advertiserUrl('/search?q=techweekly'))->assertOk();

    expect($response->json('recent'))->toBeFalse()
        ->and($response->json('query'))->toBe('techweekly')
        ->and($response->json('groups.0.key'))->toBe('websites');
});

it('turns anybody away who is not signed in', function (): void {
    $this->get(advertiserUrl('/search?q=techweekly'))->assertRedirect();
});

// ----------------------------------------------------------------- the engine

it('escapes LIKE wildcards so an underscore is a character', function (): void {
    $user = buyer();
    $wanted = site(['domain' => 'a_b.com']);
    site(['domain' => 'axb.com']);

    expect(array_column(groups(searchAs($user, 'a_b'))['websites']['items'], 'title'))
        ->toBe([$wanted->domain]);
});

it('reaches a relation through dot notation', function (): void {
    $user = buyer();
    $site = site(['domain' => 'gesundleben.at']);

    Post::factory()->create(['user_id' => $user->id, 'website_id' => $site->id, 'anchor_text' => 'sleep']);

    $ids = (new DatabaseEngine)->multiSearch(new SearchQuery(
        key: 'posts',
        model: Post::class,
        term: 'gesundleben',
        limit: 5,
        columns: ['anchor_text', 'website.domain'],
        filters: ['user_id' => $user->id],
    ));

    expect($ids['posts'])->toHaveCount(1);
});

it('names the index Scout writes to, without doubling the prefix', function (): void {
    $engine = new MeilisearchEngine(app(Client::class));

    $index = (new ReflectionMethod($engine, 'indexFor'))->invoke($engine, Post::class);

    // searchableAs() already carries scout.prefix. Prepending it again produces
    // an index that exists nowhere and returns nothing, quietly.
    expect($index)->toBe(config('scout.prefix').'posts');
});

it('builds a filter expression Meilisearch will accept', function (): void {
    $engine = new MeilisearchEngine(app(Client::class));

    $filter = (new ReflectionMethod($engine, 'filter'))->invoke($engine, ['user_id' => 7, 'status' => 'op"en']);

    expect($filter)->toBe('user_id = 7 AND status = "op\"en"');
});

it('degrades to no results rather than an error when the engine is down', function (): void {
    // A palette is an accelerator; every group in it has a page behind it. A
    // search service having a bad minute must not turn Cmd+K into a dialog.
    $engine = new MeilisearchEngine(new Client('http://127.0.0.1:9', 'nope'));

    expect($engine->multiSearch(new SearchQuery(
        key: 'websites',
        model: Website::class,
        term: 'anything',
        limit: 5,
    )))->toBe([]);
});

it('quotes the price the catalog is selling at, not the one the index holds', function (): void {
    $user = buyer();
    $site = site(['domain' => 'techweekly.com'], price: ['price_cents' => 100_00]);

    // The row moves after indexing, as prices do.
    $site->priceFor(ServiceType::ArticlePlacement)->update(['price_cents' => 275_00]);

    expect(groups(searchAs($user, 'techweekly'))['websites']['items'][0]['meta'])->toBe(275_00);
});
