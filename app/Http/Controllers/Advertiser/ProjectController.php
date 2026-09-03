<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Posts\Actions\ListPosts;
use App\Domain\Posts\DTOs\PostFilters;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Support\PostGridPayload;
use App\Domain\Projects\Actions\ArchiveProject;
use App\Domain\Projects\Actions\CreateProjectFromWizard;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\FetchSitePreview;
use App\Domain\Projects\Actions\GetProjectOverview;
use App\Domain\Projects\Actions\ListProjects;
use App\Domain\Projects\DTOs\ProjectData;
use App\Domain\Projects\DTOs\ProjectFilters;
use App\Domain\Projects\DTOs\ProjectWizardData;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Enums\ProjectTab;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectDraft;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\StoreProjectWizardRequest;
use App\Http\Requests\Advertiser\UpdateProjectRequest;
use App\Support\GridPreferences;
use App\Support\PostGridPreferences;
use Illuminate\Http\JsonResponse;
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
        $draft = ProjectDraft::query()->where('user_id', $request->user()->id)->first();

        return inertia('Projects/Create', [
            'categories' => $this->categories(),
            'topics' => SensitiveTopic::query()->orderBy('name')->get(['id', 'name'])->all(),
            'countries' => Country::query()->orderBy('name')->get(['id', 'code', 'name'])->all(),
            'languages' => Language::query()->orderBy('name')->get(['id', 'code', 'name'])->all(),
            'colors' => ProjectWizardData::COLORS,
            // Resumed exactly where it was left, including which step.
            'draft' => $draft === null ? null : ['step' => $draft->step, 'payload' => $draft->payload],
        ]);
    }

    /**
     * Autosave. Deliberately silent: it returns no content and the wizard does
     * not wait for it, because a save that interrupted typing would be worse
     * than losing the draft it is trying to protect.
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:3'],
            'payload' => ['required', 'array'],
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $validated['payload'];

        ProjectDraft::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'step' => (int) $validated['step'],
                'payload' => ProjectWizardData::fromArray($payload)->toDraftPayload($payload),
            ],
        );

        return response()->json(['saved_at' => now()->toIso8601String()]);
    }

    public function discardDraft(Request $request): RedirectResponse
    {
        ProjectDraft::query()->where('user_id', $request->user()->id)->delete();

        return to_route('projects.index');
    }

    /**
     * The site preview behind step 1's URL field.
     *
     * Throttled in the route: it makes the server fetch an address someone
     * typed, so it is the one endpoint here that can be pointed at a third
     * party. See FetchSitePreview for what stops it being pointed inwards.
     */
    public function preview(Request $request, FetchSitePreview $preview): JsonResponse
    {
        $validated = $request->validate(['url' => ['required', 'string', 'max:2048']]);

        return response()->json($preview->handle((string) $validated['url']));
    }

    public function store(StoreProjectWizardRequest $request, CreateProjectFromWizard $create): RedirectResponse
    {
        $project = $create->handle($request->user(), ProjectWizardData::fromArray($request->validated()));

        return to_route('projects.show', $project)
            ->with('success', 'Project created')
            // Read once by the General tab to offer the next step. Flashed
            // rather than stored: it is about this moment, not about the
            // project, and it should not reappear on a later visit.
            ->with('just_created', true);
    }

    /**
     * A project's own page. Six tabs, one layout, `?tab=` deciding which body
     * renders — so a tab is a URL an advertiser can bookmark or send to a
     * colleague rather than a state that exists only in their browser.
     */
    public function show(Request $request, Project $project, GetProjectOverview $overview): Response
    {
        $this->authorize('view', $project);

        $tab = ProjectTab::tryFromRequest($request->input('tab'));

        $project->load('category:id,name');
        $data = $overview->handle($project);

        return inertia('Projects/Show', [
            // Only built for the tab that renders it. Every other tab pays
            // nothing for a grid it is not showing.
            'grid' => $tab === ProjectTab::Posts
                ? $this->postsGrid($request, $project, app(ListPosts::class))
                : null,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'websiteUrl' => $project->website_url,
                'category' => $project->category?->name,
                'categoryId' => $project->category_id,
                'color' => $project->color,
                'status' => $project->status->value,
                'isArchived' => $project->status === ProjectStatus::Archived,
                // Already sanitised on write; rendered as HTML by the settings
                // tab and stripped to a plain line by the folder rows.
                'publisherTask' => $project->publisher_task,
                'createdAt' => $project->created_at?->toIso8601String(),
            ],
            'stats' => $data['stats'],
            'folders' => $data['folders'],
            'tab' => $tab->value,
            'categories' => $this->categories(),
            'justCreated' => (bool) session('just_created', false),
            // Flashed by the folder editor so the row it just wrote can be
            // pointed at for a moment. Read once, then gone.
            'folderSaved' => session('folder_saved'),
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
     * The Post management tab: the /posts grid, locked to this project.
     *
     * Same action, same row shape, same option lists — the component is the
     * one /posts renders, so anything that diverged here would be a second
     * grid pretending to be the first. What differs is the scope, which is
     * applied to the query rather than taken from the request, and the status
     * tab's key: this page already spends `tab` on which tab of the project is
     * open, so the grid's own tab travels as `posts_tab`.
     *
     * @return array<string, mixed>
     */
    private function postsGrid(Request $request, Project $project, ListPosts $list): array
    {
        $user = $request->user();
        $filters = PostFilters::fromRequest($request, 'posts_tab')->forProject($project->id);

        return [
            'posts' => $list->paginate($user, $filters)->through(PostGridPayload::row(...)),
            'tabCounts' => $list->tabCounts($user, $filters),
            'filters' => $filters->toQuery(),

            // "This project has no posts" and "nothing matches these filters"
            // are different situations with different things to do about them.
            'hasAnyPosts' => Post::query()
                ->where('user_id', $user->id)
                ->where('project_id', $project->id)
                ->exists(),
            'isFiltering' => $filters->isFiltering(),

            'options' => PostGridPayload::options($user, $project),
            'columns' => PostGridPreferences::for($user),
            'folders' => $project->folders()->orderBy('sort_order')->orderBy('id')->get(['id', 'name'])->all(),
        ];
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
