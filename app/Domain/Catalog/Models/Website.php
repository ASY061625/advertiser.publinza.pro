<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\LinkType;
use App\Domain\Trading\Enums\ServiceType;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * A publisher site an advertiser can buy a placement on.
 *
 * @property bool $is_active
 * @property LinkType $link_type
 * @property array<int, string>|null $accepts_sensitive_topics
 * @property int $publication_period_hours
 */
class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory;

    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'domain',
        'slug',
        'title',
        'description',
        'category_id',
        'primary_language_id',
        'country_id',
        'is_active',
        'is_featured',
        'accepts_sensitive_topics',
        'publication_period_hours',
        'link_type',
        'links_allowed',
        'max_links',
        'min_words',
        'sample_url',
        'guidelines',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'accepts_sensitive_topics' => 'array',
            'link_type' => LinkType::class,
            'publication_period_hours' => 'integer',
            'links_allowed' => 'integer',
            'max_links' => 'integer',
            'min_words' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------------------------------------------------------------- scopes

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Hides sites this advertiser has blacklisted.
     *
     * @param  Builder<self>  $query
     */
    public function scopeNotBlacklistedBy(Builder $query, int $userId): void
    {
        $query->whereNotExists(function ($sub) use ($userId): void {
            $sub->selectRaw('1')
                ->from('blacklists')
                ->whereColumn('blacklists.website_id', 'websites.id')
                ->where('blacklists.user_id', $userId);
        });
    }

    public function acceptsTopic(string $slug): bool
    {
        return in_array($slug, $this->accepts_sensitive_topics ?? [], true);
    }

    /** The price for one service, or null if the publisher does not offer it. */
    public function priceFor(ServiceType $service): ?WebsitePrice
    {
        return $this->prices->firstWhere('service_type', $service);
    }

    // ----------------------------------------------------------- searchable

    /**
     * Eager-loads what toSearchableArray needs when Scout indexes in bulk, so
     * a full reindex is a handful of queries rather than three per site.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['latestMetric', 'prices']);
    }

    /** Only approved, active sites belong in the advertiser-facing index. */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // Load rather than assume. Scout calls this on models it fetched
        // itself — during indexing, and on every search under the collection
        // driver — so the relations are not eager-loaded unless we say so.
        // loadMissing is a no-op when they already are.
        $this->loadMissing(['latestMetric', 'prices']);

        $metric = $this->latestMetric;
        $price = $this->prices->first();

        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'primary_language_id' => $this->primary_language_id,
            'country_id' => $this->country_id,
            'price_cents' => $price?->price_cents ?? 0,
            'monthly_traffic' => $metric?->monthly_traffic ?? 0,
            'ahrefs_dr' => $metric?->ahrefs_dr ?? 0,
            'moz_da' => $metric?->moz_da ?? 0,
            'spam_score' => $metric?->spam_score ?? 0,
        ];
    }

    // ---------------------------------------------------------- relationships

    /**
     * @return BelongsTo<WebsiteCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WebsiteCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function primaryLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'primary_language_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<WebsitePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(WebsitePrice::class);
    }

    /**
     * @return HasMany<WebsiteMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(WebsiteMetric::class);
    }

    /**
     * The newest metric snapshot — what the catalog table renders.
     *
     * @return HasOne<WebsiteMetric, $this>
     */
    public function latestMetric(): HasOne
    {
        return $this->hasOne(WebsiteMetric::class)->latestOfMany('fetched_at');
    }

    protected static function newFactory(): WebsiteFactory
    {
        return WebsiteFactory::new();
    }
}
