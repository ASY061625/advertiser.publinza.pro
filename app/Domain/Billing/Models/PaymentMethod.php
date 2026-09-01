<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tokenised card. Only the provider's reference and the display digits are
 * stored — a full card number never reaches this database.
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'provider', 'provider_reference', 'brand',
        'last_four', 'exp_month', 'exp_year', 'is_default',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['provider_reference'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'exp_month' => 'integer',
            'exp_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
