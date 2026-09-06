<?php

declare(strict_types=1);

namespace App\Domain\Billing\Support;

use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The transactions tab's filters, in one place.
 *
 * Shared by the table and both exports, which is the point: an export that
 * quietly ignores a filter hands somebody a spreadsheet that disagrees with the
 * screen they exported it from.
 */
final class LedgerQuery
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public readonly array $types = [],
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $minCents = null,
        public readonly ?int $maxCents = null,
        public readonly ?string $search = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $types = array_values(array_filter(
            (array) $request->input('types', []),
            static fn ($value): bool => is_string($value)
                && TransactionType::tryFrom($value) !== null,
        ));

        $trim = static function (?string $value): ?string {
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        $cents = static function ($value): ?int {
            if ($value === null || $value === '') {
                return null;
            }

            return (int) round((float) $value * 100);
        };

        return new self(
            types: $types,
            from: $trim($request->query('from')),
            to: $trim($request->query('to')),
            minCents: $cents($request->query('min')),
            maxCents: $cents($request->query('max')),
            search: $trim($request->query('q')),
        );
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->types !== [], fn (Builder $q) => $q->whereIn('type', $this->types))
            ->when($this->from !== null, fn (Builder $q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== null, fn (Builder $q) => $q->whereDate('created_at', '<=', $this->to))
            /*
             * Amount filters compare magnitudes, not signs.
             *
             * "Between $100 and $500" means a charge of $250 as much as a
             * deposit of $250. Comparing the signed column instead would put
             * every charge below every deposit and make the filter useless on
             * exactly the rows people are usually hunting for.
             */
            ->when($this->minCents !== null, fn (Builder $q) => $q->whereRaw('ABS(amount_cents) >= ?', [$this->minCents]))
            ->when($this->maxCents !== null, fn (Builder $q) => $q->whereRaw('ABS(amount_cents) <= ?', [$this->maxCents]))
            ->when($this->search !== null, fn (Builder $q) => $q->where('description', 'like', '%'.$this->search.'%'));
    }

    /** True when nothing is filtered — the empty state says different things. */
    public function isEmpty(): bool
    {
        return $this->types === []
            && $this->from === null
            && $this->to === null
            && $this->minCents === null
            && $this->maxCents === null
            && $this->search === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'types' => $this->types,
            'from' => $this->from,
            'to' => $this->to,
            'min' => $this->minCents === null ? null : $this->minCents / 100,
            'max' => $this->maxCents === null ? null : $this->maxCents / 100,
            'q' => $this->search,
        ];
    }
}
