<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\PublishProject;
use App\Domain\Projects\DTOs\ProjectData;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\StoreProjectRequest;
use App\Http\Requests\Advertiser\UpdateProjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Projects/Index', [
            'projects' => Project::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return inertia('Projects/Create');
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $project = $createProject->handle($request->user(), ProjectData::fromArray($request->validated()));

        return to_route('projects.show', $project)->with('success', 'Project created');
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        return inertia('Projects/Show', ['project' => $project->load('posts.site')]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return inertia('Projects/Edit', ['project' => $project]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update(ProjectData::fromArray($request->validated())->toAttributes());

        return back()->with('success', 'Project saved');
    }

    public function publish(Project $project, PublishProject $publishProject): RedirectResponse
    {
        $this->authorize('publish', $project);

        $publishProject->handle($project);

        return back()->with('success', 'Published');
    }
}
