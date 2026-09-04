<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Casts\MoneyCast;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use Database\Factories\WebsitePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ServiceType $service_type
 * @property int $price_cents
 * @property int $writing_fee_cents
 */
class WebsitePrice extends Model
{
    /** @use HasFactory<WebsitePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'website_id',
        'service_type',
        'price_cents',
        'writing_fee_cents',
        'express_fee_cents',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'price_cents' => 'integer',
            'writing_fee_cents' => 'integer',
            'express_fee_cents' => 'integer',
            'price' => MoneyCast::class,
            'writing_fee' => MoneyCast::class,
            'express_fee' => MoneyCast::class,
        ];
    }

    /** What the advertiser is quoted, before any express upgrade. */
    public function totalFor(ContentMode $mode): Money
    {
        $total = $this->price_cents;

        if ($mode->incursWritingFee()) {
            $total += $this->writing_fee_cents;
        }

        return new Money($total);
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
