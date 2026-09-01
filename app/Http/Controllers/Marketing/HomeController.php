<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Catalog\Actions\GetCatalogPreview;
use App\Domain\Catalog\Actions\GetCatalogRanges;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Catalog\Models\WebsiteMetric;
use App\Http\Controllers\Controller;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /** The eight questions we are actually asked, and the FAQPage schema source. */
    public const FAQ = [
        [
            'question' => 'Do you own every site in the catalog?',
            'answer' => 'Yes. Every domain listed is owned and operated by Publinza Media Ltd. We do not resell placements on sites belonging to anyone else, and if we do not own a site you have in mind we will tell you rather than sourcing it.',
        ],
        [
            'question' => 'How long does publication take?',
            'answer' => 'Each site lists its own window, between 24 hours and 7 days. Because we set the editorial calendar for every site, that window is a commitment rather than an estimate relayed from a publisher.',
        ],
        [
            'question' => 'What happens if the link is removed?',
            'answer' => 'If a link is removed or its page taken down within 12 months of publication, we place it again on a site with equal or better metrics, or refund the placement in full. We can offer that because we control the page.',
        ],
        [
            'question' => 'Are the links dofollow?',
            'answer' => 'Most are, and every site states its link type in the catalog before you buy. Sites marked nofollow are priced accordingly. We never change a link type after publication without telling you.',
        ],
        [
            'question' => 'Do I have to write the article?',
            'answer' => 'No. You can supply the article or have our writers produce it against your brief for a separate writing fee, shown per site. If we write it, you review and approve the draft before anything is published.',
        ],
        [
            'question' => 'When are my funds actually taken?',
            'answer' => 'Paying moves the amount from your available balance into a frozen balance held against that order. We only take it once the link is live and you have verified it. Cancel before publication and it returns to your balance.',
        ],
        [
            'question' => 'Can I buy a link on a specific page?',
            'answer' => 'For link insertions, yes: you can pick an existing article on the site. For article placements we write a new post, so the URL is created at publication and sent to you for verification.',
        ],
        [
            'question' => 'Do you accept gambling, crypto or CBD content?',
            'answer' => 'Some sites do and each one lists which regulated subjects it accepts. Filter by those topics in the catalog. Sites that do not accept a subject will not take the brief, and we will suggest ones that will.',
        ],
    ];

    public function __invoke(GetCatalogPreview $preview, GetCatalogRanges $ranges): View
    {
        $networkSize = Cache::remember(
            'marketing:network-size',
            now()->addHour(),
            fn (): int => Website::query()->where('is_active', true)->count(),
        );

        $rows = $preview->handle(limit: 8);

        return view('marketing.pages.home', [
            'networkSize' => $networkSize,
            // A spread across the range, not the top three: three near-identical
            // full bars would show a visitor nothing, and the whole point of the
            // quant-bar is that shape is readable before digits.
            'heroRows' => $this->spread($rows, 3),
            'previewRows' => $rows,
            'ranges' => $ranges->handle()->toArray(),
            'categories' => $this->categories(),
            'metricsRefreshedAt' => $this->metricsRefreshedAt(),
            'faq' => self::FAQ,
            'schema' => [
                Seo::organization(),
                Seo::website(),
                Seo::faqPage(self::FAQ),
            ],
        ]);
    }

    /**
     * Picks evenly spaced rows from an ordered list, keeping the first and last
     * so the sample spans the full range rather than clustering at one end.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function spread(array $rows, int $take): array
    {
        $count = count($rows);

        if ($count <= $take) {
            return $rows;
        }

        $picked = [];

        for ($i = 0; $i < $take; $i++) {
            $picked[] = $rows[(int) round($i * ($count - 1) / ($take - 1))];
        }

        return $picked;
    }

    /**
     * Only categories that actually have an active site behind them — a chip
     * that filters to nothing is worse than no chip.
     *
     * @return list<array{slug: string, name: string}>
     */
    private function categories(): array
    {
        /** @var list<array{slug: string, name: string}> $categories */
        $categories = Cache::remember('marketing:categories', now()->addHour(), fn (): array => WebsiteCategory::query()
            ->whereHas('websites', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->take(8)
            ->get(['slug', 'name'])
            ->map(fn ($c): array => ['slug' => $c->slug, 'name' => $c->name])
            ->all());

        return $categories;
    }

    private function metricsRefreshedAt(): string
    {
        $latest = Cache::remember(
            'marketing:metrics-refreshed',
            now()->addHour(),
            fn () => WebsiteMetric::query()->max('fetched_at'),
        );

        return $latest === null
            ? 'monthly'
            : Carbon::parse($latest)->format('j F Y');
    }
}
