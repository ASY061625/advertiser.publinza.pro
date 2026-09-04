<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Intelligence\Actions\FetchCompetitorMetrics;
use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Intelligence\Models\Competitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One vendor call, off the request.
 *
 * Queued because the four providers each need three to six HTTP calls and an
 * advertiser adding a rival should get their row back immediately, in a loading
 * state, rather than holding a connection open for a vendor's round trip.
 *
 * One try. The action already turns every provider failure into a `failed` row
 * with a reason on it, and the tab offers a Refresh button — a retry loop would
 * spend the same metered vendor calls on an API that is already not answering,
 * and would do it out of sight of the person who could decide otherwise.
 */
final class FetchCompetitorMetricsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $competitorId) {}

    public function handle(FetchCompetitorMetrics $fetch): void
    {
        $competitor = Competitor::query()->find($this->competitorId);

        // Removed between being queued and being run. Not an error: the row is
        // gone and so is the reason to ask about it.
        if ($competitor === null) {
            return;
        }

        $fetch->handle($competitor);
    }

    /**
     * The row must not be left saying "loading" forever.
     *
     * `handle()` catches the provider's own failures; this catches everything
     * else — a timeout, a worker restart, a bug in the mapping — because a
     * spinner that never resolves is the one state the tab cannot explain.
     */
    public function failed(?Throwable $e): void
    {
        Competitor::query()->where('id', $this->competitorId)->update([
            'fetch_state' => FetchState::Failed->value,
            'fetch_error' => mb_substr($e?->getMessage() ?? 'The metrics fetch did not finish.', 0, 190),
        ]);
    }
}
