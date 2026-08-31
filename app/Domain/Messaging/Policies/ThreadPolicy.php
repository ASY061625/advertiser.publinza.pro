<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Policies;

use App\Domain\Messaging\Models\Thread;
use App\Models\User;

class ThreadPolicy
{
    public function view(User $user, Thread $thread): bool
    {
        return $thread->post->project->user_id === $user->id;
    }

    public function reply(User $user, Thread $thread): bool
    {
        return $this->view($user, $thread);
    }
}
