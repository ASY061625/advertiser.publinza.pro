<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Models\Website;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The command palette's backing search.
 *
 * Three scopes in one response so the palette renders grouped results from a
 * single request. Projects and posts are scoped to the signed-in advertiser;
 * the catalog is shared but only its active sites are searchable.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->limit(60, '')->value();

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $userId = $request->user()->id;
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $groups = [];

        $projects = Project::query()
            ->where('user_id', $userId)
            ->where('name', 'like', $like)
            ->take(self::PER_GROUP)
            ->get(['id', 'name']);

        if ($projects->isNotEmpty()) {
            $groups[] = [
                'label' => 'Projects',
                'items' => $projects->map(fn (Project $p): array => [
                    'id' => "project-{$p->id}",
                    'title' => $p->name,
                    'subtitle' => null,
                    'href' => "/projects/{$p->id}",
                ])->all(),
            ];
        }

        $websites = Website::query()
            ->active()
            ->with('category:id,name')
            ->where(fn ($q) => $q->where('domain', 'like', $like)->orWhere('title', 'like', $like))
            ->take(self::PER_GROUP)
            ->get(['id', 'domain', 'slug', 'category_id']);

        if ($websites->isNotEmpty()) {
            $groups[] = [
                'label' => 'Websites',
                'items' => $websites->map(fn (Website $w): array => [
                    'id' => "website-{$w->id}",
                    'title' => $w->domain,
                    'subtitle' => $w->category?->name,
                    'href' => "/catalog/{$w->slug}",
                ])->all(),
            ];
        }

        $posts = Post::query()
            ->where('user_id', $userId)
            ->with('website:id,domain')
            ->where(fn ($q) => $q->where('anchor_text', 'like', $like)
                ->orWhereHas('website', fn ($w) => $w->where('domain', 'like', $like)))
            ->latest()
            ->take(self::PER_GROUP)
            ->get(['id', 'anchor_text', 'website_id', 'status']);

        if ($posts->isNotEmpty()) {
            $groups[] = [
                'label' => 'Posts',
                'items' => $posts->map(fn (Post $p): array => [
                    'id' => "post-{$p->id}",
                    'title' => $p->website?->domain ?? 'Post',
                    'subtitle' => Str::limit((string) $p->anchor_text, 40).' · '.$p->status->label(),
                    'href' => "/posts/{$p->id}",
                ])->all(),
            ];
        }

        return response()->json(['groups' => $groups]);
    }
}
