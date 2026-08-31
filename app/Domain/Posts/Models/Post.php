<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Domain\Catalog\Models\Site;
use App\Domain\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered placement: a project's content going live on one site.
 *
 * @property int $id
 * @property string $status
 */
class Post extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'site_id', 'status', 'draft_url', 'published_url', 'published_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
