<?php

declare(strict_types=1);

namespace App\Domain\Trading\Models;

use App\Casts\MoneyCast;
use App\Domain\Catalog\Models\Website;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in the cart. `unit_price_cents` is snapshotted when the line is added,
 * so a publisher raising their price mid-session cannot change what the
 * advertiser was quoted.
 */
class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'website_id',
        'project_id',
        'folder_id',
        'service_type',
        'content_mode',
        'anchor_text',
        'target_url',
        'unit_price_cents',
        'addons',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'content_mode' => ContentMode::class,
            'unit_price_cents' => 'integer',
            'unit_price' => MoneyCast::class,
            'addons' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ProjectFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProjectFolder::class, 'folder_id');
    }
}
