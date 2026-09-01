<?php

declare(strict_types=1);

use App\Domain\Posts\Enums\PostStatus;

/**
 * The lifecycle is:
 *   draft → new → in_progress → content_review → posted → completed
 * with rejected reachable from in_progress and content_review, cancelled from
 * any pre-posted state, and cancelled → refunded.
 *
 * These assert the map exactly — both the edges that exist and the ones that
 * must not.
 */
it('allows exactly the forward path', function (): void {
    expect(PostStatus::Draft->canTransitionTo(PostStatus::New))->toBeTrue()
        ->and(PostStatus::New->canTransitionTo(PostStatus::InProgress))->toBeTrue()
        ->and(PostStatus::InProgress->canTransitionTo(PostStatus::ContentReview))->toBeTrue()
        ->and(PostStatus::ContentReview->canTransitionTo(PostStatus::Posted))->toBeTrue()
        ->and(PostStatus::Posted->canTransitionTo(PostStatus::Completed))->toBeTrue();
});

it('allows rejection only from in_progress and content_review', function (): void {
    expect(PostStatus::InProgress->canTransitionTo(PostStatus::Rejected))->toBeTrue()
        ->and(PostStatus::ContentReview->canTransitionTo(PostStatus::Rejected))->toBeTrue();

    foreach ([PostStatus::Draft, PostStatus::New, PostStatus::Posted, PostStatus::Completed] as $status) {
        expect($status->canTransitionTo(PostStatus::Rejected))->toBeFalse();
    }
});

it('allows cancellation from any pre-posted state and nowhere else', function (): void {
    foreach ([PostStatus::Draft, PostStatus::New, PostStatus::InProgress, PostStatus::ContentReview] as $status) {
        expect($status->canTransitionTo(PostStatus::Cancelled))->toBeTrue();
    }

    foreach ([PostStatus::Posted, PostStatus::Completed, PostStatus::Rejected, PostStatus::Refunded] as $status) {
        expect($status->canTransitionTo(PostStatus::Cancelled))->toBeFalse();
    }
});

it('reaches refunded only from cancelled', function (): void {
    expect(PostStatus::Cancelled->allowedTransitions())->toBe([PostStatus::Refunded]);

    foreach (PostStatus::cases() as $status) {
        if ($status !== PostStatus::Cancelled) {
            expect($status->canTransitionTo(PostStatus::Refunded))->toBeFalse();
        }
    }
});

it('treats completed, rejected and refunded as terminal', function (): void {
    expect(PostStatus::Completed->isTerminal())->toBeTrue()
        ->and(PostStatus::Rejected->isTerminal())->toBeTrue()
        ->and(PostStatus::Refunded->isTerminal())->toBeTrue();
});

it('does not allow skipping a step', function (): void {
    expect(PostStatus::Draft->canTransitionTo(PostStatus::InProgress))->toBeFalse()
        ->and(PostStatus::New->canTransitionTo(PostStatus::Posted))->toBeFalse()
        ->and(PostStatus::InProgress->canTransitionTo(PostStatus::Completed))->toBeFalse();
});

it('does not allow going backwards', function (): void {
    expect(PostStatus::Posted->canTransitionTo(PostStatus::ContentReview))->toBeFalse()
        ->and(PostStatus::ContentReview->canTransitionTo(PostStatus::InProgress))->toBeFalse()
        ->and(PostStatus::InProgress->canTransitionTo(PostStatus::New))->toBeFalse();
});

it('never lists a transition to itself', function (): void {
    foreach (PostStatus::cases() as $status) {
        expect($status->allowedTransitions())->not->toContain($status);
    }
});
