<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Everything Publinza might tell an advertiser about.
 *
 * The important distinction here is `isTransactional()`. Some of these are
 * records of somebody's money moving or an order changing state, and those are
 * not marketing — turning them off would mean a refund happening in silence.
 * The matrix shows them locked with the reason rather than hiding them, because
 * a preference screen that quietly omits a category reads as a bug.
 */
enum NotificationEvent: string
{
    case PostPublished = 'post_published';
    case PostRejected = 'post_rejected';
    case ArticleReady = 'article_ready';
    case NewMessage = 'new_message';
    case DeadlineApproaching = 'deadline_approaching';
    case BalanceLow = 'balance_low';
    case TopUpConfirmed = 'top_up_confirmed';
    case RefundProcessed = 'refund_processed';
    case WeeklySummary = 'weekly_summary';
    case ProductUpdates = 'product_updates';

    public function label(): string
    {
        return match ($this) {
            self::PostPublished => 'Post published',
            self::PostRejected => 'Post rejected',
            self::ArticleReady => 'Article ready for review',
            self::NewMessage => 'New message',
            self::DeadlineApproaching => 'Deadline approaching',
            self::BalanceLow => 'Balance low',
            self::TopUpConfirmed => 'Top-up confirmed',
            self::RefundProcessed => 'Refund processed',
            self::WeeklySummary => 'Weekly summary',
            self::ProductUpdates => 'Product updates',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PostPublished => 'A placement went live and its link was verified.',
            self::PostRejected => 'A publisher turned a placement down, and why.',
            self::ArticleReady => 'A draft is waiting for you to approve or send back.',
            self::NewMessage => 'Somebody from the Publinza team replied to you.',
            self::DeadlineApproaching => 'A post is due in the next few days.',
            self::BalanceLow => 'Your balance is running low for what you have queued.',
            self::TopUpConfirmed => 'Money reached your balance.',
            self::RefundProcessed => 'Money came back to your balance.',
            self::WeeklySummary => 'What happened across your projects last week.',
            self::ProductUpdates => 'New features and changes to how Publinza works.',
        };
    }

    /**
     * Records of money or an order changing state.
     *
     * These cannot be switched off by email, and the pause switch skips them:
     * an advertiser who has muted everything still has to be told that $2,400
     * was refunded, or the first they hear of it is a bank statement.
     */
    public function isTransactional(): bool
    {
        return in_array($this, [
            self::PostPublished,
            self::PostRejected,
            self::TopUpConfirmed,
            self::RefundProcessed,
        ], true);
    }

    /**
     * What a brand-new account gets before it has ever opened this screen.
     *
     * @return array{email: bool, in_app: bool, push: bool}
     */
    public function defaults(): array
    {
        return match ($this) {
            // Nobody wants a browser notification for a weekly digest, and
            // product news defaults to in-app only — an email you did not ask
            // for is the fastest way to teach somebody to ignore all of them.
            self::WeeklySummary => ['email' => true, 'in_app' => false, 'push' => false],
            self::ProductUpdates => ['email' => false, 'in_app' => true, 'push' => false],
            self::NewMessage => ['email' => true, 'in_app' => true, 'push' => true],
            default => ['email' => true, 'in_app' => true, 'push' => false],
        };
    }
}
