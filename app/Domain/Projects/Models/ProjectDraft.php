<?php

declare(strict_types=1);

namespace App\Domain\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An unfinished create-project wizard.
 *
 * @property int $user_id
 * @property int $step
 * @property array<string, mixed> $payload
 */
class ProjectDraft extends Model
{
    protected $fillable = ['user_id', 'step', 'payload'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'step' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
