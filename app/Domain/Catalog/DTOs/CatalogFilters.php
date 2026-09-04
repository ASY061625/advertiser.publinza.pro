<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DTOs;

use App\Domain\Catalog\Enums\PublicationSpeed;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\UrlNormalizer;
use Illuminate\Http\Request;

/**
 * Everything the catalog is showing, read from the query string.
 *
 * The query string is the whole state. Fourteen filter groups, the sort, the
 * layout and the page size all live there and nowhere else, so a view is a link
 * — a buyer can send "these forty sites under $200 in Finance" to a colleague
 * and they will see the same forty. That is worth the size of this class.
 *
 * Ranges are nullable pairs rather than defaulted to the catalog's own min and
 * max. "No price filter" and "a price filter that happens to span the whole
 * catalog" look identical on screen and are different in the URL, in the
 * applied-filter chips, and in what happens when the catalog grows: a stored
 * link with an explicit ceiling should keep its ceiling, and one with no filter
 * should widen with the inventory.
 */
final readonly class CatalogFilters
{
    public const SORTS = ['relevance', 'price_asc', 'price_desc', 'traffic', 'dr', 'newest'];

    public const PER_PAGE = [25, 50, 100];

    public const VIEWS = ['table', 'cards'];

    /**
     * @param  list<int>  $categories
     * @param  list<int>  $countries
     * @param  list<int>  $languages
     * @param  array{int, int}|null  $price  Cents.
     * @param  array{int, int}|null  $traffic
     * @param  array{int, int}|null  $dr
     * @param  array{int, int}|null  $da
     * @param  list<string>  $speeds  PublicationSpeed values.
     * @param  list<string>  $topics  Sensitive-topic slugs the site must accept.
     */
    public function __construct(
        public ?string $query = null,
        public ?string $domain = null,
        public array $categories = [],
        public array $countries = [],
        public array $languages = [],
        public ?array $price = null,
        public ?array $traffic = null,
        public ?array $dr = null,
        public ?array $da = null,
        public ?int $maxSpam = null,
        public array $speeds = [],
        public ?string $linkType = null,
        public array $topics = [],
        public bool $hideBlacklisted = true,
        public bool $onlyFavorites = false,
        public bool $notUsedInProject = false,
        public bool $hasTrafficData = false,
        public string $sort = 'relevance',
        public int $perPage = 50,
        public string $view = 'table',
        public ?string $cursor = null,
        public ?int $projectId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            query: self::text($request, 'q'),
            // Normalised through the same judge the rest of the app uses, so
            // "https://Example.com/pricing" finds example.com.
            domain: self::domain($request),
            categories: self::ids($request, 'categories'),
            countries: self::ids($request, 'countries'),
            languages: self::ids($request, 'languages'),
            // Dollars in the URL, cents in the database. The URL is a thing
            // people read and edit by hand; cents there would be a trap.
            price: self::range($request, 'price', 100),
            traffic: self::range($request, 'traffic'),
            dr: self::range($request, 'dr'),
            da: self::range($request, 'da'),
            maxSpam: self::intOrNull($request, 'max_spam'),
            speeds: self::allowed($request, 'speed', array_map(
                static fn (PublicationSpeed $s): string => $s->value,
                PublicationSpeed::cases(),
            )),
            linkType: self::one($request, 'link_type', ['dofollow', 'nofollow']),
            topics: self::slugs($request, 'topics'),
            // On by default: a blacklist is a standing instruction, and having
            // to re-apply it on every visit would make it useless.
            hideBlacklisted: ! $request->boolean('show_blacklisted'),
            onlyFavorites: $request->boolean('favorites'),
            notUsedInProject: $request->boolean('unused'),
            hasTrafficData: $request->boolean('has_traffic'),
            sort: self::one($request, 'sort', self::SORTS) ?? 'relevance',
            perPage: in_array($request->integer('per_page'), self::PER_PAGE, true)
                ? $request->integer('per_page')
                : 50,
            view: self::one($request, 'view', self::VIEWS) ?? 'table',
            cursor: self::text($request, 'cursor'),
            projectId: $request->integer('project') ?: null,
        );
    }

    /**
     * Whether anything narrows the catalog.
     *
     * The blacklist toggle is deliberately excluded. It is on by default, so
     * counting it would mean the catalog is never unfiltered — and the "no
     * filters and no results" state, which is the one that means something is
     * broken, could never be reached.
     */
    public function isFiltering(): bool
    {
        return $this->query !== null
            || $this->domain !== null
            || $this->categories !== []
            || $this->countries !== []
            || $this->languages !== []
            || $this->price !== null
            || $this->traffic !== null
            || $this->dr !== null
            || $this->da !== null
            || $this->maxSpam !== null
            || $this->speeds !== []
            || $this->linkType !== null
            || $this->topics !== []
            || $this->onlyFavorites
            || $this->notUsedInProject
            || $this->hasTrafficData;
    }

    /**
     * The query string this view is reachable at.
     *
     * Only what differs from the defaults, so a bare catalog link stays bare
     * and a shared one carries exactly the filters that were applied.
     *
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->query,
            'domain' => $this->domain,
            'categories' => $this->categories ?: null,
            'countries' => $this->countries ?: null,
            'languages' => $this->languages ?: null,
            'price' => self::rangeToQuery($this->price, 100),
            'traffic' => self::rangeToQuery($this->traffic),
            'dr' => self::rangeToQuery($this->dr),
            'da' => self::rangeToQuery($this->da),
            'max_spam' => $this->maxSpam,
            'speed' => $this->speeds ?: null,
            'link_type' => $this->linkType,
            'topics' => $this->topics ?: null,
            'show_blacklisted' => $this->hideBlacklisted ? null : true,
            'favorites' => $this->onlyFavorites ?: null,
            'unused' => $this->notUsedInProject ?: null,
            'has_traffic' => $this->hasTrafficData ?: null,
            'sort' => $this->sort === 'relevance' ? null : $this->sort,
            'per_page' => $this->perPage === 50 ? null : $this->perPage,
            'view' => $this->view === 'table' ? null : $this->view,
            'project' => $this->projectId,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** The same view, from the top. Used whenever a filter changes. */
    public function withoutCursor(): self
    {
        return $this->with(['cursor' => null]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(
            query: array_key_exists('query', $changes) ? $changes['query'] : $this->query,
            domain: array_key_exists('domain', $changes) ? $changes['domain'] : $this->domain,
            categories: $changes['categories'] ?? $this->categories,
            countries: $changes['countries'] ?? $this->countries,
            languages: $changes['languages'] ?? $this->languages,
            price: array_key_exists('price', $changes) ? $changes['price'] : $this->price,
            traffic: array_key_exists('traffic', $changes) ? $changes['traffic'] : $this->traffic,
            dr: array_key_exists('dr', $changes) ? $changes['dr'] : $this->dr,
            da: array_key_exists('da', $changes) ? $changes['da'] : $this->da,
            maxSpam: array_key_exists('maxSpam', $changes) ? $changes['maxSpam'] : $this->maxSpam,
            speeds: $changes['speeds'] ?? $this->speeds,
            linkType: array_key_exists('linkType', $changes) ? $changes['linkType'] : $this->linkType,
            topics: $changes['topics'] ?? $this->topics,
            hideBlacklisted: $changes['hideBlacklisted'] ?? $this->hideBlacklisted,
            onlyFavorites: $changes['onlyFavorites'] ?? $this->onlyFavorites,
            notUsedInProject: $changes['notUsedInProject'] ?? $this->notUsedInProject,
            hasTrafficData: $changes['hasTrafficData'] ?? $this->hasTrafficData,
            sort: $changes['sort'] ?? $this->sort,
            perPage: $changes['perPage'] ?? $this->perPage,
            view: $changes['view'] ?? $this->view,
            cursor: array_key_exists('cursor', $changes) ? $changes['cursor'] : $this->cursor,
            projectId: array_key_exists('projectId', $changes) ? $changes['projectId'] : $this->projectId,
        );
    }

    /**
     * The project's targeting, as filters, for a first visit in buying mode.
     *
     * Seeded rather than forced: these land in the URL as ordinary filters the
     * buyer can then remove. A project that targets Germany is a strong hint
     * about which sites are worth reading, not a rule about which sites exist —
     * and a filter you cannot see or clear is indistinguishable from a bug.
     *
     * Only applied when nothing has been chosen yet. Re-seeding on every visit
     * would put a filter back the moment it was removed.
     */
    public function seededFrom(Project $project): self
    {
        if ($this->isFiltering()) {
            return $this;
        }

        return $this->with([
            'categories' => $project->category_id === null ? [] : [$project->category_id],
            'countries' => $project->countries->pluck('id')->all(),
            'languages' => $project->languages->pluck('id')->all(),
        ]);
    }

    // ------------------------------------------------------------- parsing

    private static function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));

        return $value === '' ? null : mb_substr($value, 0, 190);
    }

    private static function domain(Request $request): ?string
    {
        $value = self::text($request, 'domain');

        return $value === null ? null : UrlNormalizer::hostOf($value) ?? mb_strtolower($value);
    }

    /**
     * @return list<int>
     */
    private static function ids(Request $request, string $key): array
    {
        $ids = array_map(static fn (mixed $v): int => (int) $v, (array) $request->input($key, []));

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return list<string>
     */
    private static function slugs(Request $request, string $key): array
    {
        $values = array_map(static fn (mixed $v): string => mb_substr((string) $v, 0, 96), (array) $request->input($key, []));

        return array_values(array_unique(array_filter($values, static fn (string $v): bool => $v !== '')));
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private static function allowed(Request $request, string $key, array $allowed): array
    {
        $values = array_map(static fn (mixed $v): string => (string) $v, (array) $request->input($key, []));

        return array_values(array_intersect($allowed, $values));
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function one(Request $request, string $key, array $allowed): ?string
    {
        $value = (string) $request->input($key, '');

        return in_array($value, $allowed, true) ? $value : null;
    }

    private static function intOrNull(Request $request, string $key): ?int
    {
        return $request->has($key) && is_numeric($request->input($key))
            ? max(0, $request->integer($key))
            : null;
    }

    /**
     * A "10-250" pair, in the units the URL speaks.
     *
     * @return array{int, int}|null
     */
    private static function range(Request $request, string $key, int $multiplier = 1): ?array
    {
        $value = (string) $request->input($key, '');

        if (preg_match('/^(\d{1,12})-(\d{1,12})$/', $value, $matches) !== 1) {
            return null;
        }

        $low = (int) $matches[1] * $multiplier;
        $high = (int) $matches[2] * $multiplier;

        // Reversed by hand in the URL is a typo, not an empty result set.
        return $low <= $high ? [$low, $high] : [$high, $low];
    }

    /**
     * @param  array{int, int}|null  $range
     */
    private static function rangeToQuery(?array $range, int $divisor = 1): ?string
    {
        return $range === null
            ? null
            : intdiv($range[0], $divisor).'-'.intdiv($range[1], $divisor);
    }
}
