<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Projects\Actions\DeleteFolder;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\FolderRequest;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use RuntimeException;

/**
 * Folders group a project's landing pages and can override its writer brief.
 *
 * Nested under the project in both the URL and the authorisation: every action
 * here checks the *project*, and a folder that belongs to another project is a
 * 404 rather than a 403, so the route cannot be used to probe which folder ids
 * exist.
 */
class ProjectFolderController extends Controller
{
    public function create(Project $project): Response
    {
        $this->authorize('update', $project);

        return inertia('Projects/Folders/Edit', [
            'project' => $this->projectProps($project),
            'folder' => null,
        ]);
    }

    public function store(FolderRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $folder = $project->folders()->create([
            'name' => (string) $request->validated('name'),
            'publisher_task' => $this->task($request),
            // Appended, so a new folder lands at the bottom of the list rather
            // than displacing the order someone already arranged.
            'sort_order' => (int) $project->folders()->max('sort_order') + 1,
        ]);

        return to_route('projects.show', $project)->with('success', "“{$folder->name}” added.");
    }

    public function edit(Project $project, ProjectFolder $folder): Response
    {
        $this->authorize('update', $project);
        $this->belongsTo($project, $folder);

        return inertia('Projects/Folders/Edit', [
            'project' => $this->projectProps($project),
            'folder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'publisherTask' => $folder->publisher_task,
            ],
        ]);
    }

    public function update(FolderRequest $request, Project $project, ProjectFolder $folder): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->belongsTo($project, $folder);

        $folder->update([
            'name' => (string) $request->validated('name'),
            'publisher_task' => $this->task($request),
        ]);

        return to_route('projects.show', $project)->with('success', "“{$folder->name}” saved.");
    }

    public function destroy(Project $project, ProjectFolder $folder, DeleteFolder $delete): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->belongsTo($project, $folder);

        $name = $folder->name;

        try {
            $delete->handle($folder);
        } catch (RuntimeException $e) {
            // The reason, not just a refusal. The list already knows a folder
            // is blocked and disables its Delete; this is the server saying the
            // same thing for anyone who got past the button.
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "“{$name}” deleted.");
    }

    /**
     * A folder id from another project is not this project's business, and
     * saying "forbidden" would confirm the id exists. Say it does not.
     */
    private function belongsTo(Project $project, ProjectFolder $folder): void
    {
        abort_if($folder->project_id !== $project->id, 404);
    }

    /**
     * The brief is publisher-facing rich text typed by the advertiser. It is
     * put through the allowlist before storage, never on the way out — see
     * HtmlSanitizer for why the sanitising happens once, on write.
     */
    private function task(FolderRequest $request): ?string
    {
        $raw = $request->validated('publisher_task');

        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $clean = HtmlSanitizer::clean((string) $raw);

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function projectProps(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'publisherTask' => $project->publisher_task,
        ];
    }
}
