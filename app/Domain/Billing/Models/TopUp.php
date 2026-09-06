<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\DeclineReason;
use App\Domain\Billing\Enums\TopUpMethod;
use App\Domain\Billing\Enums\TopUpStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One attempt to add funds, successful or not.
 *
 * Separate from the ledger on purpose: `transactions` records money that moved,
 * and a declined card moved none. Keeping the attempt here means the decline
 * reason survives to be shown, and a bank transfer has somewhere to sit while
 * it is still a promise rather than a payment.
 *
 * @property TopUpMethod $method
 * @property TopUpStatus $status
 * @property int $amount_cents
 * @property int $bonus_cents
 * @property int $fee_cents
 * @property string $reference
 * @property DeclineReason|null $decline_code
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 */
class TopUp extends Model
{
    protected $fillable = [
        'user_id', 'payment_method_id', 'method', 'amount_cents', 'bonus_cents',
        'fee_cents', 'status', 'reference', 'provider_reference', 'decline_code', 'confirmed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => TopUpMethod::class,
            'status' => TopUpStatus::class,
            'decline_code' => DeclineReason::class,
            'amount_cents' => 'integer',
            'bonus_cents' => 'integer',
            'fee_cents' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * The code a bank transfer must quote.
     *
     * Digits only, so there is no O/0 or I/1 to get wrong: this gets read down
     * a phone and typed into a bank form by somebody who is not being careful,
     * and a reference nobody can transcribe is a payment nobody can match.
     *
     * Checked against the table rather than trusted to chance — the column is
     * unique, and an unhandled collision would fail somebody's payment for a
     * reason that has nothing to do with them.
     */
    public static function newReference(): string
    {
        do {
            $reference = 'PZ-'.Str::password(8, letters: false, numbers: true, symbols: false, spaces: false);
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function amount(): Money
    {
        return new Money($this->amount_cents);
    }

    /** What actually reaches the balance: the payment plus any credit on top. */
    public function credited(): Money
    {
        return new Money($this->amount_cents + $this->bonus_cents);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
