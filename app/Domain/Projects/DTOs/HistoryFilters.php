<?php

declare(strict_types=1);

namespace App\Domain\Projects\DTOs;

use Illuminate\Http\Request;

/**
 * What the History tab is showing, read from the query string.
 *
 * The filters are the identity of the log being read; the cursor is only where
 * in it the next fifty rows start, so it is deliberately not part of
 * `toQuery()` — changing a filter is a fresh read, and carrying the old
 * position into it would start halfway down a log the reader has not seen.
 */
final readonly class HistoryFilters
{
    public const FAMILIES = ['project', 'folder', 'post', 'money', 'message'];

    public const ACTORS = ['user', 'admin', 'system'];

    public const PER_PAGE = 50;

    /**
     * @param  list<string>  $families
     */
    public function __construct(
        public array $families = [],
        public ?string $actor = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $search = null,
        public ?HistoryCursor $cursor = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $families = array_values(array_intersect(
            self::FAMILIES,
            array_map(static fn (mixed $v): string => (string) $v, (array) $request->input('families', [])),
        ));

        $actor = in_array($request->input('actor'), self::ACTORS, true) ? (string) $request->input('actor') : null;

        return new self(
            families: $families,
            actor: $actor,
            from: self::dateOnly($request, 'from'),
            to: self::dateOnly($request, 'to'),
            search: self::text($request, 'q'),
            cursor: HistoryCursor::parse($request->query('cursor') === null ? null : (string) $request->query('cursor')),
        );
    }

    public function wants(string $family): bool
    {
        return $this->families === [] || in_array($family, $this->families, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'families' => $this->families ?: null,
            'actor' => $this->actor,
            'from' => $this->from,
            'to' => $this->to,
            'q' => $this->search,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** The same read, resumed further down. */
    public function withCursor(?HistoryCursor $cursor): self
    {
        return new self($this->families, $this->actor, $this->from, $this->to, $this->search, $cursor);
    }

    public function isFiltering(): bool
    {
        return $this->families !== []
            || $this->actor !== null
            || $this->from !== null
            || $this->to !== null
            || $this->search !== null;
    }

    private static function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));

        return $value === '' ? null : mb_substr($value, 0, 190);
    }

    private static function dateOnly(Request $request, string $key): ?string
    {
        $value = (string) $request->input($key, '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
