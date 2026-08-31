<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $author_type  'user' or 'admin' — the two sides of a thread.
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = ['thread_id', 'author_type', 'author_id', 'body', 'read_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
