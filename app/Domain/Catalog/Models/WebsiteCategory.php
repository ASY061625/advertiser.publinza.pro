<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One of the fourteen catalog categories. */
class WebsiteCategory extends Model
{
    use HasFactory;

    protected $table = 'website_categories';

    protected $fillable = ['name', 'slug', 'description', 'sort_order'];

    /**
     * @return HasMany<Website, $this>
     */
    public function websites(): HasMany
    {
        return $this->hasMany(Website::class, 'category_id');
    }
}
