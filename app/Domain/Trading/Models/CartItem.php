<?php

declare(strict_types=1);

namespace App\Domain\Trading\Models;

use App\Casts\MoneyCast;
use App\Domain\Catalog\Models\Website;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in the cart.
 *
 * `unit_price_cents` is what this line was quoted when it was added. It is not
 * what gets charged: the live price wins, at checkout and on the screen, and
 * the snapshot exists so the cart can say "this was $180 when you added it"
 * rather than silently changing the number. See CartPricer, which is the only
 * place either figure is turned into money.
 *
 * @property ServiceType $service_type
 * @property ContentMode $content_mode
 * @property int $unit_price_cents
 * @property bool $express
 * @property array<int, string>|null $dismissed_warnings
 * @property int|null $article_word_count
 */
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
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
        'express',
        'addons',
        'dismissed_warnings',
        'article_title',
        'article_body_html',
        'article_word_count',
        'article_file_path',
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
            'express' => 'boolean',
            'addons' => 'array',
            'dismissed_warnings' => 'array',
            'article_word_count' => 'integer',
        ];
    }

    /** Whether the advertiser has already staged an article for this line. */
    public function hasArticle(): bool
    {
        return $this->article_word_count !== null;
    }

    public function hasDismissed(string $kind): bool
    {
        return in_array($kind, $this->dismissed_warnings ?? [], true);
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
