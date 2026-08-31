<?php

declare(strict_types=1);

namespace App\Domain\Posts\Policies;

use App\Domain\Posts\DTOs\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $post->project->user_id === $user->id;
    }

    public function approve(User $user, Post $post): bool
    {
        return $this->view($user, $post) && $post->status === PostStatus::ContentReview->value;
    }
}
