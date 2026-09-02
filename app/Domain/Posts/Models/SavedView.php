<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named filter preset for one data grid.
 *
 * The stored `query` is exactly what PostFilters::toQuery() produces, which is
 * exactly what the URL carries — so a saved view is a URL with a name on it,
 * and restoring one cannot drift from what sharing the link would have done.
 *
 * @property array<string, mixed> $query
 */
class SavedView extends Model
{
    protected $fillable = ['user_id', 'surface', 'name', 'query'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['query' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
