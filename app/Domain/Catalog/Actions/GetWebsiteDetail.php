<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\PublicationSpeed;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteMetric;
use App\Domain\Catalog\Support\CatalogPresenter;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything the website detail view shows, drawer or page.
 *
 * One payload for both, because they are the same view at two sizes: the drawer
 * is how it is read while scanning a result set, the page is how it is read
 * when someone sends you a link. Two payloads would be two things to keep in
 * step and one of them would eventually show a field the other did not.
 *
 * The metric tiles carry their own provenance — source and fetch date, per
 * tile. A page of nine numbers with one date at the bottom implies they were
 * all measured together, and they are not: DR comes from one vendor's crawl and
 * traffic from another's estimate, weeks apart.
 */
final class GetWebsiteDetail
{
    /** Months of history behind each sparkline. */
    private const HISTORY_MONTHS = 12;

    /** Countries in the traffic breakdown. */
    private const COUNTRIES = 8;

    private const SAMPLES = 3;

    public function __construct(private readonly CatalogPresenter $presenter) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Website $website, User $user, ?Project $project): array
    {
        $website->loadMissing([
            'category',
            'primaryLanguage',
            'country',
            'latestMetric',
            'prices',
            'samplePosts',
        ]);

        // The same row the catalog table renders, so the header and the buy
        // button cannot disagree with the row that opened them.
        $row = $this->presenter->handle([$website], $user->id, $project)[0] ?? [];

        $history = $this->history($website);
        $metric = $website->latestMetric;

        return $row + [
            'homepage' => 'https://'.$website->domain,
            'description' => $website->description,
            'guidelines' => $website->guidelines,
            'metrics' => $this->tiles($website, $metric, $history),
            'trafficByCountry' => $this->trafficByCountry($metric),
            'terms' => $this->terms($website),
            'topics' => $this->topics($website),
            'samplePosts' => $website->samplePosts->take(self::SAMPLES)->map(static fn ($post): array => [
                'title' => $post->title,
                'url' => $post->url,
                'publishedAt' => $post->published_at?->toDateString(),
            ])->values()->all(),
            'services' => $website->prices->map(static fn ($price): array => [
                'type' => $price->service_type->value,
                'label' => $price->service_type->label(),
                'priceCents' => $price->price_cents,
                'writingFeeCents' => $price->writing_fee_cents,
                'expressFeeCents' => $price->express_fee_cents,
            ])->values()->all(),
            'myHistory' => $this->myHistory($website, $user),
        ];
    }

    /**
     * The nine tiles, each with its own value, sparkline and provenance.
     *
     * A tile whose measure has never been recorded is still a tile, with a
     * dash: leaving it out would make the grid a different shape per site and
     * lose the fact that nobody has measured it.
     *
     * @param  Collection<int, WebsiteMetric>  $history
     * @return list<array<string, mixed>>
     */
    private function tiles(Website $website, ?WebsiteMetric $metric, Collection $history): array
    {
        $source = $metric?->source->value;
        $fetchedAt = $metric?->fetched_at?->toIso8601String();

        $tile = static fn (string $key, string $label, ?int $value, string $format, bool $spark = true): array => [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'sparkline' => $spark ? [] : null,
            'source' => $source,
            'fetchedAt' => $fetchedAt,
        ];

        $tiles = [
            $tile('traffic', 'Monthly traffic', $metric?->monthly_traffic, 'compact'),
            $tile('dr', 'Ahrefs DR', $metric?->ahrefs_dr, 'plain'),
            $tile('da', 'Moz DA', $metric?->moz_da, 'plain'),
            $tile('referringDomains', 'Referring domains', $metric?->referring_domains, 'compact'),
            $tile('organicKeywords', 'Organic keywords', $metric?->organic_keywords, 'compact'),
            $tile('spamScore', 'Spam score', $metric?->spam_score, 'plain'),
            $tile('trafficValue', 'Traffic value', $metric?->traffic_value_cents, 'money'),
            $tile('indexedPages', 'Indexed pages', $metric?->indexed_pages, 'compact'),
            // Not measured by a vendor and not a time series: it is arithmetic
            // on a registration date, so it carries neither a source nor a
            // sparkline, and saying so is more useful than implying one.
            [
                'key' => 'domainAge',
                'label' => 'Domain age',
                'value' => $website->domain_registered_at?->diffInMonths(now()),
                'format' => 'age',
                'sparkline' => null,
                'source' => $website->domain_registered_at === null ? null : 'whois',
                'fetchedAt' => null,
            ],
        ];

        // Sparklines are filled from the same history rows for every tile, so
        // twelve months of nine measures is one query rather than nine.
        $series = $this->series($history);

        return array_map(static function (array $item) use ($series): array {
            if ($item['sparkline'] !== null && isset($series[$item['key']])) {
                $item['sparkline'] = $series[$item['key']];
            }

            // An empty sparkline is drawn as nothing rather than as a flat
            // line: one point is not a trend, and a flat line reads as one.
            if (is_array($item['sparkline']) && count($item['sparkline']) < 2) {
                $item['sparkline'] = null;
            }

            return $item;
        }, $tiles);
    }

    /**
     * The last twelve monthly snapshots, one row per month.
     *
     * `website_metrics` accumulates rather than updates, so a busy site can
     * hold several rows in one month. The newest row in each month wins — a
     * sparkline of twelve points is a shape, and one of forty is noise.
     *
     * @return Collection<int, WebsiteMetric>
     */
    private function history(Website $website): Collection
    {
        return WebsiteMetric::query()
            ->where('website_id', $website->id)
            ->where('fetched_at', '>=', now()->subMonths(self::HISTORY_MONTHS)->startOfMonth())
            ->orderBy('fetched_at')
            ->get()
            ->groupBy(static fn (WebsiteMetric $row): string => $row->fetched_at?->format('Y-m') ?? '')
            ->map(static fn (Collection $month): WebsiteMetric => $month->last())
            ->values();
    }

    /**
     * @param  Collection<int, WebsiteMetric>  $history
     * @return array<string, list<int>>
     */
    private function series(Collection $history): array
    {
        return [
            'traffic' => $history->pluck('monthly_traffic')->map(fn (mixed $v): int => (int) $v)->all(),
            'dr' => $history->pluck('ahrefs_dr')->map(fn (mixed $v): int => (int) $v)->all(),
            'da' => $history->pluck('moz_da')->map(fn (mixed $v): int => (int) $v)->all(),
            'referringDomains' => $history->pluck('referring_domains')->map(fn (mixed $v): int => (int) $v)->all(),
            'organicKeywords' => $history->pluck('organic_keywords')->map(fn (mixed $v): int => (int) $v)->all(),
            'spamScore' => $history->pluck('spam_score')->map(fn (mixed $v): int => (int) $v)->all(),
            'trafficValue' => $history->pluck('traffic_value_cents')->map(fn (mixed $v): int => (int) $v)->all(),
            'indexedPages' => $history->pluck('indexed_pages')->map(fn (mixed $v): int => (int) $v)->all(),
        ];
    }

    /**
     * The top countries, as shares of measured traffic.
     *
     * Percentages are of the total the provider reported, not of the top eight,
     * so eight bars that add to 71% say "and 29% elsewhere" rather than
     * implying the world is eight countries.
     *
     * @return list<array<string, mixed>>
     */
    private function trafficByCountry(?WebsiteMetric $metric): array
    {
        $shares = $metric?->traffic_by_country;

        if (! is_array($shares) || $shares === []) {
            return [];
        }

        $clean = [];

        foreach ($shares as $code => $share) {
            if (is_string($code) && is_numeric($share) && (float) $share > 0) {
                $clean[strtoupper($code)] = (float) $share;
            }
        }

        arsort($clean);

        $total = array_sum($clean);

        if ($total <= 0) {
            return [];
        }

        $rows = [];

        foreach (array_slice($clean, 0, self::COUNTRIES, true) as $code => $share) {
            $rows[] = [
                'code' => $code,
                'percent' => round(($share / $total) * 100, 1),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function terms(Website $website): array
    {
        return [
            'publicationLabel' => PublicationSpeed::describe($website->publication_period_hours),
            'linkType' => $website->link_type->value,
            'linksAllowed' => $website->links_allowed,
            'maxLinks' => $website->max_links,
            'minWords' => $website->min_words,
            'marksSponsored' => $website->marks_sponsored,
            // Zero months is "no guarantee", which is a real answer.
            'linkGuaranteeMonths' => $website->link_guarantee_months,
            'acceptsImages' => $website->accepts_images,
            'acceptsEmbeds' => $website->accepts_embeds,
        ];
    }

    /**
     * Every sensitive topic, split into accepted and refused.
     *
     * Both halves, always. A list of only what a publisher accepts leaves the
     * buyer unable to tell "does not accept gambling" from "nobody asked about
     * gambling", and those are opposite answers for anyone shopping on it.
     *
     * @return array{accepted: list<array<string, string>>, refused: list<array<string, string>>}
     */
    private function topics(Website $website): array
    {
        $accepted = [];
        $refused = [];

        foreach (SensitiveTopic::query()->orderBy('name')->get(['name', 'slug']) as $topic) {
            $entry = ['name' => $topic->name, 'slug' => $topic->slug];

            if ($website->acceptsTopic($topic->slug)) {
                $accepted[] = $entry;
            } else {
                $refused[] = $entry;
            }
        }

        return ['accepted' => $accepted, 'refused' => $refused];
    }

    /**
     * What this advertiser has already published here.
     *
     * The strongest signal a returning buyer has, and the reason this action
     * takes a user rather than being cacheable per site: "you placed here in
     * March and it is still live" outranks every metric on the page.
     *
     * @return list<array<string, mixed>>
     */
    private function myHistory(Website $website, User $user): array
    {
        return Post::query()
            ->where('user_id', $user->id)
            ->where('website_id', $website->id)
            ->with('project:id,name')
            ->latest('created_at')
            ->get(['id', 'project_id', 'anchor_text', 'status', 'published_url', 'published_at'])
            ->map(static fn (Post $post): array => [
                'id' => $post->id,
                'project' => $post->project?->name ?? '',
                'anchorText' => $post->anchor_text,
                'status' => $post->status->value,
                'statusLabel' => $post->status->label(),
                'badge' => $post->status->badgeKey(),
                'publishedUrl' => $post->published_url,
                'publishedAt' => $post->published_at?->toDateString(),
            ])
            ->all();
    }
}
