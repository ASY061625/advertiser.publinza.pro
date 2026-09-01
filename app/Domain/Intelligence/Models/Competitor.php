<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Models;

use App\Domain\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A rival domain an advertiser tracks against one of their projects. */
class Competitor extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'domain', 'label', 'added_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['added_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<CompetitorMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(CompetitorMetric::class);
    }

    /**
     * @return HasOne<CompetitorMetric, $this>
     */
    public function latestMetric(): HasOne
    {
        return $this->hasOne(CompetitorMetric::class)->latestOfMany('fetched_at');
    }
}
