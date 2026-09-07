<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Wallet;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Enums\ContentMode;
use App\Models\User;
use App\Notifications\Publinza\ArticleReadyForReviewNotification;
use App\Notifications\Publinza\BalanceLowNotification;
use App\Notifications\Publinza\DeadlineApproachingNotification;
use App\Notifications\Publinza\DeadlineMissedNotification;
use App\Notifications\Publinza\PostPublishedNotification;
use App\Notifications\Publinza\PostRejectedNotification;
use App\Notifications\Publinza\WeeklySummaryNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

function placement(User $user, array $attributes = []): Post
{
    // A fresh domain per call: `websites.domain` is unique, and two placements
    // in one test are two different sites.
    static $n = 0;
    $website = site(['domain' => 'techweekly'.(++$n).'.com']);

    return Post::factory()->create([
        'user_id' => $user->id,
        'website_id' => $website->id,
        'status' => PostStatus::InProgress,
        'content_mode' => ContentMode::PublisherWrites,
        'price_cents' => 240_00,
        ...$attributes,
    ]);
}

// --------------------------------------------------------- the post lifecycle

it('announces a publication from the observer, whatever moved the status', function (): void {
    Notification::fake();

    $user = buyer();
    $post = placement($user, ['status' => PostStatus::ContentReview]);

    // A raw update, not an action. The observer is the guarantee — a
    // notification wired into today's call sites is one missing from the call
    // site added next month.
    $post->update(['status' => PostStatus::Posted, 'published_url' => 'https://techweekly.com/x']);

    Notification::assertSentTo($user, PostPublishedNotification::class);
});

it('announces a rejection with the publisher’s reason', function (): void {
    Notification::fake();

    $user = buyer();
    $post = placement($user, ['rejection_reason' => 'The brief does not fit our editorial line.']);

    $post->update(['status' => PostStatus::Rejected]);

    Notification::assertSentTo(
        $user,
        PostRejectedNotification::class,
        fn (PostRejectedNotification $n): bool => str_contains($n->body(), 'editorial line'),
    );
});

it('only asks for a review of an article somebody else wrote', function (): void {
    Notification::fake();

    $mine = buyer();
    $theirs = buyer();

    placement($theirs)->update(['status' => PostStatus::ContentReview]);
    placement($mine, ['content_mode' => ContentMode::AdvertiserProvides])
        ->update(['status' => PostStatus::ContentReview]);

    Notification::assertSentTo($theirs, ArticleReadyForReviewNotification::class);

    // Telling somebody their own copy is ready for their review is the
    // notification that teaches people to ignore the bell.
    Notification::assertNotSentTo($mine, ArticleReadyForReviewNotification::class);
});

it('says nothing about a status the advertiser moved themselves', function (): void {
    Notification::fake();

    $user = buyer();

    placement($user, ['status' => PostStatus::Draft])->update(['status' => PostStatus::New]);

    Notification::assertNothingSentTo($user);
});

it('survives a status change on a post that was loaded without its advertiser', function (): void {
    Notification::fake();

    $user = buyer();
    $id = placement($user, ['status' => PostStatus::ContentReview])->id;

    /*
     * The shape every bulk action produces: posts selected by id, no relations.
     * `preventLazyLoading` is armed here, so an announcer that reads
     * `$post->advertiser` off the model throws inside the observer and takes
     * the whole cancellation down with it.
     */
    $bare = Post::query()->whereKey($id)->get()->first();

    $bare->update(['status' => PostStatus::Posted]);

    expect($bare->fresh()->status)->toBe(PostStatus::Posted);
    Notification::assertSentTo($user, PostPublishedNotification::class);
});

it('says nothing about a publication the transaction rolled back', function (): void {
    Notification::fake();

    $user = buyer();
    $post = placement($user, ['status' => PostStatus::ContentReview]);

    try {
        DB::transaction(function () use ($post): void {
            $post->update(['status' => PostStatus::Posted]);

            throw new RuntimeException('something later went wrong');
        });
    } catch (RuntimeException) {
        // Expected.
    }

    // The status change was undone, so the email must never have gone. An
    // announcement is not retractable.
    expect($post->fresh()->status)->toBe(PostStatus::ContentReview);
    Notification::assertNothingSentTo($user);
});

// ------------------------------------------------------------- the daily scan

it('warns about a deadline coming up and one already missed', function (): void {
    Notification::fake();

    $soon = buyer();
    $late = buyer();

    placement($soon, ['deadline_at' => now()->addDays(2)]);
    placement($late, ['deadline_at' => now()->subDays(2)]);

    $this->artisan('notifications:scan')->assertExitCode(0);

    Notification::assertSentTo($soon, DeadlineApproachingNotification::class);
    Notification::assertSentTo($late, DeadlineMissedNotification::class);
});

it('does not say the same thing again tomorrow', function (): void {
    $user = buyer();

    placement($user, ['deadline_at' => now()->addDays(2)]);

    $this->artisan('notifications:scan');
    $this->artisan('notifications:scan');

    // A scheduled scan without a dedupe is a scan that emails somebody every
    // morning about the same deadline.
    expect($user->notifications()->count())->toBe(1);
});

it('leaves a deadline alone once the post is finished', function (): void {
    Notification::fake();

    $user = buyer();

    placement($user, ['status' => PostStatus::Completed, 'deadline_at' => now()->subDays(2)]);

    $this->artisan('notifications:scan');

    Notification::assertNotSentTo($user, DeadlineMissedNotification::class);
});

it('warns when the balance will not cover what is frozen', function (): void {
    Notification::fake();

    $short = buyer();
    $fine = buyer();

    Wallet::query()->create(['user_id' => $short->id, 'available_cents' => 50_00, 'frozen_cents' => 300_00]);
    Wallet::query()->create(['user_id' => $fine->id, 'available_cents' => 900_00, 'frozen_cents' => 300_00]);

    $this->artisan('notifications:scan');

    Notification::assertSentTo($short, BalanceLowNotification::class);
    Notification::assertNotSentTo($fine, BalanceLowNotification::class);
});

it('names the shortfall rather than leaving the arithmetic to the reader', function (): void {
    $body = (new BalanceLowNotification(50_00, 300_00))->body();

    expect($body)->toContain('$250.00 short');
});

// ---------------------------------------------------------- the weekly summary

it('skips a weekly summary for an account with nothing to summarise', function (): void {
    Notification::fake();

    $busy = buyer();
    $quiet = buyer();

    placement($busy, ['status' => PostStatus::InProgress]);

    $this->artisan('notifications:weekly-summary')->assertExitCode(0);

    Notification::assertSentTo($busy, WeeklySummaryNotification::class);

    // A digest sent to somebody with nothing in it is the email that gets a
    // filter rule written for it — and takes the rest of our mail with it.
    Notification::assertNotSentTo($quiet, WeeklySummaryNotification::class);
});

it('reads a stored row back as the sentence it was written as', function (): void {
    $user = buyer();
    $post = placement($user, ['status' => PostStatus::ContentReview]);

    $post->update(['status' => PostStatus::Posted, 'published_url' => 'https://techweekly.com/x']);

    $row = $user->notifications()
        ->get()
        ->firstWhere(fn ($n): bool => ($n->data['type'] ?? null) === NotificationType::PostPublished->value);

    expect($row->data['title'])->toBe("Published on {$post->website->domain}")
        ->and($row->data['href'])->toBe("/posts/{$post->id}")
        ->and($row->data['icon'])->toBe('success')
        ->and($row->data['tone'])->toBe('success');
});
