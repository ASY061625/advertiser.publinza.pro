<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Every amount in this codebase is an integer in minor units. No floats touch
 * money at any point.
 *
 * @property int $id
 * @property int $balance_minor_units
 * @property int $frozen_minor_units
 */
class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'balance_minor_units', 'frozen_minor_units', 'currency'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_minor_units' => 'integer',
            'frozen_minor_units' => 'integer',
        ];
    }

    /** Frozen funds are committed to open orders and cannot be spent again. */
    public function availableMinorUnits(): int
    {
        return $this->balance_minor_units - $this->frozen_minor_units;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
