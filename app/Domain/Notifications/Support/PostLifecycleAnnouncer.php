<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Enums\ContentMode;
use App\Models\User;
use App\Notifications\Publinza\ArticleApprovedNotification;
use App\Notifications\Publinza\ArticleReadyForReviewNotification;
use App\Notifications\Publinza\PostPublishedNotification;
use App\Notifications\Publinza\PostRejectedNotification;
use App\Notifications\Publinza\RefundProcessedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Turns a post's status change into the notification it deserves.
 *
 * Called from PostObserver, which is the one place in the codebase guaranteed
 * to see every status change — a notification wired into the actions that
 * happen to move a status today is a notification missing from the action added
 * next month.
 *
 * Silent by design about the states nobody needs telling about. Moving from
 * draft to new is the advertiser's own click, and a notification about
 * something you just did is noise.
 */
final class PostLifecycleAnnouncer
{
    public function announce(Post $post, ?PostStatus $from, PostStatus $to): void
    {
        if (! $this->isWorthTelling($to)) {
            return;
        }

        /*
         * Deferred until the transaction commits.
         *
         * Post::transitionTo() wraps its save in a transaction and this runs
         * inside the `updated` event, so sending from here would announce a
         * publication that a later rollback undid. An email saying your post is
         * live is not retractable.
         */
        DB::afterCommit(fn () => $this->send($post, $from, $to));
    }

    /**
     * The states somebody wants to hear about.
     *
     * Checked before anything is loaded, so a bulk move through a dozen
     * uninteresting transitions costs no queries at all. Approval — the
     * ContentReview → Posted edge — needs no entry of its own: Posted is
     * already here, and `send()` reads the edge from `$from`.
     */
    private function isWorthTelling(PostStatus $to): bool
    {
        return in_array($to, [
            PostStatus::ContentReview,
            PostStatus::Posted,
            PostStatus::Rejected,
            PostStatus::Refunded,
        ], true);
    }

    private function send(Post $post, ?PostStatus $from, PostStatus $to): void
    {
        /*
         * Loaded explicitly, never read off the model and hoped for.
         *
         * `preventLazyLoading` is armed in this application, and this runs from
         * a model event on a post somebody else loaded — a bulk action selects
         * posts without their advertiser, so reading `$post->advertiser` here
         * throws and takes the whole cancellation down with it. loadMissing is
         * an eager load, which the guard allows.
         */
        $post->loadMissing(['advertiser', 'website']);

        $advertiser = $post->advertiser;

        if (! $advertiser instanceof User) {
            return;
        }

        match ($to) {
            PostStatus::ContentReview => $this->reviewReady($advertiser, $post),
            PostStatus::Posted => $advertiser->notify(PostPublishedNotification::for($post)),
            PostStatus::Rejected => $advertiser->notify(PostRejectedNotification::for($post)),
            PostStatus::Refunded => $advertiser->notify(new RefundProcessedNotification(
                $post->price_cents,
                'Your cancelled placement has been settled.',
                $post->id,
            )),
            default => null,
        };

        /*
         * Approval is a move *out* of review, not a state of its own.
         *
         * The lifecycle has no `article_approved` status — approving a draft
         * sends the post to `posted`. The edge is what carries the meaning, so
         * it is read from `$from`.
         */
        if ($from === PostStatus::ContentReview && $to === PostStatus::Posted) {
            $advertiser->notify(ArticleApprovedNotification::for($post));
        }
    }

    /**
     * A draft arriving is only news when somebody else wrote it.
     *
     * A post whose article the advertiser supplied reaches review because they
     * submitted it, and telling them their own copy is ready for their review
     * is the kind of notification that teaches people to ignore the bell.
     */
    private function reviewReady(User $advertiser, Post $post): void
    {
        if ($post->content_mode !== ContentMode::PublisherWrites) {
            return;
        }

        $post->loadMissing('article');

        $advertiser->notify(ArticleReadyForReviewNotification::for(
            $post,
            (int) ($post->article?->word_count ?? 0),
        ));
    }
}
