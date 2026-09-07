<?php

declare(strict_types=1);

namespace App\Domain\Search\Support;

use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\DTOs\SearchQuery;
use App\Domain\Trading\Enums\ServiceType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What Cmd+K asks, and what it gets back.
 *
 * Four indexes in one round trip, then one hydration query per group that had
 * hits. The ids come from the engine; every field the palette renders comes
 * from the database, because an index is eventually consistent and a palette
 * that renders a price out of it will one day quote a price nobody is selling
 * at.
 */
final class GlobalSearch
{
    /** Per group, as specified. The "See all" link carries the rest. */
    public const PER_GROUP = 5;

    /**
     * Asked of the engine, before hydration.
     *
     * Deliberately more than PER_GROUP. Hydration re-applies the real scoping —
     * active sites only, not soft-deleted, this account's rows — and an index
     * that is a few seconds stale can hand back ids that no longer pass it.
     * Asking for five and showing three because two were withdrawn is a worse
     * answer than asking for fifteen.
     */
    private const CANDIDATES = self::PER_GROUP * 3;

    /** Shorter than this and every catalog domain matches. */
    public const MIN_TERM = 2;

    public function __construct(
        private readonly SearchEngine $engine,
        private readonly RecentlyViewedRecorder $recent,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(User $user, string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_TERM) {
            return [];
        }

        $ids = $this->engine->multiSearch(
            new SearchQuery(
                key: 'websites',
                model: Website::class,
                term: $term,
                limit: self::CANDIDATES,
                columns: ['domain', 'title'],
            ),
            /*
             * user_id on all three, and not as a nicety.
             *
             * These indexes hold every advertiser's rows in one place. The
             * hydration below scopes by owner as well — two locks on the same
             * door, because this is the one bug in a search palette that leaks
             * one customer's data to another, and it would look exactly like a
             * working feature.
             */
            new SearchQuery(
                key: 'projects',
                model: Project::class,
                term: $term,
                limit: self::CANDIDATES,
                columns: ['name', 'website_url'],
                filters: ['user_id' => $user->id],
            ),
            new SearchQuery(
                key: 'posts',
                model: Post::class,
                term: $term,
                limit: self::CANDIDATES,
                columns: ['anchor_text', 'target_url', 'website.domain'],
                filters: ['user_id' => $user->id],
            ),
            new SearchQuery(
                key: 'conversations',
                model: Conversation::class,
                term: $term,
                limit: self::CANDIDATES,
                columns: ['subject', 'website.domain'],
                filters: ['user_id' => $user->id],
            ),
        );

        return array_values(array_filter([
            $this->websites($ids['websites'] ?? [], $term),
            $this->projects($user, $ids['projects'] ?? [], $term),
            $this->posts($user, $ids['posts'] ?? [], $term),
            $this->conversations($user, $ids['conversations'] ?? [], $term),
        ]));
    }

    /**
     * What the palette shows before anything is typed.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(User $user): array
    {
        return array_values(array_filter([
            $this->websites($this->recent->recent($user, RecentlyViewedRecorder::WEBSITE), null, 'Recently viewed'),
            $this->projects($user, $this->recent->recent($user, RecentlyViewedRecorder::PROJECT), null, 'Recent projects'),
        ]));
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>|null
     */
    private function websites(array $ids, ?string $term, string $label = 'Websites'): ?array
    {
        if ($ids === []) {
            return null;
        }

        $rows = Website::query()
            ->active()
            ->with(['category:id,name', 'prices'])
            ->whereIn('id', $ids)
            ->get(['id', 'domain', 'slug', 'category_id']);

        return $this->group($label, 'websites', $this->order($rows, $ids)->map(fn (Website $site): array => [
            'id' => "website-{$site->id}",
            'title' => $site->domain,
            'subtitle' => $site->category?->name,
            // The article-placement price, which is what the catalog leads with
            // and therefore what somebody is comparing against.
            'meta' => $site->priceFor(ServiceType::ArticlePlacement)?->price_cents,
            /*
             * The drawer's own address.
             *
             * /catalog/website/{slug} answers JSON to the drawer and renders
             * the whole site as a page when visited directly — it is what makes
             * the drawer deep-linkable, so it is where a palette hit goes.
             */
            'href' => "/catalog/website/{$site->slug}",
        ])->all(), $term, '/catalog?q='.urlencode((string) $term));
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>|null
     */
    private function projects(User $user, array $ids, ?string $term, string $label = 'Projects'): ?array
    {
        if ($ids === []) {
            return null;
        }

        $rows = Project::query()
            ->where('user_id', $user->id)
            ->withCount('posts')
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'website_url', 'color']);

