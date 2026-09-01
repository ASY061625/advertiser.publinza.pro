<?php

declare(strict_types=1);

namespace App\Domain\System\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A marketing blog article. Written by our own editors in the admin panel, so
 * `body_html` is trusted and rendered unescaped — nothing user-submitted is
 * ever stored here.
 */
class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'slug', 'excerpt', 'body_html', 'author', 'reading_minutes', 'published_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reading_minutes' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
