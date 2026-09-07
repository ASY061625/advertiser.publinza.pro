<?php

declare(strict_types=1);

namespace App\Domain\System\Models;

use App\Domain\System\Enums\ChangelogType;
use Database\Factories\ChangelogEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A "what's new" note shown to advertisers.
 *
 * Authored in the admin panel; read-only everywhere on this side.
 *
 * @property ChangelogType $type
 * @property bool $is_major
 * @property Carbon|null $published_at
 * @property string|null $image_path
 */
class ChangelogEntry extends Model
{
    /** @use HasFactory<ChangelogEntryFactory> */
    use HasFactory;

    protected $table = 'changelog_entries';

    protected $fillable = ['title', 'slug', 'body', 'image_path', 'type', 'is_major', 'published_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChangelogType::class,
            'is_major' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * The image, served through the app rather than from the disk.
     *
     * Changelog images live on the private disk with everything else the app
     * stores, so this is a route, not a storage URL.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : "/whats-new/{$this->id}/image";
    }

    /**
     * The anchor the full page links each entry by.
     *
     * The slug, which is stable, rather than the id — a changelog URL somebody
     * pasted into Slack should survive the row being re-created.
     */
    public function anchor(): string
    {
        return "entry-{$this->slug}";
    }
}
