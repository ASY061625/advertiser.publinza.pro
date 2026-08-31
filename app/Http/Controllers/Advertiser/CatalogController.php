<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Actions\GetCatalogRanges;
use App\Domain\Catalog\Actions\SearchCatalog;
use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(
        Request $request,
        SearchCatalog $searchCatalog,
        GetCatalogRanges $getCatalogRanges,
    ): Response {
        $sites = $searchCatalog->handle(CatalogFilters::fromRequest($request));

        return inertia('Catalog/Index', [
            'sites' => $sites->through(fn (Site $site): array => $this->present($site)),
            // The quant-bars scale against the whole catalog, not this page.
            'ranges' => $getCatalogRanges->handle()->toArray(),
            'filters' => $request->only(['q', 'categories', 'language', 'min_traffic', 'max_price']),
        ]);
    }

    public function show(Request $request, Site $site): Response
    {
        $this->authorize('view', $site);

        return inertia('Catalog/Show', ['site' => $this->present($site)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Site $site): array
    {
        return [
            'id' => $site->id,
            'domain' => $site->domain,
            'language' => $site->language,
            'category' => $site->category,
            'priceMinorUnits' => $site->price_minor_units,
            'traffic' => $site->traffic,
            'domainRating' => $site->domain_rating,
            'domainAuthority' => $site->domain_authority,
            'spamScore' => $site->spam_score,
        ];
    }
}
