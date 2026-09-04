<?php

declare(strict_types=1);

namespace App\Domain\Trading\Support;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Catalog\Support\CompatibilityWarnings;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use Illuminate\Support\Collection;

/**
 * The whole cart, in the shape the page reads.
 *
 * Grouped by project because that is the unit an advertiser actually thinks in:
 * "am I done buying for the spring launch" is a question about a group, and a
 * flat list of nineteen lines from four campaigns cannot answer it.
 *
 * Every per-line question that needs the database — has this project already
 * placed here, what does this site cost now — is resolved in bulk. A cart is
 * small, but it is rendered on every page load through the header preview and
 * the N+1 would be paid every time.
 */
final class CartPresenter
{
    public function __construct(private readonly CartPricer $pricer) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(?Cart $cart): array
    {
        /** @var Collection<int, CartItem> $items */
        $items = $cart?->items ?? collect();

        if ($items->isEmpty()) {
            return ['groups' => [], 'itemCount' => 0];
        }

        $recent = $this->recentPlacements($items);
        $targeting = $this->targetingByProject($items);

        $groups = $items
            // Ungrouped lines sort last under a null key rather than being
            // dropped: a line with no project is still money in the cart, and a
            // buyer who cannot see it cannot fix it.
            ->groupBy(static fn (CartItem $item): int => $item->project_id ?? 0)
            // Sorted before mapping, on the models rather than on the arrays
            // they become: projects sort by name so the groups do not shuffle
            // between loads on whatever order the database felt like, and
            // projectless lines go last under a codepoint no name can precede.
            ->sortBy(fn (Collection $lines): string => $this->groupName($lines))
            ->map(fn (Collection $lines): array => $this->group($lines, $recent, $targeting))
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'itemCount' => $items->count(),
        ];
    }

    /**
     * The name a group of lines sorts under.
     *
     * Projectless lines go last, under a codepoint no project name can
     * precede. Sorting by name at all is so the groups do not shuffle between
     * page loads on whatever order the database felt like returning.
     *
     * @param  Collection<int, CartItem>  $lines
     */
    private function groupName(Collection $lines): string
    {
        return $lines->first()?->project?->name ?? "\u{FFFF}";
    }

    /**
     * @param  Collection<int, CartItem>  $lines
     * @param  array<int, array{websiteId: int, projectId: int, days: int}>  $recent
     * @param  array<int, array{languages: array<int, string>, topics: array<string, string>}>  $targeting
     * @return array<string, mixed>
     */
    private function group(Collection $lines, array $recent, array $targeting): array
    {
        $project = $lines->first()?->project;

        return [
            // The key the front end collapses on. Zero rather than null so it
            // survives the round trip through a query string unchanged.
            'id' => $project?->id ?? 0,
            'project' => $project === null ? null : [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
            ],
            'itemCount' => $lines->count(),
            'subtotalCents' => $this->pricer->sum($lines)->cents,
            'items' => $lines
                ->map(fn (CartItem $item): array => $this->item($item, $recent, $targeting))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array{websiteId: int, projectId: int, days: int}>  $recent
     * @param  array<int, array{languages: array<int, string>, topics: array<string, string>}>  $targeting
     * @return array<string, mixed>
     */
    private function item(CartItem $item, array $recent, array $targeting): array
    {
        $website = $item->website;
        $drift = $this->pricer->drift($item);

        return [
            'id' => $item->id,
            'website' => [
                'id' => $website?->id,
                'slug' => $website?->slug,
                'domain' => $website?->domain ?? '',
                'publicationHours' => $website?->publication_period_hours ?? 0,
                'minWords' => $website?->min_words ?? 0,
            ],
            'projectId' => $item->project_id,
            'folder' => $item->folder === null ? null : [
                'id' => $item->folder->id,
                'name' => $item->folder->name,
            ],
            'serviceType' => $item->service_type->value,
            'serviceLabel' => $item->service_type->label(),
            'contentMode' => $item->content_mode->value,
            'contentLabel' => $item->content_mode->label(),
            'anchorText' => $item->anchor_text,
            'targetUrl' => $item->target_url,
            'express' => $item->express,
            'baseCents' => $this->pricer->base($item)->cents,
            'writingFeeCents' => $this->pricer->writingFee($item)->cents,
            'expressFeeCents' => $this->pricer->expressFee($item)->cents,
            // What the publisher offers, as opposed to what this line is being
            // charged. The editor needs both: a line with express off still has
            // to be able to show what turning it on would cost.
            'fees' => [
                'writingCents' => $this->pricer->price($item)?->writing_fee_cents ?? 0,
                'expressCents' => $this->pricer->price($item)?->express_fee_cents ?? 0,
            ],
            'totalCents' => $this->pricer->total($item)->cents,
            // Present only when the publisher's price moved since this line was
            // added. The line is charged at the new price; this says so.
            'quotedCents' => $drift?->cents,
            'article' => $item->hasArticle() ? [
                'title' => $item->article_title,
                'wordCount' => $item->article_word_count,
                'fileName' => $item->article_file_path === null
                    ? null
                    : basename($item->article_file_path),
            ] : null,
            'warnings' => $this->warnings($item, $recent, $targeting),
        ];
    }

    /**
     * @param  array<int, array{websiteId: int, projectId: int, days: int}>  $recent
     * @param  array<int, array{languages: array<int, string>, topics: array<string, string>}>  $targeting
     * @return list<array{kind: string, message: string}>
     */
    private function warnings(CartItem $item, array $recent, array $targeting): array
    {
        $warnings = [];
        $website = $item->website;

        if ($website === null) {
            return $warnings;
        }

        if ($item->project_id !== null && isset($targeting[$item->project_id])) {
            $warnings = CompatibilityWarnings::for($website, $targeting[$item->project_id]);
        }

        // The publisher withdrew the service after the line was added. Worth
        // saying loudly: this line would otherwise check out at zero.
        if ($this->pricer->price($item) === null) {
            $warnings[] = [
                'kind' => 'unavailable',
                'message' => sprintf(
                    '%s no longer offers %s. Remove this line before checking out.',
                    $website->domain,
                    strtolower($item->service_type->label()),
                ),
            ];
        }

        $key = $this->placementKey($item->website_id, $item->project_id);

        if (isset($recent[$key])) {
            $days = $recent[$key]['days'];

            $warnings[] = [
                'kind' => 'duplicate',
                'message' => sprintf(
                    'You already placed a post here %s. A second link from the same site is worth much less than the first.',
                    $days === 0 ? 'today' : ($days === 1 ? 'yesterday' : "{$days} days ago"),
                ),
            ];
        }

        // Dismissal is per kind, so hiding "you placed here recently" does not
        // also hide a topic mismatch the buyer has not seen yet.
        return array_values(array_filter(
            $warnings,
            static fn (array $warning): bool => ! $item->hasDismissed($warning['kind']),
        ));
    }

    /**
     * The most recent post per (site, project) pair in the cart, within 90 days.
     *
     * One query for the whole cart. The window is 90 days because outside it
     * the warning stops being useful — a repeat placement a year later is an
     * ordinary thing to want, and a strip that fires on every line is a strip
     * nobody reads.
     *
     * @param  Collection<int, CartItem>  $items
     * @return array<int, array{websiteId: int, projectId: int, days: int}>
     */
    private function recentPlacements(Collection $items): array
    {
        $pairs = $items->filter(static fn (CartItem $item): bool => $item->project_id !== null);

        if ($pairs->isEmpty()) {
            return [];
        }

        $rows = Post::query()
            ->whereIn('website_id', $pairs->pluck('website_id')->unique()->all())
            ->whereIn('project_id', $pairs->pluck('project_id')->unique()->all())
            ->where('created_at', '>=', now()->subDays(90))
            ->get(['website_id', 'project_id', 'created_at']);

        $recent = [];

        foreach ($rows as $row) {
            $key = $this->placementKey($row->website_id, $row->project_id);
            $days = (int) $row->created_at->startOfDay()->diffInDays(now()->startOfDay());

            // The most recent one wins: "6 days ago" is more useful than the
            // oldest of four placements, and it is the one a buyer remembers.
            if (! isset($recent[$key]) || $days < $recent[$key]['days']) {
                $recent[$key] = [
                    'websiteId' => (int) $row->website_id,
                    'projectId' => (int) $row->project_id,
                    'days' => $days,
                ];
            }
        }

        return $recent;
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @return array<int, array{languages: array<int, string>, topics: array<string, string>}>
     */
    private function targetingByProject(Collection $items): array
    {
        $ids = $items->pluck('project_id')->filter()->unique()->all();

        if ($ids === []) {
            return [];
        }

        return Project::query()
            ->with(['languages:id,name', 'sensitiveTopics:id,name,slug'])
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(static fn (Project $project): array => [
                $project->id => CompatibilityWarnings::targeting($project),
            ])
            ->all();
    }

    /**
     * Two integers into one array key. Cantor-style rather than a string so the
     * lookup stays an integer-keyed array.
     */
    private function placementKey(?int $websiteId, ?int $projectId): int
    {
        return ((int) $websiteId * 1_000_000) + (int) $projectId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyTotals(): array
    {
        return [
            'subtotalCents' => 0,
            'writingFeesCents' => 0,
            'expressFeesCents' => 0,
            'discountCents' => 0,
            'totalCents' => 0,
        ];
    }

    /**
     * The summary card's five lines.
     *
     * The subtotal is base prices only, so the two fee lines beneath it are
     * additions rather than a breakdown of a number that already contains them
     * — which is the difference between a summary a buyer can check and one
     * they have to trust.
     *
     * @return array<string, mixed>
     */
    public function totals(?Cart $cart, Money $discount): array
    {
        /** @var Collection<int, CartItem> $items */
        $items = $cart?->items ?? collect();

        if ($items->isEmpty()) {
            return self::emptyTotals();
        }

        $subtotal = Money::zero();
        $writing = Money::zero();
        $express = Money::zero();

        foreach ($items as $item) {
            $subtotal = $subtotal->plus($this->pricer->base($item));
            $writing = $writing->plus($this->pricer->writingFee($item));
            $express = $express->plus($this->pricer->expressFee($item));
        }

        $gross = $subtotal->plus($writing)->plus($express);

        return [
            'subtotalCents' => $subtotal->cents,
            'writingFeesCents' => $writing->cents,
            'expressFeesCents' => $express->cents,
            'discountCents' => min($discount->cents, $gross->cents),
            'totalCents' => max(0, $gross->cents - $discount->cents),
        ];
    }

    /** What the cart needs eager-loaded before any of the above is called. */
    public const RELATIONS = [
        'items.website.prices',
        'items.website.primaryLanguage',
        'items.project',
        'items.folder',
        'promoCode',
    ];
}
