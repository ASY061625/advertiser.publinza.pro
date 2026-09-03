<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Projects\Support\ProjectStats;
use Illuminate\Support\Facades\DB;

/**
 * Everything the General tab shows for one project.
 *
 * The post mix and the money come from ProjectStats, the same aggregate the
 * projects list uses, so a project cannot report 34 posts in the list and 31
 * on its own page.
 *
 * Folder counts are one grouped query each rather than a count per folder: a
 * project with twenty folders should not cost forty queries to render.
 */
final class GetProjectOverview
{
    /** How much of a folder's brief the row shows. */
    private const EXCERPT = 60;

    /**
     * @return array<string, mixed>
     */
    public function handle(Project $project): array
    {
        $stats = ProjectStats::forProject($project->id);

        return [
            'stats' => [
                'posts' => ProjectStats::mix($stats),
                // Total completed spend on this project, for all time — the
                // list's figure is windowed to a month, this one is not, and
                // the labels say which is which.
                'spentCents' => $stats['completedCents'],
                'frozenCents' => $stats['frozenCents'],
                'averageCents' => ProjectStats::averageCents($stats),
            ],
            'folders' => $this->folders($project),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function folders(Project $project): array
    {
        $folders = ProjectFolder::query()
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'publisher_task', 'sort_order']);

        if ($folders->isEmpty()) {
            return [];
        }

        $ids = $folders->pluck('id')->all();

        $landingPages = DB::table('landing_pages')
            ->whereIn('folder_id', $ids)
            ->groupBy('folder_id')
            ->pluck(DB::raw('count(*)'), 'folder_id');

        $posts = Post::query()
            ->whereIn('folder_id', $ids)
            ->groupBy('folder_id')
            ->pluck(DB::raw('count(*)'), 'folder_id');

        // A folder can only be deleted once nothing points at it. Landing pages
        // move with the folder, but a post referencing it is work in the world
        // and cannot be quietly detached.
        $blocking = Post::query()
            ->whereIn('folder_id', $ids)
            ->whereNotIn('status', $this->terminalValues())
            ->groupBy('folder_id')
            ->pluck(DB::raw('count(*)'), 'folder_id');

        return $folders->map(fn (ProjectFolder $folder): array => [
            'id' => $folder->id,
            'name' => $folder->name,
            'landingPages' => (int) ($landingPages[$folder->id] ?? 0),
            'posts' => (int) ($posts[$folder->id] ?? 0),
            'activePosts' => (int) ($blocking[$folder->id] ?? 0),
            // Truncated server-side so the row is the same width whatever the
            // brief contains, and so a 3,000-character task is not sent to
            // render sixty characters of it.
            'taskExcerpt' => $this->excerpt($folder->publisher_task),
        ])->all();
    }

    private function excerpt(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        // The brief is stored as sanitised HTML; the row wants a plain line.
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= self::EXCERPT) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::EXCERPT);
        $space = mb_strrpos($cut, ' ');

        // Back off to the last word boundary, unless that would throw away most
        // of the line — "Mention the free trial and link the pricing page from
        // the…" reads; cutting mid-word does not.
        if ($space !== false && $space > self::EXCERPT / 2) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, ' ,.;:-').'…';
    }

    /**
     * @return list<string>
     */
    private function terminalValues(): array
    {
        return array_values(array_map(
            static fn (PostStatus $status): string => $status->value,
            array_filter(PostStatus::cases(), static fn (PostStatus $s): bool => $s->isTerminal()),
        ));
    }
}
