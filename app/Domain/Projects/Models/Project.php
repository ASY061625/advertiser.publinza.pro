<?php

declare(strict_types=1);

namespace App\Domain\Projects\Models;

use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\SensitiveTopic;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Search\Contracts\SearchableIndex;
use App\Models\User;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * A campaign: one advertiser site, its targeting, and the posts bought for it.
 *
 * The @property lines are load-bearing, not decoration. Without them static
 * analysis reads `status` as a plain string and cannot tell that comparing it
 * against the enum is meaningful — which is how three separate places came to
 * compare it against 'draft', a value ProjectStatus has never had, and fail
 * silently under a strict comparison.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $website_url
 * @property int|null $category_id
 * @property string|null $color
 * @property ProjectStatus $status
 * @property string|null $publisher_task
 */
class Project extends Model implements SearchableIndex
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'website_url',
        'category_id',
        'color',
        'status',
        'publisher_task',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ProjectStatus::class];
    }

    /**
     * What the global palette matches a project on.
     *
     * `user_id` is in here as a *filterable* attribute, not a searchable one.
     * Everything in this index belongs to somebody, and the search that reads
     * it filters on that column — without it one advertiser's palette would
     * return another's projects, which is the whole reason this model is not
     * simply searchable on its name.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'website_url' => $this->website_url,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<WebsiteCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WebsiteCategory::class, 'category_id');
    }

    /**
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'project_countries');
    }

    /**
     * @return BelongsToMany<Language, $this>
     */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'project_languages');
    }

    /**
     * @return BelongsToMany<SensitiveTopic, $this>
     */
    public function sensitiveTopics(): BelongsToMany
    {
        return $this->belongsToMany(SensitiveTopic::class, 'project_sensitive_topics');
    }

    /**
     * @return HasMany<ProjectFolder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(ProjectFolder::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<LandingPage, $this>
     */
    public function landingPages(): HasMany
    {
        return $this->hasMany(LandingPage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<Competitor, $this>
     */
    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }
}
