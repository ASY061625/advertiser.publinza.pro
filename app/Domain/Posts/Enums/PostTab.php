<?php

declare(strict_types=1);

namespace App\Domain\Posts\Enums;

/**
 * The status tabs across the top of /posts.
 *
 * A tab is a lifecycle *phase*, not a single status, because two of the nine
 * statuses are the terminal end of a phase that already has a tab:
 *
 *   - `completed` is where `posted` goes after the verification window. A
 *     Posted tab that excluded it would quietly empty out as posts aged, and
 *     an advertiser looking for last month's live link would not find it.
 *   - `refunded` is where `cancelled` goes once the money is returned. Same
 *     problem: the post does not stop being cancelled once it is refunded.
 *
 * Grouping them here is what makes the tab counts sum to the All count. Anyone
 * who wants those two on their own still has them in the status multi-select,
 * which filters by status rather than by phase.
 */
enum PostTab: string
{
    case All = 'all';
    case Draft = 'draft';
    case New = 'new';
    case InProgress = 'in_progress';
    case ContentReview = 'content_review';
    case Posted = 'posted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Draft => 'Draft',
            self::New => 'New',
            self::InProgress => 'In progress',
            self::ContentReview => 'Content review',
            self::Posted => 'Posted',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * The statuses this tab covers. Empty for All, which filters nothing.
     *
     * @return list<PostStatus>
     */
    public function statuses(): array
    {
        return match ($this) {
            self::All => [],
            self::Draft => [PostStatus::Draft],
            self::New => [PostStatus::New],
            self::InProgress => [PostStatus::InProgress],
            self::ContentReview => [PostStatus::ContentReview],
            self::Posted => [PostStatus::Posted, PostStatus::Completed],
            self::Rejected => [PostStatus::Rejected],
            self::Cancelled => [PostStatus::Cancelled, PostStatus::Refunded],
        };
    }

    /** Drives the 2px underline; the tab wears its phase's colour. */
    public function badgeKey(): string
    {
        // All has no status of its own; every other tab has at least one.
        return $this === self::All ? 'all' : $this->statuses()[0]->badgeKey();
    }

    public static function tryFromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::All) : self::All;
    }

    /**
     * Every status lands in exactly one tab. Asserted in the test suite, so a
     * tenth status cannot be added without deciding where it belongs.
     */
    public static function forStatus(PostStatus $status): self
    {
        foreach (self::cases() as $tab) {
            if (in_array($status, $tab->statuses(), true)) {
                return $tab;
            }
        }

        return self::All;
    }
}
