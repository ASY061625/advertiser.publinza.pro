<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\NotificationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per event per person, written only when they change something.
 *
 * @property NotificationEvent $event
 * @property bool $email
 * @property bool $in_app
 * @property bool $push
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'event', 'email', 'in_app', 'push'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'email' => 'boolean',
            'in_app' => 'boolean',
            'push' => 'boolean',
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
