<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Providers;

use App\Domain\Intelligence\Contracts\MetricsProvider;
use App\Domain\Intelligence\DTOs\GapKeyword;
use App\Domain\Intelligence\Exceptions\MetricsUnavailable;
use App\Domain\Projects\Support\UrlNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * What every vendor integration does the same way.
 *
 * The differences between these four APIs are entirely in their URLs and their
 * field names. Everything else — the timeout, turning any failure into one
 * exception type, capping the gap list — is identical, and is here so that a
 * new vendor is a mapping rather than a new set of decisions about timeouts.
 *
 * Nothing here retries. A refresh is a person pressing a button and a queued
 * job that can be run again; a retry loop inside the request would hold a
 * worker open against an API that is already not answering.
 */
abstract class HttpMetricsProvider implements MetricsProvider
{
    protected const TIMEOUT_SECONDS = 12;

    /** How many gap keywords are ever stored, whatever the vendor returns. */
    protected function gapLimit(): int
    {
        return (int) config('publinza.competitors.gap_keywords', 100);
    }

    /** How many referring domains are ever read back, whatever a vendor offers. */
    protected function referrerLimit(): int
    {
        return (int) config('publinza.competitors.referring_domains', 500);
    }

    protected function request(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(5)
            ->acceptJson()
            ->withUserAgent('Publinza/1.0 (+https://publinza.pro)');
    }

    /**
     * Runs one call and turns every way it can go wrong into MetricsUnavailable.
     *
     * @param  callable(): Response  $call
     */
    protected function send(string $domain, callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException $e) {
            throw new MetricsUnavailable($this->key(), $domain, 'the API could not be reached', $e);
        } catch (Throwable $e) {
            throw new MetricsUnavailable($this->key(), $domain, 'the request failed', $e);
        }

        if ($response->failed()) {
            // The status, not the body: a vendor's error body can contain the
            // key that was rejected, and this string is stored on the row.
            throw new MetricsUnavailable($this->key(), $domain, "the API answered {$response->status()}");
        }

        return $response;
    }

    /**
     * The last twelve months, oldest first, from whatever the vendor returned.
     *
     * Providers disagree about order and about how many months they hand back.
     * Sorting and slicing here means the chart can plot the array as it stands.
     *
     * @param  array<int, array{month: string, traffic: int}>  $points
     * @return list<array{month: string, traffic: int}>
     */
    protected function lastTwelveMonths(array $points): array
    {
        usort($points, static fn (array $a, array $b): int => strcmp($a['month'], $b['month']));

        return array_values(array_slice($points, -12));
    }

    /**
     * Vendor host strings, normalised the way this codebase spells a host.
     *
     * They arrive as "WWW.Example.com", "example.com/" and "https://example.com"
     * from the same API, and they are about to be matched against the catalog
     * by exact string — so three spellings of one site would find nothing three
     * times over.
     *
     * @param  array<int, string>  $hosts
     * @return list<string>
     */
    protected function hosts(array $hosts): array
    {
        $clean = [];

        foreach ($hosts as $host) {
            $normalized = UrlNormalizer::hostOf($host);

            if ($normalized !== null) {
                $clean[$normalized] = true;
            }
        }

        return array_slice(array_keys($clean), 0, $this->referrerLimit());
    }

    /**
     * @param  list<GapKeyword>  $keywords
     * @return list<GapKeyword>
     */
    protected function topGaps(array $keywords): array
    {
        // Best position first, then by volume: the useful end of a gap list is
        // the keywords they already rank well for, not the ones they barely do.
        usort($keywords, static fn (GapKeyword $a, GapKeyword $b): int => $a->position <=> $b->position
            ?: $b->volume <=> $a->volume);

        return array_values(array_slice($keywords, 0, $this->gapLimit()));
    }
}
