<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\DTOs\MessageData;
use App\Domain\Messaging\Models\Message;
use App\Domain\Messaging\Models\Thread;
use Illuminate\Support\Facades\DB;

final class PostMessage
{
    public function handle(Thread $thread, MessageData $data): Message
    {
        return DB::transaction(function () use ($thread, $data): Message {
            $message = Message::query()->create([
                'thread_id' => $thread->id,
                'author_type' => $data->authorType,
                'author_id' => $data->authorId,
                'body' => $data->body,
            ]);

            // Denormalised so thread lists sort without touching messages.
            $thread->update(['last_message_at' => $message->created_at]);

            return $message;
        });
    }
}
