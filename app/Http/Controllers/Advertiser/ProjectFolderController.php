<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Projects\Actions\DeleteFolder;
use App\Domain\Projects\Actions\GetFolderEditor;
use App\Domain\Projects\Actions\SaveFolder;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Projects\Support\ProjectAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\FolderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function create(Project $project, GetFolderEditor $editor): Response
    {
        $this->authorize('update', $project);

        return inertia('Projects/Folders/Edit', $editor->handle($project, null));
    }

    public function store(FolderRequest $request, Project $project, SaveFolder $save): RedirectResponse
    {
        $this->authorize('update', $project);

        $payload = $this->payload($request);
        $folder = $save->handle($project, null, $payload);

        ProjectAudit::folderEvent($request->user(), $project, 'folder.added', [
            'folder' => $folder->name,
            'landing_pages' => count($payload['landing_pages']),
        ], $request->ip());

        return $this->backToGeneral($project, $folder->id);
    }

    public function edit(Project $project, ProjectFolder $folder, GetFolderEditor $editor): Response
    {
        $this->authorize('update', $project);
        $this->belongsTo($project, $folder);

        return inertia('Projects/Folders/Edit', $editor->handle($project, $folder));
    }

    public function update(
        FolderRequest $request,
        Project $project,
        ProjectFolder $folder,
        SaveFolder $save,
    ): RedirectResponse {
        $this->authorize('update', $project);
        $this->belongsTo($project, $folder);

        $before = [
            'name' => $folder->name,
            'landing_pages' => $folder->landingPages()->count(),
        ];

        try {
            $save->handle($project, $folder, $this->payload($request));
        } catch (RuntimeException $e) {
            // A landing page that posts already point at. The editor disables
            // Remove on those rows; this is the same refusal for anything that
            // got past it, and it names the page rather than the rule.
            return back()->withInput()->withErrors(['landing_pages' => $e->getMessage()]);
        }

        $folder->refresh();

        ProjectAudit::folderEvent($request->user(), $project, 'folder.edited', [
            'folder' => $folder->name,
            'renamed_from' => $before['name'] === $folder->name ? null : $before['name'],
            'landing_pages_before' => $before['landing_pages'],
            'landing_pages_after' => $folder->landingPages()->count(),
        ], $request->ip());

        return $this->backToGeneral($project, $folder->id);
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectFolder $folder,
        DeleteFolder $delete,
    ): RedirectResponse {
        $this->authorize('update', $project);
        $this->belongsTo($project, $folder);

        $name = $folder->name;

        try {
            $delete->handle($folder);

            ProjectAudit::folderEvent($request->user(), $project, 'folder.removed', [
                'folder' => $name,
            ], $request->ip());
        } catch (RuntimeException $e) {
            // The reason, not just a refusal. The list already knows a folder
            // is blocked and disables its Delete; this is the server saying the
            // same thing for anyone who got past the button.
            return back()->with('error', $e->getMessage());
        }

        return to_route('projects.show', $project)->with('success', "“{$name}” deleted.");
    }

    /**
     * Back to the General tab, saying which folder just changed so the row can
     * be pointed at for a moment. The flash is about this arrival, not about the
     * folder, so it is not stored anywhere.
     */
    private function backToGeneral(Project $project, int $folderId): RedirectResponse
    {
        return to_route('projects.show', $project)
            ->with('success', 'Saved')
            ->with('folder_saved', $folderId);
    }

    /**
     * @return array{name: string, publisher_task: ?string, landing_pages: list<array<string, mixed>>}
     */
    private function payload(FolderRequest $request): array
    {
        /** @var list<array<string, mixed>> $pages */
        $pages = $request->validated('landing_pages') ?? [];

        return [
            'name' => (string) $request->validated('name'),
            'publisher_task' => $request->validated('publisher_task'),
            'landing_pages' => array_values($pages),
        ];
    }

    /**
     * A folder id from another project is not this project's business, and
     * saying "forbidden" would confirm the id exists. Say it does not.
     */
    private function belongsTo(Project $project, ProjectFolder $folder): void
    {
        abort_if($folder->project_id !== $project->id, 404);
    }
}
