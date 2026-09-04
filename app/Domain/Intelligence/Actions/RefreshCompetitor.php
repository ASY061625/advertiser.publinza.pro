<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Actions;

use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Intelligence\Models\Competitor;
use App\Jobs\FetchCompetitorMetricsJob;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A person asking for one row to be measured again.
 *
 * Rate-limited to once a day per competitor. Vendor calls are metered and
 * billed per row, and the numbers this fetches move on a scale of weeks — a
 * second fetch an hour later costs money to return the same figures.
 *
 * The limit is enforced here rather than only on the button, because the button
 * is in a browser and the endpoint is on the internet.
 */
final class RefreshCompetitor
{
    public function handle(Competitor $competitor): Competitor
    {
        $remaining = $competitor->cooldownSeconds();

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'refresh' => 'This competitor was refreshed recently. You can refresh it again in '
                    .$this->humanise($remaining).'.',
            ]);
        }

        $competitor->forceFill([
            'refreshed_at' => now(),
            'fetch_state' => FetchState::Pending,
            'fetch_error' => null,
        ])->save();

        FetchCompetitorMetricsJob::dispatch($competitor->id);

        return $competitor;
    }

    /**
     * Queues a refetch of anything whose figures have gone stale.
     *
     * Called when the tab is read. A row whose fetch is already pending is
     * skipped — a page opened three times in a minute should not queue three
     * billable vendor calls for the same domain.
     *
     * @param  Collection<int, Competitor>  $competitors
     */
    public function refill($competitors): void
    {
        $days = (int) config('publinza.competitors.cache_days', 7);
        $cutoff = now()->subDays($days);

        foreach ($competitors as $competitor) {
            if ($competitor->fetch_state === FetchState::Pending) {
                continue;
            }

            $fetchedAt = $competitor->latestMetric?->fetched_at;

            // Never measured, or measured longer ago than the cache holds. A
            // row that failed keeps whatever it had and is retried the same way.
            if ($fetchedAt === null || $fetchedAt->lessThan($cutoff)) {
                $competitor->forceFill(['fetch_state' => FetchState::Pending])->save();

                FetchCompetitorMetricsJob::dispatch($competitor->id);
            }
        }
    }

    private function humanise(int $seconds): string
    {
        $hours = (int) floor($seconds / 3600);

        if ($hours >= 1) {
            return $hours === 1 ? 'about an hour' : "about {$hours} hours";
        }

        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes === 1 ? 'a minute' : "{$minutes} minutes";
    }
}
