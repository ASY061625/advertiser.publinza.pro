<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The dashboard's date filter, and the equivalent window before it.
 *
 * "Previous equivalent period" is the same number of days ending where this one
 * begins, not the previous calendar month — comparing a 31-day month against a
 * 28-day one would make February look like a collapse every year.
 */
final readonly class DateRange
{
    public const PRESETS = ['last_7', 'last_30', 'quarter', 'year', 'custom'];

    public function __construct(
        public string $key,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $label,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $key = in_array($request->input('range'), self::PRESETS, true)
            ? (string) $request->input('range')
            : 'last_30';

        $now = CarbonImmutable::now()->endOfDay();

        if ($key === 'custom') {
            $from = self::parse($request->input('from')) ?? $now->subDays(29)->startOfDay();
            $to = self::parse($request->input('to')) ?? $now;

            // A backwards range is a typo, not an error worth refusing.
            if ($from->greaterThan($to)) {
                [$from, $to] = [$to, $from];
            }

            return new self('custom', $from->startOfDay(), $to->endOfDay(), 'Custom range');
        }

        return match ($key) {
            'last_7' => new self($key, $now->subDays(6)->startOfDay(), $now, 'Last 7 days'),
            'quarter' => new self($key, $now->subDays(89)->startOfDay(), $now, 'Quarter'),
            'year' => new self($key, $now->subDays(364)->startOfDay(), $now, 'Year'),
            default => new self('last_30', $now->subDays(29)->startOfDay(), $now, 'Last 30 days'),
        };
    }

    /** The same length again, ending the instant this range starts. */
    public function previous(): self
    {
        $length = $this->lengthInDays();

        return new self(
            $this->key,
            $this->from->subDays($length)->startOfDay(),
            $this->from->subSecond(),
            'Previous '.$length.' days',
        );
    }

    public function lengthInDays(): int
    {
        return max(1, (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1);
    }

    /**
     * Buckets short ranges by day and long ones by month, so a year does not
     * render 365 bars two pixels wide.
     */
    public function defaultGranularity(): string
    {
        $days = $this->lengthInDays();

        return match (true) {
            $days <= 31 => 'day',
            $days <= 120 => 'week',
            default => 'month',
        };
    }

    public function granularityFrom(Request $request): string
    {
        $requested = $request->input('granularity');

        return in_array($requested, ['day', 'week', 'month'], true)
            ? (string) $requested
            : $this->defaultGranularity();
    }

    /** Stable across a 5-minute window, so the cache key is not per-second. */
    public function cacheKey(): string
    {
        return sprintf('%s:%s:%s', $this->key, $this->from->toDateString(), $this->to->toDateString());
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
