<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Domain\Billing\DTOs\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Either a percentage or a flat amount off, never both.
 */
class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'description', 'percent_off', 'amount_off_cents', 'minimum_spend_cents',
        'max_redemptions', 'redemptions_count', 'starts_at', 'ends_at', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_off' => 'integer',
            'amount_off_cents' => 'integer',
            'minimum_spend_cents' => 'integer',
            'amount_off' => MoneyCast::class,
            'minimum_spend' => MoneyCast::class,
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isRedeemableNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return $this->max_redemptions === null || $this->redemptions_count < $this->max_redemptions;
    }

    /** The discount this code takes off a given subtotal, never more than it. */
    public function discountFor(Money $subtotal): Money
    {
        if ($subtotal->cents < $this->minimum_spend_cents) {
            return Money::zero($subtotal->currency);
        }

        $discount = $this->percent_off !== null
            ? intdiv($subtotal->cents * $this->percent_off, 100)
            : (int) $this->amount_off_cents;

        return new Money(min($discount, $subtotal->cents), $subtotal->currency);
    }

    /**
     * @return HasMany<PromoRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }
}
