<?php

declare(strict_types=1);

namespace App\Domain\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A queued export; the file lands on the private disk and the row tracks it.
 *
 * The casts are declared again as properties because static analysis reads the
 * docblock, not casts() — without them `completed_at` is a string and every
 * date comparison on it looks like a bug.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $type
 * @property array<string, mixed>|null $filters
 * @property string $status
 * @property string|null $file_path
 * @property int|null $row_count
 * @property string|null $error
 * @property Carbon|null $completed_at
 */
class ExportJob extends Model
{
    protected $fillable = [
        'user_id', 'type', 'filters', 'status', 'file_path', 'row_count', 'error', 'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'row_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
