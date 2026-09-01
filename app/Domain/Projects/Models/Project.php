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
use App\Models\User;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A campaign: one advertiser site, its targeting, and the posts bought for it.
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'website_url',
        'category_id',
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
