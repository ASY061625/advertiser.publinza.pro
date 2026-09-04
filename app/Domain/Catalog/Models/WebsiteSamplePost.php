<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\WebsiteSamplePostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An example of the publisher's own work, curated by an admin.
 *
 * @property string $title
 * @property string $url
 * @property Carbon|null $published_at
 */
class WebsiteSamplePost extends Model
{
    /** @use HasFactory<WebsiteSamplePostFactory> */
    use HasFactory;

    protected $fillable = ['website_id', 'title', 'url', 'published_at', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published_at' => 'date', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    protected static function newFactory(): WebsiteSamplePostFactory
    {
        return WebsiteSamplePostFactory::new();
    }
}
