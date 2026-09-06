<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Domain\Messaging\Enums\SenderType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $conversation_id
 * @property SenderType $sender_type
 * @property int|null $sender_id
 * @property string $body
 * @property string|null $client_token
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_type', 'sender_id', 'body', 'client_token', 'read_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sender_type' => SenderType::class,
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Everything but the advertiser's own writing is Publinza's side of it. */
    public function isFromTeam(): bool
    {
        return $this->sender_type !== SenderType::User;
    }

    /**
     * @return HasMany<MessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
