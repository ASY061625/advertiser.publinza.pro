<?php

declare(strict_types=1);

namespace App\Domain\Projects\DTOs;

/**
 * Where in the log to start reading.
 *
 * The timeline is append-only and read newest-first, so an offset is not a
 * position in it: anything written between two requests pushes every row down
 * one and page two repeats the last row of page one. A cursor names the row
 * itself, and rows added above it cannot move it.
 *
 * It has to match the sort exactly — occurred_at, then source, then id — or it
 * is only approximately a position, which is the same bug more quietly. Rows
 * from four tables routinely share a timestamp to the second, and the tiebreak
 * is the only thing separating them.
 *
 * Two shapes, one parameter:
 *
 *   2026-04-18 09:31:07|audit-412   continue below this exact row
 *   2026-04-18                      jump to the end of that day
 *
 * The second is the "Jump to date" control. It carries no tiebreak, so it
 * reads inclusively from the last instant of the day — a reading position
 * rather than a row, which is what jumping to a date means.
 */
final readonly class HistoryCursor
{
    public function __construct(
        public string $occurredAt,
        public ?string $source = null,
        public ?int $sourceId = null,
    ) {}

    public static function parse(?string $value): ?self
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $value) === 1) {
            return new self($value.' 23:59:59');
        }

        $pattern = '/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(?:\.\d{1,6})?\|([a-z]+)-(\d+)$/';

        if (preg_match($pattern, $value, $matches) !== 1) {
            return null;
        }

        return new self("{$matches[1]} {$matches[2]}", $matches[3], (int) $matches[4]);
    }

    /**
     * The cursor that continues below a narrated event.
     *
     * `id` is already "{source}-{source_id}" and `occurredAt` is the raw column
     * value, so the round trip compares the same strings the database sorted.
     *
     * @param  array<string, mixed>  $event
     */
    public static function after(array $event): self
    {
        $cursor = self::parse($event['occurredAt'].'|'.$event['id']);

        // Only reachable if a row carried a timestamp no database wrote.
        return $cursor ?? new self((string) $event['occurredAt']);
    }

    public function toQuery(): string
    {
        return $this->source === null
            ? $this->occurredAt
            : "{$this->occurredAt}|{$this->source}-{$this->sourceId}";
    }
}
