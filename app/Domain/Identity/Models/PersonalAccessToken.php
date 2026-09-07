<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A bearer credential somebody pastes into a script.
 *
 * The plaintext is returned exactly once, by the request that created it, and
 * only its SHA-256 hash is stored. Not bcrypt: a token arrives on every API
 * request and has to be found by a single indexed lookup, and unlike a password
 * it is 40 bytes of entropy rather than something a person chose — so there is
 * nothing for a slow hash to defend against.
 *
 * @property list<string> $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 */
class PersonalAccessToken extends Model
{
    /** Prefixed so a leaked token is recognisable in a log or a repository. */
    public const PREFIX = 'pzt_';

    protected $fillable = ['user_id', 'name', 'token', 'abilities', 'last_used_at', 'expires_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return array{plain: string, hash: string} */
    public static function mint(): array
    {
        $plain = self::PREFIX.Str::random(48);

        return ['plain' => $plain, 'hash' => hash('sha256', $plain)];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function can(TokenAbility $ability): bool
    {
        return ! $this->isExpired() && in_array($ability->value, $this->abilities, true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
