<?php

declare(strict_types=1);

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Staff account for publinza.pro/asylogin. Deliberately a separate table and a
 * separate guard from advertisers — an advertiser record can never escalate
 * into the admin panel.
 *
 * @property string $role
 * @property string|null $two_factor_secret
 */
class Admin extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $table = 'admins';

    protected $fillable = ['name', 'email', 'password', 'role'];

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
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super';
    }
}
