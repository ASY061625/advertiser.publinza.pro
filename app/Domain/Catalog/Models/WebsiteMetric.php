<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\MetricSource;
use Database\Factories\WebsiteMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A point-in-time SEO snapshot. Rows accumulate rather than update, so a site's
 * trajectory stays readable.
 *
 * traffic_by_country is annotated as loosely as it really is: it is whatever
 * the provider wrote, decoded by the array cast, not a shape this application
 * controls — so it is validated where it is read rather than promised here.
 *
 * @property int $monthly_traffic
 * @property int $ahrefs_dr
 * @property int $moz_da
 * @property int $semrush_as
 * @property int $spam_score
 * @property int $referring_domains
 * @property int $organic_keywords
 * @property int $traffic_value_cents
 * @property int $indexed_pages
 * @property array<array-key, mixed>|null $traffic_by_country
 * @property MetricSource $source
 * @property Carbon|null $fetched_at
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
        'traffic_value_cents',
        'indexed_pages',
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
            'traffic_value_cents' => 'integer',
            'indexed_pages' => 'integer',
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
