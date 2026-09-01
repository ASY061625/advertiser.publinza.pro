<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRedemption extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['promo_code_id', 'user_id', 'order_id', 'discount_cents'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_cents' => 'integer',
            'discount' => MoneyCast::class,
            'created_at' => 'datetime',
        ];
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
