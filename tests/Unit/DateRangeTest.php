<?php

declare(strict_types=1);

use App\Domain\Analytics\DTOs\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

function dateRange(array $query): DateRange
{
    return DateRange::fromRequest(Request::create('/dashboard/metrics', 'GET', $query));
}

it('compares against the same length again, not the previous calendar month', function (): void {
    CarbonImmutable::setTestNow('2026-03-31 12:00:00');

    $current = dateRange(['range' => 'last_30']);
    $previous = $current->previous();

    // February has 28 days. A calendar comparison would make every March look
    // like a 7% jump for no reason at all.
    expect($previous->lengthInDays())->toBe($current->lengthInDays())
        ->and($previous->to->lessThan($current->from))->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('picks a bucket size that keeps the bars readable', function (): void {
    expect(dateRange(['range' => 'last_7'])->defaultGranularity())->toBe('day')
        ->and(dateRange(['range' => 'last_30'])->defaultGranularity())->toBe('day')
        ->and(dateRange(['range' => 'quarter'])->defaultGranularity())->toBe('week')
        ->and(dateRange(['range' => 'year'])->defaultGranularity())->toBe('month');
});

it('ignores a granularity it does not recognise', function (): void {
    $range = dateRange(['range' => 'last_7']);
    $request = Request::create('/dashboard/metrics', 'GET', ['granularity' => 'fortnight']);

    expect($range->granularityFrom($request))->toBe('day');
});

it('rejects a malformed custom date rather than guessing at it', function (): void {
    CarbonImmutable::setTestNow('2026-03-15 12:00:00');

    $range = dateRange(['range' => 'custom', 'from' => 'last tuesday', 'to' => '2026-03-10']);

    expect($range->from->toDateString())->toBe('2026-02-14')
        ->and($range->to->toDateString())->toBe('2026-03-10');

    CarbonImmutable::setTestNow();
});

it('keys the cache by range rather than by the current second', function (): void {
    CarbonImmutable::setTestNow('2026-03-15 12:00:00');
    $first = dateRange(['range' => 'last_30'])->cacheKey();

    CarbonImmutable::setTestNow('2026-03-15 12:04:59');
    $second = dateRange(['range' => 'last_30'])->cacheKey();

    expect($first)->toBe($second);

    CarbonImmutable::setTestNow();
});
