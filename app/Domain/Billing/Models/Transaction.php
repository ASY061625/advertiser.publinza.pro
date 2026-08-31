<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only ledger line. Rows are never updated or deleted; a correction
 * is a new row.
 *
 * @property int $amount_minor_units
 */
class Transaction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['wallet_id', 'type', 'amount_minor_units', 'reference_type', 'reference_id', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount_minor_units' => 'integer'];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
