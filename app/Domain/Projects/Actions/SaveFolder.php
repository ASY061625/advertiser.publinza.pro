<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes a folder and the landing pages inside it, in one transaction.
 *
 * The submitted list is the whole truth for this folder: rows carrying an id are
 * updated in place, rows without one are created, and rows the browser no longer
 * sends are deleted. Order comes from the array, so a drag is persisted by the
 * same save as a rename.
 *
 * One thing the form cannot do is drop a landing page that posts already point
 * at. The editor disables Remove on those rows; this refuses them again, because
 * a rule only enforced in the browser is not a rule — a stale tab, a replayed
 * request or a second window would all get past it.
 */
final class SaveFolder
{
    /**
     * @param  array{name: string, publisher_task: ?string, landing_pages: list<array<string, mixed>>}  $data
     */
    public function handle(Project $project, ?ProjectFolder $folder, array $data): ProjectFolder
    {
        return DB::transaction(function () use ($project, $folder, $data): ProjectFolder {
            $folder = $this->upsertFolder($project, $folder, $data);

            $this->syncLandingPages($project, $folder, $data['landing_pages']);

            return $folder;
        });
    }

    /**
     * @param  array{name: string, publisher_task: ?string}  $data
     */
    private function upsertFolder(Project $project, ?ProjectFolder $folder, array $data): ProjectFolder
    {
        $attributes = [
            'name' => trim($data['name']),
            'publisher_task' => $this->task($data['publisher_task']),
        ];

        if ($folder !== null) {
            $folder->update($attributes);

            return $folder;
        }

        return $project->folders()->create($attributes + [
            // Appended, so a new folder lands at the bottom rather than
            // displacing an order someone already arranged.
            'sort_order' => (int) $project->folders()->max('sort_order') + 1,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncLandingPages(Project $project, ProjectFolder $folder, array $rows): void
    {
        $existing = LandingPage::query()
            ->where('folder_id', $folder->id)
            ->get(['id', 'anchor_text', 'url'])
            ->keyBy('id');

        $kept = [];

        foreach (array_values($rows) as $position => $row) {
            $anchor = trim((string) ($row['anchor_text'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $id = isset($row['id']) ? (int) $row['id'] : null;

            // An id the browser sent that is not in this folder is not an
            // error to explain — it is a row that no longer exists, so it is
            // created fresh rather than silently editing someone else's page.
            $page = $id !== null && $existing->has($id) ? $existing->get($id) : null;

            if ($page === null) {
                $page = new LandingPage(['project_id' => $project->id, 'folder_id' => $folder->id]);
            }

            $page->fill([
                'project_id' => $project->id,
                'folder_id' => $folder->id,
                'anchor_text' => $anchor,
                'url' => $url,
                'sort_order' => $position,
            ])->save();

            $kept[] = $page->id;
        }

        $removed = $existing->keys()->diff($kept);

        if ($removed->isEmpty()) {
            return;
        }

        $usage = GetFolderEditor::usage($project);

        foreach ($removed as $id) {
            $page = $existing->get($id);
            $uses = $usage[GetFolderEditor::pairKey($page->anchor_text, $page->url)] ?? 0;

            if ($uses > 0) {
                throw new RuntimeException(sprintf(
                    '“%s” is used by %d post%s, so it cannot be removed. Placements already point at that page; '
                    .'edit the anchor or the URL if it needs to change.',
                    $page->anchor_text,
                    $uses,
                    $uses === 1 ? '' : 's',
                ));
            }
        }

        LandingPage::query()->whereIn('id', $removed->all())->delete();
    }

    /**
     * The brief is publisher-facing rich text typed by the advertiser. It goes
     * through the allowlist once, on write — never on the way out.
     */
    private function task(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $clean = HtmlSanitizer::clean($raw);

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }
}
