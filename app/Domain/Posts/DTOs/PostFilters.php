<?php

declare(strict_types=1);

namespace App\Domain\Posts\DTOs;

use App\Domain\Posts\Enums\PostTab;
use Illuminate\Http\Request;

/**
 * Every filter on /posts, read from the query string and written back to it.
 *
 * The query string is the state. A filtered grid is a URL an advertiser can
 * bookmark, share with a colleague or reload without losing — which only holds
 * if the round trip is lossless, so `toQuery()` is the exact inverse of
 * `fromRequest()` and the test suite pins that.
 */
final readonly class PostFilters
{
    public const SORTS = [
        'id', 'domain', 'project', 'anchor_text', 'status',
        'price', 'created_at', 'published_at', 'deadline_at',
    ];

    public const PER_PAGE = [25, 50, 100];

    public const DATE_FIELDS = ['created', 'published'];

    public const DEADLINE_WINDOWS = ['24h', '3d', '7d', 'overdue'];

    /**
     * @param  list<int>  $projects
     * @param  list<string>  $statuses
     * @param  list<int>  $categories
     * @param  list<int>  $countries
     * @param  list<int>  $languages
     */
    public function __construct(
        public PostTab $tab = PostTab::All,
        public ?string $search = null,
        public array $projects = [],
        public array $statuses = [],
        public string $dateField = 'created',
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public array $categories = [],
        public array $countries = [],
        public array $languages = [],
        public ?int $minPriceCents = null,
        public ?int $maxPriceCents = null,
        public ?string $contentMode = null,
        public ?string $anchorContains = null,
        public ?string $targetUrlContains = null,
        public ?int $minDr = null,
        public ?int $maxDr = null,
        public ?int $minTraffic = null,
        public ?int $maxTraffic = null,
        public ?int $folderId = null,
        public bool $unreadOnly = false,
        public ?string $deadlineWithin = null,
        public string $sort = 'created_at',
        public string $direction = 'desc',
        public int $perPage = 25,
        /**
         * A project the grid is locked to. Not a filter: it never comes from
         * the request, never round-trips through the query string, and never
         * counts towards isFiltering() — a project's own Post management tab
         * showing "no posts match these filters" because it is scoped to that
         * project would be nonsense. Set it with forProject().
         */
        public ?int $projectScope = null,
    ) {}

    /**
     * The status tab's key is a parameter because a project's page already
     * spends `tab` on which tab of the project is open. There, the grid's own
     * tab travels as `posts_tab`; on /posts it is plain `tab`.
     */
    public static function fromRequest(Request $request, string $tabKey = 'tab'): self
    {
        return new self(
            tab: PostTab::tryFromRequest($request->input($tabKey)),
            search: self::text($request, 'q'),
            projects: self::ints($request, 'projects'),
            statuses: self::strings($request, 'statuses'),
            dateField: in_array($request->input('date_field'), self::DATE_FIELDS, true)
                ? (string) $request->input('date_field')
                : 'created',
            dateFrom: self::date($request, 'from'),
            dateTo: self::date($request, 'to'),
            categories: self::ints($request, 'categories'),
            countries: self::ints($request, 'countries'),
            languages: self::ints($request, 'languages'),
            // The UI talks in whole dollars; the schema stores cents.
            minPriceCents: $request->integer('min_price') > 0 ? $request->integer('min_price') * 100 : null,
            maxPriceCents: $request->integer('max_price') > 0 ? $request->integer('max_price') * 100 : null,
            contentMode: in_array($request->input('content_mode'), ['advertiser_provides', 'publisher_writes'], true)
                ? (string) $request->input('content_mode')
                : null,
            anchorContains: self::text($request, 'anchor'),
            targetUrlContains: self::text($request, 'target'),
            minDr: self::bounded($request, 'min_dr', 0, 100),
            maxDr: self::bounded($request, 'max_dr', 0, 100),
            minTraffic: $request->integer('min_traffic') > 0 ? $request->integer('min_traffic') : null,
            maxTraffic: $request->integer('max_traffic') > 0 ? $request->integer('max_traffic') : null,
            folderId: $request->integer('folder') ?: null,
            unreadOnly: $request->boolean('unread'),
            deadlineWithin: in_array($request->input('deadline'), self::DEADLINE_WINDOWS, true)
                ? (string) $request->input('deadline')
                : null,
            sort: in_array($request->input('sort'), self::SORTS, true)
                ? (string) $request->input('sort')
                : 'created_at',
            direction: $request->input('direction') === 'asc' ? 'asc' : 'desc',
            perPage: in_array($request->integer('per_page'), self::PER_PAGE, true)
                ? $request->integer('per_page')
                : 25,
        );
    }

    /**
     * The same filters, locked to one project.
     *
     * Any `projects[]` from the query string is dropped rather than ANDed with
     * the scope: the grid can only ever show this project, so leaving the value
     * in would put a chip on screen for a filter that changes nothing.
     */
    public function forProject(int $projectId): self
    {
        return new self(
            tab: $this->tab,
            search: $this->search,
            projects: [],
            statuses: $this->statuses,
            dateField: $this->dateField,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            categories: $this->categories,
            countries: $this->countries,
            languages: $this->languages,
            minPriceCents: $this->minPriceCents,
            maxPriceCents: $this->maxPriceCents,
            contentMode: $this->contentMode,
            anchorContains: $this->anchorContains,
            targetUrlContains: $this->targetUrlContains,
            minDr: $this->minDr,
            maxDr: $this->maxDr,
            minTraffic: $this->minTraffic,
            maxTraffic: $this->maxTraffic,
            folderId: $this->folderId,
            unreadOnly: $this->unreadOnly,
            deadlineWithin: $this->deadlineWithin,
            sort: $this->sort,
            direction: $this->direction,
            perPage: $this->perPage,
            projectScope: $projectId,
        );
    }

    /**
     * The query string this state serialises to. Defaults are omitted so a
     * plain /posts stays a plain URL rather than growing twenty empty keys.
     *
     * The status tab always serialises as `tab` here: this is the shape the
     * client holds its filter state in, and the caller renames it if the URL
     * it writes needs a different key.
     *
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'tab' => $this->tab === PostTab::All ? null : $this->tab->value,
            'q' => $this->search,
            'projects' => $this->projects ?: null,
            'statuses' => $this->statuses ?: null,
            'date_field' => $this->dateField === 'created' ? null : $this->dateField,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
            'categories' => $this->categories ?: null,
            'countries' => $this->countries ?: null,
            'languages' => $this->languages ?: null,
            'min_price' => $this->minPriceCents === null ? null : intdiv($this->minPriceCents, 100),
            'max_price' => $this->maxPriceCents === null ? null : intdiv($this->maxPriceCents, 100),
            'content_mode' => $this->contentMode,
            'anchor' => $this->anchorContains,
            'target' => $this->targetUrlContains,
            'min_dr' => $this->minDr,
            'max_dr' => $this->maxDr,
            'min_traffic' => $this->minTraffic,
            'max_traffic' => $this->maxTraffic,
            'folder' => $this->folderId,
            'unread' => $this->unreadOnly ? 1 : null,
            'deadline' => $this->deadlineWithin,
            'sort' => $this->sort === 'created_at' ? null : $this->sort,
            'direction' => $this->direction === 'desc' ? null : $this->direction,
            'per_page' => $this->perPage === 25 ? null : $this->perPage,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Whether anything is filtering the grid. Drives the difference between
     * "you have no posts yet" and "no posts match these filters" — two very
     * different things to tell someone.
     */
    public function isFiltering(): bool
    {
        $query = $this->toQuery();

        // Sort, direction, page size and the tab arrange the grid; they do not
        // narrow it, so they do not turn an empty account into "no matches".
        unset($query['sort'], $query['direction'], $query['per_page'], $query['tab']);

        return $query !== [];
    }

    private static function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));

        return $value === '' ? null : mb_substr($value, 0, 190);
    }

    private static function date(Request $request, string $key): ?string
    {
        $value = (string) $request->input($key, '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private static function bounded(Request $request, string $key, int $min, int $max): ?int
    {
        if (! $request->filled($key)) {
            return null;
        }

        return max($min, min($max, $request->integer($key)));
    }

    /**
     * @return list<int>
     */
    private static function ints(Request $request, string $key): array
    {
        $values = array_map(static fn (mixed $v): int => (int) $v, (array) $request->input($key, []));

        return array_values(array_unique(array_filter($values, static fn (int $v): bool => $v > 0)));
    }

    /**
     * @return list<string>
     */
    private static function strings(Request $request, string $key): array
    {
        $values = array_map(static fn (mixed $v): string => (string) $v, (array) $request->input($key, []));

        return array_values(array_unique(array_filter($values, static fn (string $v): bool => $v !== '')));
    }
}
