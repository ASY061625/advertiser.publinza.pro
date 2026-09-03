<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use Illuminate\Support\Facades\DB;

/**
 * Everything the folder editor needs, including what it is not allowed to break.
 *
 * The usage counts are the point. A landing page an advertiser has already
 * bought placements against is not a row in a form any more — deleting it would
 * leave posts pointing at a pair the project no longer claims to promote. So
 * every row carries how many posts use it, the editor disables Remove above
 * zero, and SaveFolder refuses the same thing server-side.
 *
 * Usage is matched on the anchor/URL pair rather than a foreign key because
 * that is how a post records where it points: the columns are denormalised onto
 * the post so a placement keeps meaning something after the landing page it was
 * ordered from is edited.
 */
final class GetFolderEditor
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Project $project, ?ProjectFolder $folder): array
    {
        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'websiteUrl' => $project->website_url,
                'publisherTask' => $project->publisher_task,
            ],
            'folder' => $folder === null ? null : [
                'id' => $folder->id,
                'name' => $folder->name,
                'publisherTask' => $folder->publisher_task,
                // Above zero, Delete folder is refused and says why.
                'activePosts' => $this->activePosts($folder),
                'isOnlyFolder' => ProjectFolder::query()->where('project_id', $project->id)->count() <= 1,
            ],
            'landingPages' => $folder === null ? [] : $this->landingPages($project, $folder),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function landingPages(Project $project, ProjectFolder $folder): array
    {
        $pages = LandingPage::query()
            ->where('folder_id', $folder->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'anchor_text', 'url']);

        if ($pages->isEmpty()) {
            return [];
        }

        $usage = self::usage($project);

        return $pages->map(fn (LandingPage $page): array => [
            'id' => $page->id,
            // The React key. Stable across a drag, and the same shape the
            // wizard's unsaved rows use so one component renders both.
            'key' => 'lp-'.$page->id,
            'anchor_text' => $page->anchor_text,
            'url' => $page->url,
            'usage' => $usage[self::pairKey($page->anchor_text, $page->url)] ?? 0,
        ])->all();
    }

    /**
     * Posts per anchor/URL pair, for the whole project, in one grouped query.
     *
     * @return array<string, int>
     */
    public static function usage(Project $project): array
    {
        $rows = Post::query()
            ->where('project_id', $project->id)
            ->whereNotNull('anchor_text')
            ->whereNotNull('target_url')
            ->groupBy('anchor_text', 'target_url')
            ->get([
                'anchor_text',
                'target_url',
                DB::raw('count(*) as uses'),
            ]);

        $out = [];

        foreach ($rows as $row) {
            $key = self::pairKey((string) $row->getAttribute('anchor_text'), (string) $row->getAttribute('target_url'));
            $out[$key] = (int) $row->getAttribute('uses');
        }

        return $out;
    }

    /** Trimmed and lower-cased, so a stray space does not hide a real usage. */
    public static function pairKey(string $anchor, string $url): string
    {
        return mb_strtolower(trim($anchor))."\0".mb_strtolower(trim($url));
    }

    /** Posts still in flight against this folder's brief. */
    private function activePosts(ProjectFolder $folder): int
    {
        return Post::query()
            ->where('folder_id', $folder->id)
            ->whereIn('status', array_values(array_map(
                static fn (PostStatus $status): string => $status->value,
                array_filter(PostStatus::cases(), static fn (PostStatus $s): bool => ! $s->isTerminal()),
            )))
            ->count();
    }
}
