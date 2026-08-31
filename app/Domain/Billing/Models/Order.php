<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A checkout: one or more posts bought in a single action, with the total
 * frozen against the advertiser's wallet.
 *
 * @property int $total_minor_units
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'total_minor_units', 'status', 'currency'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['total_minor_units' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
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
