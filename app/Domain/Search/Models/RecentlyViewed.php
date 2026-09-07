<?php

declare(strict_types=1);

namespace App\Domain\Search\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The last thing somebody looked at.
 *
 * @property string $viewable_type
 * @property int $viewable_id
 * @property Carbon $viewed_at
 */
class RecentlyViewed extends Model
{
    /** Two columns and a timestamp of its own; created_at/updated_at would be noise. */
    public $timestamps = false;

    protected $table = 'recently_viewed';

    protected $fillable = ['user_id', 'viewable_type', 'viewable_id', 'viewed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
