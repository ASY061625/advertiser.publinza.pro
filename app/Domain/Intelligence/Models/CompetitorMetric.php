<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'competitor_id', 'organic_traffic', 'organic_keywords', 'dr', 'da',
        'referring_domains', 'backlinks', 'top_keywords', 'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organic_traffic' => 'integer',
            'organic_keywords' => 'integer',
            'dr' => 'integer',
            'da' => 'integer',
            'referring_domains' => 'integer',
            'backlinks' => 'integer',
            'top_keywords' => 'array',
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
}
