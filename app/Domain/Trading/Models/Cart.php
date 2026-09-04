<?php

declare(strict_types=1);

namespace App\Domain\Trading\Models;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\PromoCode;
use App\Models\User;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One open cart per advertiser, persisted so it survives a session. */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'promo_code_id'];

    public function subtotal(): Money
    {
        return new Money((int) $this->items->sum('unit_price_cents'));
    }

    /**
     * @return BelongsTo<PromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
