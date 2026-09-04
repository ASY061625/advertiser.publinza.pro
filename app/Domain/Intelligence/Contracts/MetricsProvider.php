<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Contracts;

use App\Domain\Intelligence\DTOs\DomainMetrics;
use App\Domain\Intelligence\Exceptions\MetricsUnavailable;

/**
 * Where a domain's SEO figures come from.
 *
 * Four vendors sell approximately this data and no two agree on its shape, its
 * names or its scale. The interface is the seam: everything above it —
 * the table, the charts, the recommendations — reads DomainMetrics and never
 * learns who answered, so changing vendor is a line in config rather than a
 * change to the tab.
 *
 * The comparison is the reason the seam matters more than usual here. Every
 * number on the tab is a delta against the project's own site, and a delta
 * between two vendors' figures is not a measurement of anything — Ahrefs and
 * Moz disagree about the same domain by a wide margin. So one provider answers
 * for every domain in a project, and the row records which one it was.
 */
interface MetricsProvider
{
    /** The stored key. Goes in the row, so it must not change once used. */
    public function key(): string;

    /** How the vendor is named to an advertiser: "Data from Ahrefs". */
    public function label(): string;

    /** False when the vendor's credentials are not configured. */
    public function isConfigured(): bool;

    /**
     * One domain's figures.
     *
     * @param  string  $ownDomain  The project's own site, for the overlap and
     *                             gap analysis. Passing the same domain in both
     *                             arguments asks for the project's own row.
     *
     * @throws MetricsUnavailable When the vendor cannot be reached, refuses the
     *                            request, or answers with something unreadable.
     *                            Never returns a half-filled DomainMetrics: a
     *                            zero that means "the API timed out" is
     *                            indistinguishable from a site with no traffic.
     */
    public function fetch(string $domain, string $ownDomain): DomainMetrics;
}
