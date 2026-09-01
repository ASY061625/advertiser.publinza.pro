<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\TransactionType;
use App\Exceptions\InsufficientFunds;
use App\Models\User;
use Closure;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * An advertiser's balance, split into two buckets.
 *
 * `available` is spendable. `frozen` is committed to open orders and cannot be
 * spent twice. Money moves between them and in and out of the wallet only
 * through the five methods below.
 *
 * Every one of those methods takes a `SELECT ... FOR UPDATE` row lock inside a
 * transaction, so two requests racing to spend the same balance serialise
 * behind the lock instead of both reading the pre-spend figure. Both columns
 * are UNSIGNED in the schema, so if the arithmetic were ever wrong the database
 * rejects the write rather than storing a negative balance.
 *
 * @property int $available_cents
 * @property int $frozen_cents
 */
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'available_cents', 'frozen_cents', 'currency'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_cents' => 'integer',
            'frozen_cents' => 'integer',
            'available' => MoneyCast::class,
            'frozen' => MoneyCast::class,
        ];
    }

    // ---------------------------------------------------------------- reads

    public function available(): Money
    {
        return new Money($this->available_cents, $this->currency);
    }

    public function frozen(): Money
    {
        return new Money($this->frozen_cents, $this->currency);
    }

    /** Everything the advertiser holds, spendable or not. */
    public function total(): Money
    {
        return new Money($this->available_cents + $this->frozen_cents, $this->currency);
    }

    // ------------------------------------------------------------ mutations

    /** Adds funds to the spendable balance. */
    public function deposit(Money $amount, ?Model $reference = null, ?string $description = null): Transaction
    {
        return $this->mutate(
            TransactionType::Deposit,
            $amount,
            static function (self $wallet) use ($amount): int {
                $wallet->available_cents += $amount->cents;

                return $amount->cents;
            },
            $reference,
            $description,
        );
    }

    /**
     * Commits funds to an order: available → frozen.
     *
     * This is the method that must not overdraw. The balance check runs against
     * the row read under the lock, never against the in-memory copy the caller
     * happened to load earlier.
     */
    public function freeze(Money $amount, ?Model $reference = null, ?string $description = null): Transaction
    {
        return $this->mutate(
            TransactionType::Freeze,
            $amount,
            static function (self $wallet) use ($amount): int {
                if ($wallet->available_cents < $amount->cents) {
                    throw new InsufficientFunds($amount, $wallet->available(), 'available');
                }

                $wallet->available_cents -= $amount->cents;
                $wallet->frozen_cents += $amount->cents;

                // Signed for the ledger: the spendable balance went down.
                return -$amount->cents;
            },
            $reference,
            $description,
        );
    }

    /** Releases a commitment back to spendable: frozen → available. */
    public function unfreeze(Money $amount, ?Model $reference = null, ?string $description = null): Transaction
    {
        return $this->mutate(
            TransactionType::Unfreeze,
            $amount,
            static function (self $wallet) use ($amount): int {
                if ($wallet->frozen_cents < $amount->cents) {
                    throw new InsufficientFunds($amount, $wallet->frozen(), 'frozen');
                }

                $wallet->frozen_cents -= $amount->cents;
                $wallet->available_cents += $amount->cents;

                return $amount->cents;
            },
            $reference,
            $description,
        );
    }

    /**
     * Takes frozen funds out of the wallet for good — the post completed and
     * the money became platform revenue.
     */
    public function charge(Money $amount, ?Model $reference = null, ?string $description = null): Transaction
    {
        return $this->mutate(
            TransactionType::Charge,
            $amount,
            static function (self $wallet) use ($amount): int {
                if ($wallet->frozen_cents < $amount->cents) {
                    throw new InsufficientFunds($amount, $wallet->frozen(), 'frozen');
                }

                $wallet->frozen_cents -= $amount->cents;

                return -$amount->cents;
            },
            $reference,
            $description,
        );
    }

    /** Returns money to the spendable balance after a cancellation or rejection. */
    public function refund(Money $amount, ?Model $reference = null, ?string $description = null): Transaction
    {
        return $this->mutate(
            TransactionType::Refund,
            $amount,
            static function (self $wallet) use ($amount): int {
                $wallet->available_cents += $amount->cents;

                return $amount->cents;
            },
            $reference,
            $description,
        );
    }

    // ------------------------------------------------------------- internals

    /**
     * Runs one balance change under a row lock and records it in the ledger.
     *
     * @param  Closure(self): int  $apply  Mutates the locked wallet and returns
     *                                     the signed amount for the ledger row.
     */
    private function mutate(
        TransactionType $type,
        Money $amount,
        Closure $apply,
        ?Model $reference,
        ?string $description,
    ): Transaction {
        if ($amount->cents <= 0) {
            throw new InvalidArgumentException(
                sprintf('A %s must be a positive amount, %s given.', $type->value, $amount->format()),
            );
        }

        if ($amount->currency !== $this->currency) {
            throw new InvalidArgumentException(
                sprintf('Cannot %s %s into a %s wallet.', $type->value, $amount->currency, $this->currency),
            );
        }

        return DB::transaction(function () use ($type, $apply, $reference, $description): Transaction {
            // SELECT ... FOR UPDATE. Everything below reads the locked row, so a
            // concurrent caller blocks here rather than racing the balance check.
            $locked = static::query()->lockForUpdate()->find($this->getKey());

            if ($locked === null) {
                throw new RuntimeException("Wallet #{$this->getKey()} disappeared before it could be locked.");
            }

            $signedAmount = $apply($locked);
            $locked->save();

            $transaction = Transaction::query()->create([
                'wallet_id' => $locked->getKey(),
                'type' => $type,
                'amount_cents' => $signedAmount,
                'balance_after_cents' => $locked->available_cents,
                'frozen_after_cents' => $locked->frozen_cents,
                'reference_type' => $reference === null ? null : $reference->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
            ]);

            // Keep the caller's copy honest with what was actually written.
            $this->forceFill([
                'available_cents' => $locked->available_cents,
                'frozen_cents' => $locked->frozen_cents,
            ])->syncOriginal();

            return $transaction;
        });
    }

    // ---------------------------------------------------------- relationships

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
        return $this->hasMany(Transaction::class)->latest('created_at');
    }

    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }
}
