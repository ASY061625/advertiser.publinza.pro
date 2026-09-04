<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Models;

use Database\Factories\CompetitorMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One provider's answer about one domain, at one moment.
 *
 * Rows accumulate rather than update, so a refresh that returns worse numbers
 * cannot erase the better ones it replaced, and the twelve-month line has
 * something to be checked against.
 *
 * @property int $organic_traffic
 * @property int $organic_keywords
 * @property int|null $dr
 * @property int|null $da
 * @property int $referring_domains
 * @property int $backlinks
 * @property int $traffic_value_cents
 * @property string $provider
 *                            The three JSON columns are typed as loosely as they really are. What comes
 *                            back is whatever json_decode made of the bytes in the row — written by a
 *                            provider mapping that can change, or by an older version of one — so the code
 *                            reading them checks their shape, and a docblock promising a shape would only
 *                            make those checks look redundant.
 * @property array<int, mixed>|null $traffic_history
 * @property int|null $shared_keywords
 * @property array<int, mixed>|null $gap_keywords
 * @property array<array-key, mixed>|null $link_categories
 * @property Carbon|null $fetched_at
 */
class CompetitorMetric extends Model
{
    /** @use HasFactory<CompetitorMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'competitor_id', 'organic_traffic', 'organic_keywords', 'dr', 'da',
        'referring_domains', 'backlinks', 'top_keywords', 'fetched_at',
        'traffic_value_cents', 'provider', 'traffic_history', 'shared_keywords',
        'gap_keywords', 'link_categories',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organic_traffic' => 'integer',
            'organic_keywords' => 'integer',
            // Nullable: no provider sells both scores, and a site nobody
            // measured must not be printed with the worst possible one.
            'dr' => 'integer',
            'da' => 'integer',
            'referring_domains' => 'integer',
            'backlinks' => 'integer',
            'top_keywords' => 'array',
            'traffic_value_cents' => 'integer',
            'traffic_history' => 'array',
            'shared_keywords' => 'integer',
            'gap_keywords' => 'array',
            'link_categories' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Competitor, $this>
     */
    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    protected static function newFactory(): CompetitorMetricFactory
    {
        return CompetitorMetricFactory::new();
    }
}
