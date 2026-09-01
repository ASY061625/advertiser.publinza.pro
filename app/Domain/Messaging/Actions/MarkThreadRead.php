<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;

final class MarkThreadRead
{
    /** Marks everything the given side did not write as read. */
    public function handle(Conversation $conversation, SenderType $reader): int
    {
        return $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_type', '!=', $reader->value)
            ->update(['read_at' => now()]);
    }
}
