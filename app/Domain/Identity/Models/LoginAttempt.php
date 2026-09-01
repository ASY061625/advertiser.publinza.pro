<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;

/** One sign-in attempt, successful or not. Feeds rate limiting and audit. */
class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['email', 'guard', 'ip_address', 'user_agent', 'successful'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
