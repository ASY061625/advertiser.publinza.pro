<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Models\PostStatusHistory;
use App\Domain\Posts\Support\PostStatusContext;
use App\Exceptions\InvalidStatusTransition;

/**
 * Makes the post lifecycle non-optional.
 *
 * Two guarantees, both enforced here rather than in the actions that happen to
 * change a status today:
 *
 *   1. A status can only move along an edge in PostStatus::allowedTransitions().
 *      Anything else throws InvalidStatusTransition before the write.
 *   2. Every change writes a post_status_history row, including the post's
 *      creation. There is no code path that changes `posts.status` and leaves
 *      no trace, because this runs on the model event rather than at call sites.
 *
 * Post::transitionTo() additionally wraps the save in a transaction, so the
 * status change and its history row commit together.
 */
class PostObserver
{
    /** Records the post's birth, so the timeline starts at the beginning. */
    public function created(Post $post): void
    {
        $this->record($post, null, $post->status);
    }

    /** Refuses an illegal move before it reaches the database. */
    public function updating(Post $post): void
    {
        if (! $post->isDirty('status')) {
            return;
        }

        $from = $this->statusFrom($post->getOriginal('status'));
        $to = $post->status;

        if ($from === null || $from === $to) {
            return;
        }

        if (! $from->canTransitionTo($to)) {
            throw new InvalidStatusTransition($from, $to, $post->getKey());
        }
    }

    /** Writes the history row once the change has actually landed. */
    public function updated(Post $post): void
    {
        if (! $post->wasChanged('status')) {
            return;
        }

        $from = $this->statusFrom($post->getOriginal('status'));

        $this->record($post, $from, $post->status);
    }

    private function record(Post $post, ?PostStatus $from, PostStatus $to): void
    {
        [$actorType, $actorId] = PostStatusContext::resolveActor();

        PostStatusHistory::query()->create([
            'post_id' => $post->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => PostStatusContext::takeNote(),
        ]);
    }

    /**
     * `getOriginal()` returns the raw column value, which is a string on a model
     * loaded from the database but already a PostStatus on one that was just
     * created in memory.
     */
    private function statusFrom(mixed $original): ?PostStatus
    {
        if ($original instanceof PostStatus) {
            return $original;
        }

        if (is_string($original)) {
            return PostStatus::tryFrom($original);
        }

        return null;
    }
}
