<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * A publisher site an advertiser can buy a placement on.
 *
 * @property int $id
 * @property string $domain
 * @property int $price_minor_units
 * @property int $traffic
 * @property int $domain_rating
 * @property int $domain_authority
 * @property int $spam_score
 */
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    use Searchable;

    protected $fillable = [
        'domain',
        'language',
        'category',
        'price_minor_units',
        'traffic',
        'domain_rating',
        'domain_authority',
        'spam_score',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor_units' => 'integer',
            'traffic' => 'integer',
            'domain_rating' => 'integer',
            'domain_authority' => 'integer',
            'spam_score' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Only approved sites are searchable — the catalog is the advertiser's
     * view, and pending sites live in the admin panel.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'language' => $this->language,
            'category' => $this->category,
            'price_minor_units' => $this->price_minor_units,
            'traffic' => $this->traffic,
            'domain_rating' => $this->domain_rating,
            'domain_authority' => $this->domain_authority,
            'spam_score' => $this->spam_score,
        ];
    }

    protected static function newFactory(): SiteFactory
    {
        return SiteFactory::new();
    }
}
