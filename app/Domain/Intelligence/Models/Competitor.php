<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Models;

use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Projects\Models\Project;
use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A rival domain an advertiser tracks against one of their projects.
 *
 * The project's own promoted site is a row here too, flagged `is_self`. It
 * carries the same columns and is filled by the same provider on the same
 * schedule, because every figure on the tab is a comparison against it and a
 * comparison between two differently-sourced numbers measures the sources.
 *
 * @property bool $is_self
 * @property string $domain
 * @property string|null $label
 * @property Carbon|null $refreshed_at
 * @property FetchState $fetch_state
 * @property string|null $fetch_error
 * @property CompetitorMetric|null $latestMetric
 */
class Competitor extends Model
{
    /** @use HasFactory<CompetitorFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id', 'is_self', 'domain', 'label', 'added_at',
        'refreshed_at', 'fetch_state', 'fetch_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_self' => 'boolean',
            'added_at' => 'datetime',
            'refreshed_at' => 'datetime',
            'fetch_state' => FetchState::class,
        ];
    }

    /**
     * How long until this row may be refreshed by hand again, in seconds.
     *
     * Zero means now. Measured from `refreshed_at`, which only a person's
     * refresh sets — a scheduled refill of a stale row is not something the
     * advertiser asked for and does not spend their allowance.
     */
    public function cooldownSeconds(): int
    {
        if ($this->refreshed_at === null) {
            return 0;
        }

        $ready = $this->refreshed_at->addHours((int) config('publinza.competitors.refresh_cooldown_hours', 24));

        // Carbon returns a float. The column is a countdown in whole seconds
        // and the payload is typed as an int, so the cast belongs here rather
        // than at each of the places that read it.
        return (int) max(0, now()->diffInSeconds($ready, false));
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

    protected static function newFactory(): CompetitorFactory
    {
        return CompetitorFactory::new();
    }
}
