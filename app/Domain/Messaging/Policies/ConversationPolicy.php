<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Policies;

use App\Domain\Messaging\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && $conversation->status->value === 'open';
    }
}
