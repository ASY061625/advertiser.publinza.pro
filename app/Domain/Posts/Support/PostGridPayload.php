<?php

declare(strict_types=1);

namespace App\Domain\Posts\Support;

use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Enums\PostTab;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Models\User;

/**
 * The shapes the posts grid is sent, wherever it is rendered.
 *
 * /posts and a project's Post management tab are the same component with the
 * same table, drawer, filters and bulk actions. They have to be fed identically
 * or the two would drift into different grids that merely look alike — so the
 * row and the option lists are defined once, here, and both controllers call
 * this rather than each writing their own.
 */
final class PostGridPayload
{
    /**
     * The grid row. Deliberately flat and deliberately small: this is sent 100
     * times per page, so it carries what the columns render and nothing else.
     *
     * @return array<string, mixed>
     */
    public static function row(Post $post): array
    {
        return [
            'id' => $post->id,
            'domain' => $post->website?->domain ?? '',
            // Null until Publinza stores its own site marks. Pointing this at a
            // third-party favicon service would ship every domain the
            // advertiser is buying on to that service on each page load, which
            // is not a trade this product makes. The card falls back to a glyph
            // of the same size, so nothing shifts if one ever lands.
            'favicon' => null,
            'dr' => $post->website?->latestMetric?->ahrefs_dr,
            'traffic' => $post->website?->latestMetric?->monthly_traffic,
            'project' => $post->project?->name,
            'projectId' => $post->project_id,
            'folder' => $post->folder?->name,
            'anchorText' => $post->anchor_text,
            'targetUrl' => $post->target_url,
            'status' => $post->status->value,
            'statusLabel' => $post->status->label(),
            'badge' => $post->status->badgeKey(),
            'canCancel' => $post->status->isPrePosted(),
            'priceCents' => $post->price_cents,
            'createdAt' => $post->created_at?->toIso8601String(),
            'publishedAt' => $post->published_at?->toIso8601String(),
            'deadlineAt' => $post->deadline_at?->toIso8601String(),
            'publishedUrl' => $post->published_url,
            // Unread by the advertiser: their own messages never count. Loaded
            // as an exists by ListPosts; false when the grid did not ask.
            'hasUnread' => (bool) $post->getAttribute('has_unread'),
        ];
    }

    /**
     * The filter bar's option lists.
     *
     * Projects and folders are the advertiser's own. Categories, countries and
     * languages are narrowed to those that actually appear on their posts —
     * a filter that can only ever return nothing is noise.
     *
     * Scoped to one project, the folder list narrows with it, because the only
     * folders worth offering there are the ones the grid can actually show.
     *
     * @return array<string, mixed>
     */
    public static function options(User $user, ?Project $scope = null): array
    {
        $websiteIds = Post::query()
            ->where('user_id', $user->id)
            ->when($scope !== null, fn ($q) => $q->where('project_id', $scope->id))
            ->select('website_id');

        $used = fn (string $column, string $model): array => $model::query()
            ->whereIn('id', fn ($q) => $q->select($column)->from('websites')->whereIn('id', $websiteIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        return [
            'projects' => Project::query()
                ->where('user_id', $user->id)
                // Still every project when scoped: "duplicate to another
                // project" and the bulk move need somewhere to move things to,
                // and both are about leaving this project.
                ->with('folders:id,project_id,name')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'statuses' => array_map(static fn (PostStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'badge' => $s->badgeKey(),
            ], PostStatus::cases()),
            'tabs' => array_map(static fn (PostTab $t): array => [
                'value' => $t->value,
                'label' => $t->label(),
                'badge' => $t->badgeKey(),
            ], PostTab::cases()),
            'categories' => $used('category_id', WebsiteCategory::class),
            'countries' => $used('country_id', Country::class),
            'languages' => $used('primary_language_id', Language::class),
        ];
    }
}
