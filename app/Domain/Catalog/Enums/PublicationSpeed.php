<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * How quickly a publisher goes live, as the four bands buyers actually choose
 * between.
 *
 * The column is `publication_period_hours`, a number. A buyer does not shop in
 * hours — they shop for "this week" or "today" — so the filter offers bands and
 * this enum owns the one mapping from band to hours. Putting those boundaries
 * anywhere else means the label on the row and the filter that selected it can
 * disagree about what "3–7 days" contains.
 */
enum PublicationSpeed: string
{
    case Day = 'day';
    case Fast = 'fast';
    case Week = 'week';
    case Slow = 'slow';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Within 24 hours',
            self::Fast => '1–3 days',
            self::Week => '3–7 days',
            self::Slow => 'Over a week',
        };
    }

    /**
     * The half-open band, in hours: [from, to). `to` is null for the last one.
     *
     * @return array{int, int|null}
     */
    public function hours(): array
    {
        return match ($this) {
            self::Day => [0, 24],
            self::Fast => [24, 72],
            self::Week => [72, 168],
            self::Slow => [168, null],
        };
    }

    public static function forHours(int $hours): self
    {
        return match (true) {
            $hours <= 24 => self::Day,
            $hours < 72 => self::Fast,
            $hours < 168 => self::Week,
            default => self::Slow,
        };
    }

    /** What the Publication time column prints for a site. */
    public static function describe(int $hours): string
    {
        return match (true) {
            $hours <= 24 => '~1 day',
            $hours <= 48 => '1–2 days',
            $hours <= 72 => '2–3 days',
            $hours <= 168 => '3–7 days',
            default => 'Over a week',
        };
    }
}
