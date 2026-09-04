<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Casts\MoneyCast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $billing_details
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'order_id', 'number', 'subtotal_cents', 'tax_cents', 'total_cents',
        'currency', 'status', 'billing_details', 'pdf_path', 'issued_at', 'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'subtotal' => MoneyCast::class,
            'tax' => MoneyCast::class,
            'total' => MoneyCast::class,
            'billing_details' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
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
