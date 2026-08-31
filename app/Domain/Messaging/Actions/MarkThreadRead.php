<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\Thread;

final class MarkThreadRead
{
    /** Marks everything the given side did not write as read. */
    public function handle(Thread $thread, string $readerType): int
    {
        return $thread->messages()
            ->whereNull('read_at')
            ->where('author_type', '!=', $readerType)
            ->update(['read_at' => now()]);
    }
}
