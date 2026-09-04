<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Support;

use App\Domain\Catalog\Enums\PublicationSpeed;
use App\Domain\Catalog\Models\Website;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Enums\ServiceType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One page of sites, in the shape the table and the cards both read.
 *
 * Five of the things a row shows are about the advertiser rather than the site
 * — favourited, blacklisted, in the cart, already used on this project, and the
 * compatibility flags — and all five are resolved in bulk here. A page of a
 * hundred rows asking those questions one at a time is five hundred queries,
 * and it is the kind of N+1 that only shows up once the catalog is worth
 * scrolling.
 */
final class CatalogPresenter
{
    /**
     * @param  Collection<int, Website>|array<int, Website>  $websites
     * @return list<array<string, mixed>>
     */
    public function handle($websites, ?int $userId, ?Project $project): array
    {
        $websites = collect($websites);
        $ids = $websites->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $favorites = $this->lookup('favorites', $ids, $userId);
        $blacklisted = $this->lookup('blacklists', $ids, $userId);
        $inCart = $this->cartItems($ids, $userId);
        $used = $this->usedInProject($ids, $project);

        // The project's targeting, read once rather than per row.
        $projectLanguages = $project?->languages->pluck('name', 'id')->all() ?? [];
        $projectTopics = $project?->sensitiveTopics->pluck('name', 'slug')->all() ?? [];

        return $websites->map(fn (Website $website): array => $this->row(
            $website,
            isFavorite: isset($favorites[$website->id]),
            isBlacklisted: isset($blacklisted[$website->id]),
            cartItemId: $inCart[$website->id] ?? null,
            isUsed: isset($used[$website->id]),
            projectLanguages: $projectLanguages,
            projectTopics: $projectTopics,
        ))->all();
    }

    /**
     * @param  array<int, string>  $projectLanguages
     * @param  array<string, string>  $projectTopics
     * @return array<string, mixed>
     */
    private function row(
        Website $website,
        bool $isFavorite,
        bool $isBlacklisted,
        ?int $cartItemId,
        bool $isUsed,
        array $projectLanguages,
        array $projectTopics,
    ): array {
        $metric = $website->latestMetric;
        $price = $website->priceFor(ServiceType::ArticlePlacement);
        $hours = $website->publication_period_hours;

        return [
            'id' => $website->id,
            'slug' => $website->slug,
            'domain' => $website->domain,
            'title' => $website->title,
            'category' => $website->category?->name ?? '',
            'language' => [
                'code' => $website->primaryLanguage?->code ?? '',
                'name' => $website->primaryLanguage?->name ?? '',
            ],
            'country' => [
                'code' => $website->country?->code ?? '',
                'name' => $website->country?->name ?? '',
            ],
            // Null rather than zero where nothing was measured: a site with no
            // metric row has not been assessed, which is not the same claim as
            // a site measured at zero, and the cell renders it differently.
            'traffic' => $metric?->monthly_traffic,
            'domainRating' => $metric?->ahrefs_dr,
            'domainAuthority' => $metric?->moz_da,
            'spamScore' => $metric?->spam_score,
            'publicationHours' => $hours,
            'publicationLabel' => PublicationSpeed::describe($hours),
            'linkType' => $website->link_type->value,
            'priceCents' => $price?->price_cents ?? 0,
            // Zero means the publisher does not write; the row shows the
            // "+ writing" line only when there is a fee to show.
            'writingFeeCents' => $price?->writing_fee_cents ?? 0,
            'isFavorite' => $isFavorite,
            'isBlacklisted' => $isBlacklisted,
            'cartItemId' => $cartItemId,
            'usedInProject' => $isUsed,
            'warnings' => $this->warnings($website, $projectLanguages, $projectTopics),
        ];
    }

    /**
     * Where a site and the project disagree.
     *
     * Informational, never exclusionary. A publisher who does not take the
     * project's topic is still a site somebody might buy — for a different
     * article, or because the topic is not in this one — and hiding the row
     * would be the catalog deciding something the buyer is better placed to
     * decide. The row says what is wrong and leaves it alone.
     *
     * @param  array<int, string>  $projectLanguages
     * @param  array<string, string>  $projectTopics
     * @return list<array{kind: string, message: string}>
     */
    private function warnings(Website $website, array $projectLanguages, array $projectTopics): array
    {
        $warnings = [];

        if ($projectLanguages !== [] && ! in_array($website->primary_language_id, array_keys($projectLanguages), true)) {
            $warnings[] = [
                'kind' => 'language',
                'message' => sprintf(
                    'Publishes in %s. This project targets %s.',
                    $website->primaryLanguage?->name ?? 'another language',
                    $this->list(array_values($projectLanguages)),
                ),
            ];
        }

        $missing = [];

        foreach ($projectTopics as $slug => $name) {
            if (! $website->acceptsTopic($slug)) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            $warnings[] = [
                'kind' => 'topic',
                'message' => 'Does not accept '.$this->list($missing).'.',
            ];
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $items
     */
    private function list(array $items): string
    {
        if (count($items) <= 2) {
            return implode(' and ', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, bool>
     */
    private function lookup(string $table, array $ids, ?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        return DB::table($table)
            ->where('user_id', $userId)
            ->whereIn('website_id', $ids)
            ->pluck('website_id')
            ->flip()
            ->map(static fn (): bool => true)
            ->all();
    }

    /**
     * The cart item id per site, so a row in the cart can offer to remove it.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function cartItems(array $ids, ?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        return DB::table('cart_items')
            ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->where('carts.user_id', $userId)
            ->whereIn('cart_items.website_id', $ids)
            ->pluck('cart_items.id', 'cart_items.website_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, bool>
     */
    private function usedInProject(array $ids, ?Project $project): array
    {
        if ($project === null) {
            return [];
        }

        return DB::table('posts')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->whereIn('website_id', $ids)
            ->pluck('website_id')
            ->flip()
            ->map(static fn (): bool => true)
            ->all();
    }
}
