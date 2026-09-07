<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One sign-in attempt, successful or not. Feeds rate limiting and audit.
 *
 * @property bool $successful
 * @property string|null $ip_address
 * @property string|null $country
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['email', 'guard', 'ip_address', 'country', 'user_agent', 'successful'];

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
