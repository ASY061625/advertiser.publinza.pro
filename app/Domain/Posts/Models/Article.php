<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A submitted draft. Revisions are versioned rather than overwritten, so a
 * rejected draft stays readable next to the one that replaced it.
 */
class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'title',
        'body_html',
        'word_count',
        'file_path',
        'version',
        'submitted_by',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'word_count' => 'integer',
            'version' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
