<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Domain\Posts\Models\Post;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One conversation, always anchored to a post so support can see the context.
 */
class Thread extends Model
{
    use HasFactory;

    protected $fillable = ['post_id', 'subject', 'last_message_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }
}
