<?php

declare(strict_types=1);

namespace App\Domain\Projects\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groups landing pages within a project, optionally overriding the project's
 * default writer instructions.
 */
class ProjectFolder extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'name', 'publisher_task', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** The folder's instructions if it has any, otherwise the project's. */
    public function effectivePublisherTask(): ?string
    {
        return $this->publisher_task ?? $this->project->publisher_task;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<LandingPage, $this>
     */
    public function landingPages(): HasMany
    {
        return $this->hasMany(LandingPage::class, 'folder_id')->orderBy('sort_order');
    }
}
