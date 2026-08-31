<?php

declare(strict_types=1);

namespace App\Domain\Posts\DTOs;

/**
 * The status vocabulary shared by the advertiser app and the admin panel. The
 * front-end colour map in `resources/js/shared/lib/status.ts` keys off these
 * exact values.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case New = 'new';
    case InProgress = 'in_progress';
    case ContentReview = 'content_review';
    case Published = 'published';
    case Frozen = 'frozen';
    case Rejected = 'rejected';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::New => 'New',
            self::InProgress => 'In progress',
            self::ContentReview => 'Content review',
            self::Published => 'Published',
            self::Frozen => 'Frozen',
            self::Rejected => 'Rejected',
            self::Refunded => 'Refunded',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Published, self::Rejected, self::Refunded], true);
    }
}
