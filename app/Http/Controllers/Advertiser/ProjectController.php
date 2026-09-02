<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Projects\Actions\ArchiveProject;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\ListProjects;
use App\Domain\Projects\DTOs\ProjectData;
use App\Domain\Projects\DTOs\ProjectFilters;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\StoreProjectRequest;
use App\Http\Requests\Advertiser\UpdateProjectRequest;
use App\Support\GridPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use RuntimeException;

class ProjectController extends Controller
{
    public function index(Request $request, ListProjects $list): Response
    {
        $user = $request->user();
        $filters = ProjectFilters::fromRequest($request);
        $rows = $list->handle($user, $filters);

        return inertia('Projects/Index', [
            'projects' => $rows->all(),
            'totals' => $list->totals($rows),
            'filters' => $filters->toQuery(),

            // "You have no projects" and "nothing matches this filter" are
            // different situations with different things to do about them.
            'hasAnyProjects' => Project::query()->where('user_id', $user->id)->exists(),
            'isFiltering' => $filters->isFiltering(),

            'view' => GridPreferences::view($user, 'projects', 'table'),
        ]);
    }

    public function create(Request $request): Response
    {
        return inertia('Projects/Create', ['categories' => $this->categories()]);
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $project = $createProject->handle($request->user(), ProjectData::fromArray($request->validated()));

        return to_route('projects.show', $project)->with('success', 'Project created');
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        return inertia('Projects/Show', [
            'project' => $project->load(['category:id,name', 'folders:id,project_id,name']),
            'categories' => $this->categories(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update(ProjectData::fromArray($request->validated())->toAttributes());

        return back()->with('success', 'Project saved');
    }

    public function archive(Project $project, ArchiveProject $archive): RedirectResponse
    {
        $this->authorize('archive', $project);

        $archive->handle($project);

        return back()->with('success', "“{$project->name}” archived.");
    }

    public function restore(Project $project, ArchiveProject $archive): RedirectResponse
    {
        $this->authorize('restore', $project);

        $archive->restore($project);

        return back()->with('success', "“{$project->name}” restored.");
    }

    public function destroy(Request $request, Project $project, DeleteProject $delete): RedirectResponse
    {
        $this->authorize('delete', $project);

        // The typed name is checked server-side as well as in the dialog: a
        // confirmation only enforced in the browser is not a confirmation.
        $request->validate(['name' => ['required', 'string']]);

        if (trim((string) $request->input('name')) !== $project->name) {
            return back()->with('error', 'That name does not match. Nothing was deleted.');
        }

        $name = $project->name;

        try {
            $delete->handle($project);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('projects.index')->with('success', "“{$name}” deleted.");
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function categories(): array
    {
        return WebsiteCategory::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (WebsiteCategory $c): array => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    /** Table or cards. Persisted per account, so the choice follows the person. */
    public function view(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'view' => ['required', 'string', 'in:'.implode(',', ProjectFilters::VIEWS)],
        ]);

        GridPreferences::setView($request->user(), 'projects', (string) $validated['view']);

        return back();
    }
}
