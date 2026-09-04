<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\MetricSource;
use Database\Factories\WebsiteMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time SEO snapshot. Rows accumulate rather than update, so a site's
 * trajectory stays readable.
 */
class WebsiteMetric extends Model
{
    /** @use HasFactory<WebsiteMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id',
        'monthly_traffic',
        'ahrefs_dr',
        'moz_da',
        'semrush_as',
        'spam_score',
        'referring_domains',
        'organic_keywords',
        'traffic_by_country',
        'source',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_traffic' => 'integer',
            'ahrefs_dr' => 'integer',
            'moz_da' => 'integer',
            'semrush_as' => 'integer',
            'spam_score' => 'integer',
            'referring_domains' => 'integer',
            'organic_keywords' => 'integer',
            'traffic_by_country' => 'array',
            'source' => MetricSource::class,
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
