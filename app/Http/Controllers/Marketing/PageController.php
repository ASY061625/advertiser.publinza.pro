<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Trading\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Support\Format;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    public function howItWorks(): View
    {
        return view('marketing.pages.how-it-works', [
            'schema' => [Seo::organization(), Seo::website()],
        ]);
    }

    public function pricing(): View
    {
        return view('marketing.pages.pricing', [
            // Bands come from the live catalog, so the page cannot quote a
            // price the app no longer charges.
            'priceBands' => $this->priceBands(),
            'schema' => [Seo::organization(), Seo::website()],
        ]);
    }

    public function about(): View
    {
        return view('marketing.pages.about', [
            'networkSize' => Cache::remember(
                'marketing:network-size',
                now()->addHour(),
                fn (): int => Website::query()->where('is_active', true)->count(),
            ),
            'categoryCount' => Cache::remember(
                'marketing:category-count',
                now()->addHour(),
                fn (): int => WebsiteCategory::query()->whereHas('websites')->count(),
            ),
            'languageCount' => Cache::remember(
                'marketing:language-count',
                now()->addHour(),
                fn (): int => Language::query()->whereHas('websites')->count(),
            ),
            'schema' => [Seo::organization(), Seo::website()],
        ]);
    }

    public function contact(): View
    {
        return view('marketing.pages.contact', [
            'schema' => [Seo::organization(), Seo::website()],
        ]);
    }

    /**
     * The lowest and highest live price per service type.
     *
     * @return array<string, string>
     */
    private function priceBands(): array
    {
        /** @var array<string, string> $bands */
        $bands = Cache::remember('marketing:price-bands', now()->addHour(), function (): array {
            $out = [];

            foreach ([
                'article' => ServiceType::ArticlePlacement,
                'insertion' => ServiceType::LinkInsertion,
                'homepage' => ServiceType::Homepage,
            ] as $key => $service) {
                $row = WebsitePrice::query()
                    ->where('service_type', $service)
                    ->whereHas('website', fn ($q) => $q->where('is_active', true))
                    ->selectRaw('MIN(price_cents) low, MAX(price_cents) high')
                    ->first();

                $out[$key] = $row?->low === null
                    ? 'On request'
                    : Format::moneyRounded((int) $row->low).' – '.Format::moneyRounded((int) $row->high);
            }

            return $out;
        });

        return $bands;
    }
}
