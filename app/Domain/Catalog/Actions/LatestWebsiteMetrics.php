<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One row per website: its newest metric snapshot.
 *
 * `website_metrics` is a time series, so every catalog query needs the latest
 * row per site. This is that subquery in one place, rather than repeated (and
 * quietly diverging) in the search, the ranges and the exports.
 */
final class LatestWebsiteMetrics
{
    public static function query(): Builder
    {
        return DB::table('website_metrics as m')
            ->select([
                'm.website_id',
                'm.monthly_traffic',
                'm.ahrefs_dr',
                'm.moz_da',
                'm.semrush_as',
                'm.spam_score',
                'm.referring_domains',
                'm.organic_keywords',
            ])
            ->whereRaw('m.fetched_at = (
                select max(m2.fetched_at) from website_metrics m2 where m2.website_id = m.website_id
            )');
    }
}
