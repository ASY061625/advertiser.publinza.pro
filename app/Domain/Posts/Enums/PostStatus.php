<?php

declare(strict_types=1);

namespace App\Domain\Posts\Enums;

/**
 * The post lifecycle — the single source of truth for the whole product.
 *
 *     draft → new → in_progress → content_review → posted → completed
 *                        ↓              ↓
 *                    rejected ←─────────┘
 *          any pre-posted state → cancelled → refunded
 *
 * Stored as a string column, never a database enum: adding a status to a MySQL
 * ENUM is a locking DDL change, and the set of valid values belongs in code
 * where it can be tested.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case New = 'new';
    case InProgress = 'in_progress';
    case ContentReview = 'content_review';
    case Posted = 'posted';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Every legal edge in the lifecycle. Anything not listed here throws.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // The forward path. Each pre-posted state can also be cancelled.
            self::Draft => [self::New, self::Cancelled],
            self::New => [self::InProgress, self::Cancelled],
            self::InProgress => [self::ContentReview, self::Rejected, self::Cancelled],
            self::ContentReview => [self::Posted, self::Rejected, self::Cancelled],

            // `posted` opens the 3-day verification window; it is past the point
            // where the advertiser may cancel, so `completed` is the only exit.
            self::Posted => [self::Completed],

            // Cancelling frees the money, which is what `refunded` records.
            self::Cancelled => [self::Refunded],

            // Terminal.
            self::Completed, self::Rejected, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** Before the post is live — the window in which it may still be cancelled. */
    public function isPrePosted(): bool
    {
        return in_array($this, [self::Draft, self::New, self::InProgress, self::ContentReview], true);
    }

    /** Statuses whose money sits in the wallet's frozen bucket. */
    public function holdsFrozenFunds(): bool
    {
        return in_array($this, [self::New, self::InProgress, self::ContentReview, self::Posted], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::New => 'New',
            self::InProgress => 'In progress',
            self::ContentReview => 'Content review',
            self::Posted => 'Posted',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Maps onto the fixed status palette in the design system. `posted` and
     * `completed` share the published treatment; `cancelled` shares rejected's.
     */
    public function badgeKey(): string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::New => 'new',
            self::InProgress => 'in_progress',
            self::ContentReview => 'content_review',
            self::Posted, self::Completed => 'posted',
            self::Rejected, self::Cancelled => 'rejected',
            self::Refunded => 'refunded',
        };
    }
}
