<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A provider could not answer.
 *
 * Thrown rather than returning zeros, because the two are not the same fact and
 * the tab shows them differently: an outage keeps the last cached figures under
 * an amber notice, while a zero would quietly redraw every chart around a site
 * that had supposedly lost all its traffic overnight.
 */
final class MetricsUnavailable extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $domain,
        string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct("{$provider} could not answer for {$domain}: {$reason}", 0, $previous);
    }

    /** Short enough for the `fetch_error` column, and safe to show a person. */
    public function summary(): string
    {
        return mb_substr($this->getMessage(), 0, 190);
    }
}
