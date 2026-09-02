<?php

declare(strict_types=1);

namespace App\Domain\Projects\DTOs;

use App\Domain\Projects\Enums\ProjectStatus;
use Illuminate\Http\Request;

/**
 * The /projects filter state, read from the query string and written back.
 *
 * Same contract as the posts grid: the URL is the state, so a filtered view is
 * a link, and `toQuery()` is the exact inverse of `fromRequest()`.
 */
final readonly class ProjectFilters
{
    public const SORTS = ['name', 'posts', 'spent_month', 'created_at'];

    public const VIEWS = ['table', 'cards'];

    public function __construct(
        public string $status = 'active',
        public ?string $search = null,
        public string $sort = 'spent_month',
        public string $direction = 'desc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            // Active by default: an archived project is one you have finished
            // with, and it should not be in the way of the ones you have not.
            status: in_array($request->input('status'), ['active', 'archived', 'all'], true)
                ? (string) $request->input('status')
                : 'active',
            search: self::text($request, 'q'),
            sort: in_array($request->input('sort'), self::SORTS, true)
                ? (string) $request->input('sort')
                : 'spent_month',
            direction: $request->input('direction') === 'asc' ? 'asc' : 'desc',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'status' => $this->status === 'active' ? null : $this->status,
            'q' => $this->search,
            'sort' => $this->sort === 'spent_month' ? null : $this->sort,
            'direction' => $this->direction === 'desc' ? null : $this->direction,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** Whether anything is narrowing the list, as opposed to arranging it. */
    public function isFiltering(): bool
    {
        return $this->search !== null || $this->status !== 'active';
    }

    public function statusEnum(): ?ProjectStatus
    {
        return match ($this->status) {
            'active' => ProjectStatus::Active,
            'archived' => ProjectStatus::Archived,
            default => null,
        };
    }

    private static function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));

        return $value === '' ? null : mb_substr($value, 0, 190);
    }
}
