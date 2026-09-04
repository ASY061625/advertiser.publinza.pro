<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Actions\GetCatalogFacets;
use App\Domain\Catalog\Actions\GetCatalogRanges;
use App\Domain\Catalog\Actions\GetWebsiteDetail;
use App\Domain\Catalog\Actions\SearchCatalog;
use App\Domain\Catalog\Actions\SuggestRelaxations;
use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Enums\PublicationSpeed;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Support\CatalogPresenter;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GridPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The catalog: two modes over one page.
 *
 * Browse mode (`/catalog`) is a showroom — everything is readable, nothing is
 * buyable, and the page says why. Buying mode (`/catalog?project={id}`) is the
 * same page with a project attached: prices become orderable, the project's
 * targeting seeds the filters, and every row is checked against what the
 * project needs.
 *
 * The difference is one query parameter, deliberately. A separate route for
 * buying would mean a shared link stops working the moment the recipient does
 * not own the project, and would give the catalog two page components to keep
 * in step.
 */
class CatalogController extends Controller
{
    public function index(
        Request $request,
        SearchCatalog $search,
        GetCatalogRanges $ranges,
        GetCatalogFacets $facets,
        SuggestRelaxations $relaxations,
        CatalogPresenter $presenter,
    ): Response {
        $user = $request->user();
        $filters = CatalogFilters::fromRequest($request);
        $project = $this->project($user, $filters->projectId);

        // The project's targeting becomes ordinary, removable filters on the
        // first visit only — see CatalogFilters::seededFrom.
        if ($project !== null) {
            $filters = $filters->seededFrom($project);
        }

        $catalogRanges = $ranges->handle();
        $page = $search->handle($filters, $user->id);
        $total = $search->count($filters, $user->id);

        // Only when the answer is empty, and only then: each suggestion costs a
        // count query, and nobody needs to be told how to widen a search that
        // is already returning four hundred sites.
        $suggestions = $total === 0 && $filters->isFiltering()
            ? $relaxations->handle($filters, $user->id)
            : [];

        return inertia('Catalog/Index', [
            'sites' => $presenter->handle($page->items(), $user->id, $project),
            'pagination' => [
                'perPage' => $filters->perPage,
                'nextCursor' => $page->nextCursor()?->encode(),
                'previousCursor' => $page->previousCursor()?->encode(),
                // The contract interface has no hasMorePages(); a next cursor
                // is the same fact and is what the pager actually uses.
                'hasMore' => $page->nextCursor() !== null,
            ],
            'total' => $total,
            'ranges' => $catalogRanges->toArray(),
            'facets' => $facets->handle($filters, $user->id, $catalogRanges->priceMaxCents),
            'options' => $this->options(),
            'filters' => $filters->toQuery(),
            'isFiltering' => $filters->isFiltering(),
            'suggestions' => $suggestions,
            'project' => $project === null ? null : [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
            ],
            'projects' => $this->projects($user),
            'canBuy' => $project !== null,
        ]);
    }

    /**
     * One website, at `/catalog/website/{slug}`.
     *
     * The same address answers two ways, deliberately. Asked for as JSON — by
     * the drawer, from a catalog row — it returns the payload. Visited
     * directly it renders the whole thing as a page.
     *
     * That is what makes the drawer deep-linkable without a second
     * implementation of it: one URL, one payload, and the only difference is
     * whether it arrives inside a panel or inside a page.
     */
    public function show(
        Request $request,
        Website $website,
        GetWebsiteDetail $detail,
        GetCatalogRanges $ranges,
    ): JsonResponse|Response {
        $this->authorize('view', $website);

        $user = $request->user();
        $project = $this->project($user, $request->integer('project') ?: null);

        $payload = $detail->handle($website, $user, $project);

        if ($request->wantsJson()) {
            return response()->json($payload + ['buying' => $this->buyingConfig($project)]);
        }

        return inertia('Catalog/Website', [
            'site' => $payload,
            // The quant-bars and sparklines scale against the whole catalog,
            // which the standalone page has no other way to know.
            'ranges' => $ranges->handle()->toArray(),
            'buying' => $this->buyingConfig($project),
            'project' => $project === null ? null : [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
            ],
            'projects' => $this->projects($user),
        ]);
    }

    /**
     * What the add-to-cart popover needs to configure an order.
     *
     * Empty in browse mode, because there is nothing to configure an order
     * against — which is also what disables the button.
     *
     * @return array<string, mixed>
     */
    private function buyingConfig(?Project $project): array
    {
        if ($project === null) {
            return ['folders' => [], 'landingPages' => []];
        }

        return [
            'folders' => $project->folders()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->all(),
            // The project's saved anchor/URL pairs. The popover offers these
            // first and a manual entry second: a buyer who has already written
            // down what they are promoting should not retype it per order.
            'landingPages' => LandingPage::query()
                ->where('project_id', $project->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'folder_id', 'anchor_text', 'url'])
                ->map(static fn (LandingPage $page): array => [
                    'id' => $page->id,
                    'folderId' => $page->folder_id,
                    'anchorText' => $page->anchor_text,
                    'url' => $page->url,
                ])
                ->all(),
        ];
    }

    /** Remembers table or cards across visits, and across browsers. */
    public function view(Request $request): JsonResponse
    {
        $view = in_array($request->input('view'), CatalogFilters::VIEWS, true)
            ? (string) $request->input('view')
            : 'table';

        GridPreferences::setView($request->user(), 'catalog', $view);

        return response()->json(['view' => $view]);
    }

    /**
     * The reference lists the filter rail is built from.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'speeds' => array_map(static fn (PublicationSpeed $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
            ], PublicationSpeed::cases()),
            'topics' => SensitiveTopic::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->all(),
            'services' => array_map(static fn (ServiceType $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
            ], ServiceType::cases()),
            'sorts' => [
                ['value' => 'relevance', 'label' => 'Relevance'],
                ['value' => 'price_asc', 'label' => 'Price low to high'],
                ['value' => 'price_desc', 'label' => 'Price high to low'],
                ['value' => 'traffic', 'label' => 'Traffic'],
                ['value' => 'dr', 'label' => 'DR'],
                ['value' => 'newest', 'label' => 'Recently added'],
            ],
            'perPage' => CatalogFilters::PER_PAGE,
        ];
    }

    /**
     * The projects the picker offers.
     *
     * Archived ones are left out: they are read-only, so choosing one would put
     * the catalog into buying mode against a project that cannot be bought for.
     *
     * @return list<array<string, mixed>>
     */
    private function projects(User $user): array
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->where('status', ProjectStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'website_url'])
            ->map(static fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
                'websiteUrl' => $project->website_url,
            ])
            ->all();
    }

    /**
     * The project buying mode is for, or null.
     *
     * A project id belonging to somebody else silently drops the page back to
     * browse mode rather than raising. The parameter is the kind of thing that
     * survives in a bookmark long after access to the project has gone, and a
     * 403 on the catalog would be a confusing way to learn that.
     */
    private function project(User $user, ?int $projectId): ?Project
    {
        if ($projectId === null) {
            return null;
        }

        return Project::query()
            ->where('id', $projectId)
            ->where('user_id', $user->id)
            ->where('status', ProjectStatus::Active)
            ->with(['countries:id,name', 'languages:id,name', 'sensitiveTopics:id,name,slug'])
            ->first();
    }
}
