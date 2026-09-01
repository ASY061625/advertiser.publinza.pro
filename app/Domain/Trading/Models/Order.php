<?php

declare(strict_types=1);

namespace App\Domain\Trading\Models;

use App\Casts\MoneyCast;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Enums\OrderStatus;
use App\Domain\Trading\Enums\PaidFrom;
use App\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A checkout. Paying an order freezes its total against the advertiser's wallet
 * and creates one post per line.
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal_cents',
        'discount_cents',
        'total_cents',
        'currency',
        'status',
        'paid_from',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'subtotal' => MoneyCast::class,
            'discount' => MoneyCast::class,
            'total' => MoneyCast::class,
            'status' => OrderStatus::class,
            'paid_from' => PaidFrom::class,
            'paid_at' => 'datetime',
        ];
    }

    public function total(): Money
    {
        return new Money($this->total_cents, $this->currency);
    }

    /** Sequential-looking but not guessable in bulk: PZ-YYYYMM-XXXXXX. */
    public static function generateNumber(): string
    {
        return sprintf('PZ-%s-%s', now()->format('Ym'), str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
