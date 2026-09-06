<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;

/**
 * Puts a thread back in the unread pile.
 *
 * Unread is derived — a thread is unread when it holds an unread inbound
 * message — so this clears `read_at` on the most recent one rather than setting
 * a flag beside it. Two sources of truth for "unread" is how an inbox ends up
 * with a badge that says 3 over a list showing nothing new.
 *
 * The *most recent* one only, not all of them: "mark unread" means "I have not
 * dealt with this", not "forget that I read the other eleven".
 */
final class MarkThreadUnread
{
    public function handle(Conversation $conversation): bool
    {
        $latest = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_type', '!=', SenderType::User->value)
            ->latest('created_at')
            ->latest('id')
            ->first();

        // A thread the team has never written in cannot be unread. Silently
        // doing nothing is right here: the menu item is offered on every
        // thread, and hiding it on this one would be more confusing than a
        // no-op.
        if ($latest === null) {
            return false;
        }

        $latest->update(['read_at' => null]);

        return true;
    }
}