        return $this->group($label, 'projects', $this->order($rows, $ids)->map(fn (Project $project): array => [
            'id' => "project-{$project->id}",
            'title' => $project->name,
            'subtitle' => $project->website_url,
            'meta' => (int) $project->getAttribute('posts_count'),
            'color' => $project->color,
            'href' => "/projects/{$project->id}",
        ])->all(), $term, '/projects');
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>|null
     */
    private function posts(User $user, array $ids, ?string $term): ?array
    {
        if ($ids === []) {
            return null;
        }

        $rows = Post::query()
            ->where('user_id', $user->id)
            ->with('website:id,domain')
            ->whereIn('id', $ids)
            ->get(['id', 'anchor_text', 'website_id', 'status']);

        return $this->group('Posts', 'posts', $this->order($rows, $ids)->map(fn (Post $post): array => [
            'id' => "post-{$post->id}",
            'title' => $post->website?->domain ?? 'Post',
            'subtitle' => $post->anchor_text,
            /*
             * badgeKey(), not the raw status.
             *
             * The badge vocabulary is eight colours and the lifecycle is nine
             * states: Completed reads in Posted's green and Cancelled in
             * Rejected's red. Sending the raw value hands the Badge a key it
             * does not have, and it destructures the lookup — so one completed
             * post in the results took the whole palette down.
             */
            'status' => $post->status->badgeKey(),
            'statusLabel' => $post->status->label(),
            // Opens the drawer over the post manager — the row's own deep link.
            'href' => "/posts?post={$post->id}",
        ])->all(), $term, '/posts?q='.urlencode((string) $term));
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>|null
     */
    private function conversations(User $user, array $ids, ?string $term): ?array
    {
        if ($ids === []) {
            return null;
        }

        $rows = Conversation::query()
            ->where('user_id', $user->id)
            ->with(['website:id,domain', 'latestMessage'])
            ->whereIn('id', $ids)
            ->get(['id', 'subject', 'website_id']);

        return $this->group('Conversations', 'conversations', $this->order($rows, $ids)->map(fn (Conversation $thread): array => [
            'id' => "conversation-{$thread->id}",
            'title' => $thread->website?->domain ?? $thread->subject,
            'subtitle' => $thread->subject,
            'excerpt' => $thread->latestMessage === null
                ? null
                : Str::limit(strip_tags($thread->latestMessage->body), 70),
            'href' => "/conversations?thread={$thread->id}",
        ])->all(), $term, '/conversations');
    }

    /**
     * Puts hydrated rows back into the order the engine returned them.
     *
     * `whereIn` returns rows in whatever order the database finds convenient,
     * which throws away the relevance ranking that is the entire reason for
     * asking a search engine rather than the table.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $rows
     * @param  list<int>  $ids
     * @return Collection<int, TModel>
     */
    private function order($rows, array $ids)
    {
        $position = array_flip($ids);

        return $rows
            ->sortBy(fn ($row): int => $position[$row->getKey()] ?? PHP_INT_MAX)
            ->take(self::PER_GROUP)
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function group(string $label, string $kind, array $items, ?string $term, string $seeAll): ?array
    {
        if ($items === []) {
            return null;
        }

        return [
            'key' => $kind,
            'label' => $label,
            'items' => $items,
            // Only offered on a real search. "See all" under a list of things
            // you looked at yesterday has nowhere honest to point.
            'seeAll' => $term === null ? null : $seeAll,
        ];
    }
}
