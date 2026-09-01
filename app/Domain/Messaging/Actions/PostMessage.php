<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\DTOs\MessageData;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use Illuminate\Support\Facades\DB;

final class PostMessage
{
    public function handle(Conversation $conversation, MessageData $data): Message
    {
        return DB::transaction(function () use ($conversation, $data): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => $data->senderType,
                'sender_id' => $data->senderId,
                'body' => $data->body,
            ]);

            // Denormalised so inbox lists sort without touching messages.
            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });
    }
}
