<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Actions\GetCatalogFacets;
use App\Domain\Catalog\Actions\GetCatalogRanges;
use App\Domain\Catalog\Actions\SearchCatalog;
use App\Domain\Catalog\Actions\SuggestRelaxations;
use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Enums\PublicationSpeed;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Support\CatalogPresenter;
use App\Domain\Projects\Enums\ProjectStatus;
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
     * One site, as JSON, for the detail drawer.
     *
     * Fetched on open rather than shipped with every row: the guidelines alone
     * are a paragraph per site, and a page of a hundred rows would carry a
     * hundred of them for the one a buyer opens.
     */
    public function show(Request $request, Website $website, CatalogPresenter $presenter): JsonResponse
    {
        $this->authorize('view', $website);

        $user = $request->user();
        $project = $this->project($user, $request->integer('project') ?: null);

        $website->load(['category', 'primaryLanguage', 'country', 'latestMetric', 'prices']);

        $row = $presenter->handle([$website], $user->id, $project)[0] ?? [];

        return response()->json($row + [
            'description' => $website->description,
            'guidelines' => $website->guidelines,
            'sampleUrl' => $website->sample_url,
            'minWords' => $website->min_words,
            'maxLinks' => $website->max_links,
            'linksAllowed' => $website->links_allowed,
            'acceptsTopics' => $website->accepts_sensitive_topics ?? [],
            'services' => $website->prices->map(static fn ($price): array => [
                'type' => $price->service_type->value,
                'label' => $price->service_type->label(),
                'priceCents' => $price->price_cents,
                'writingFeeCents' => $price->writing_fee_cents,
            ])->values()->all(),
        ]);
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
