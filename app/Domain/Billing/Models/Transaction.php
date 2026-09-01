<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the wallet ledger, written by Wallet's mutation methods.
 *
 * Append-only: rows are never updated or deleted, so there is no `updated_at`
 * and a correction is a new row. `amount_cents` is signed — a charge is
 * negative — while the two `*_after` columns record both buckets as they stood
 * immediately after the write, which is what makes the ledger reconstructable.
 */
class Transaction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'type',
        'amount_cents',
        'balance_after_cents',
        'frozen_after_cents',
        'reference_type',
        'reference_id',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'frozen_after_cents' => 'integer',
            'amount' => MoneyCast::class,
            'balance_after' => MoneyCast::class,
            'created_at' => 'datetime',
        ];
    }

    public function amount(): Money
    {
        return new Money($this->amount_cents);
    }

    public function balanceAfter(): Money
    {
        return new Money($this->balance_after_cents);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
