<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person, one notification type, and the email window for it.
 *
 * @property NotificationType $type
 * @property Carbon|null $last_sent_at
 * @property int $pending_count
 * @property list<string>|null $pending_ids
 */
class NotificationDigest extends Model
{
    protected $fillable = ['user_id', 'type', 'last_sent_at', 'pending_count', 'pending_ids'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'last_sent_at' => 'datetime',
            'pending_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
