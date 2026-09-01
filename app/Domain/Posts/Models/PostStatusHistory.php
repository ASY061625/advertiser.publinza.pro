<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Domain\Posts\Enums\ActorType;
use App\Domain\Posts\Enums\PostStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per status change, written by PostObserver. Append-only: history is
 * never updated or deleted, so `updated_at` does not exist.
 */
class PostStatusHistory extends Model
{
    protected $table = 'post_status_history';

    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => PostStatus::class,
            'to_status' => PostStatus::class,
            'actor_type' => ActorType::class,
            'created_at' => 'datetime',
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
