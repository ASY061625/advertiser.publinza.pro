<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Catalog\Actions\GetCatalogPreview;
use App\Domain\Catalog\Actions\GetCatalogRanges;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Http\Controllers\Controller;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * A public slice of the catalog. Deliberately capped and unpaginated: the full
 * catalog with its filters lives behind the account, and this exists to show
 * the product is real rather than to replace it.
 */
class CatalogPreviewController extends Controller
{
    private const PREVIEW_LIMIT = 24;

    public function __invoke(Request $request, GetCatalogPreview $preview, GetCatalogRanges $ranges): View
    {
        $category = $request->string('category')->trim()->value() ?: null;
        $query = $request->string('q')->trim()->limit(80, '')->value() ?: null;

        return view('marketing.pages.catalog', [
            'rows' => $preview->handle(self::PREVIEW_LIMIT, $category, $query),
            'ranges' => $ranges->handle()->toArray(),
            'categories' => $this->categories(),
            'activeCategory' => $category,
            'query' => $query ?? '',
            'networkSize' => Cache::remember(
                'marketing:network-size',
                now()->addHour(),
                fn (): int => Website::query()->where('is_active', true)->count(),
            ),
            'schema' => [
                Seo::organization(),
                Seo::website(),
                Seo::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Catalog', 'url' => route('catalog')],
                ]),
            ],
        ]);
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    private function categories(): array
    {
        /** @var list<array{slug: string, name: string}> $categories */
        $categories = Cache::remember('marketing:categories:all', now()->addHour(), fn (): array => WebsiteCategory::query()
            ->whereHas('websites', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->get(['slug', 'name'])
            ->map(fn ($c): array => ['slug' => $c->slug, 'name' => $c->name])
            ->all());

        return $categories;
    }
}
