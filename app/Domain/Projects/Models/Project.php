<?php

declare(strict_types=1);

namespace App\Domain\Projects\Models;

use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A campaign an advertiser groups posts under.
 *
 * @property int $id
 * @property string $status
 */
class Project extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'target_url', 'anchor_text', 'brief', 'status'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
