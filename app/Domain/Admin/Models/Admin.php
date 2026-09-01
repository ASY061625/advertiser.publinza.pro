<?php

declare(strict_types=1);

namespace App\Domain\Admin\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Staff account for publinza.pro/asylogin.
 *
 * Deliberately a separate table and guard from advertisers: an advertiser
 * record can never escalate into the admin panel because it is not in here.
 *
 * @property string|null $two_factor_secret
 */
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    use Notifiable;

    protected $table = 'admins';

    protected $fillable = ['email', 'password', 'name', 'role_id', 'status'];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null;
    }

    public function isOwner(): bool
    {
        return $this->role?->name === 'owner';
    }

    public function can($abilities, $arguments = []): bool
    {
        // The owner role bypasses the permission table entirely.
        if ($this->isOwner()) {
            return true;
        }

        return $this->role?->hasPermission((string) $abilities) ?? false;
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    protected static function newFactory(): AdminFactory
    {
        return AdminFactory::new();
    }
}
