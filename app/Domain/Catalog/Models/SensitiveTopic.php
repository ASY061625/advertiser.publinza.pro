<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Projects\Models\Project;
use Database\Factories\SensitiveTopicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A topic publishers opt into rather than accept by default — gambling, CBD,
 * adult and so on. A website stores the slugs it accepts as JSON; a project
 * stores the ones it needs as a pivot.
 */
class SensitiveTopic extends Model
{
    /** @use HasFactory<SensitiveTopicFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_sensitive_topics');
    }
}
