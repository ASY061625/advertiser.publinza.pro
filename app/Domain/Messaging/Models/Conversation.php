<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\Enums\ConversationStatus;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A thread. It may hang off a website, a specific post, or neither — general
 * support has both nullable.
 *
 * Every thread is advertiser ↔ Publinza; there is no third party, because
 * Publinza owns every site in the catalog. What varies is the subject.
 *
 * @property int $user_id
 * @property int|null $website_id
 * @property int|null $post_id
 * @property string $subject
 * @property ConversationStatus $status
 * @property Carbon|null $last_message_at
 * @property Carbon|null $muted_at
 */
class Conversation extends Model
{
    protected $fillable = ['user_id', 'website_id', 'post_id', 'subject', 'last_message_at', 'status', 'muted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'muted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
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

    public function isMuted(): bool
    {
        return $this->muted_at !== null;
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }
}
