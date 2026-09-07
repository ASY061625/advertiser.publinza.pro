<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

use App\Domain\Identity\Enums\NotificationEvent;

/**
 * Every distinct thing Publinza sends, and how each one looks.
 *
 * A *type* is a template — one title shape, one body shape, one icon, one
 * colour, one deep link. A *preference event* is the category somebody switches
 * on and off. There are more types than events on purpose: "deadline
 * approaching" and "deadline missed" are two different messages about the same
 * thing, and nobody wants to be asked about them separately.
 *
 * `preference()` is where those two vocabularies meet. Every type names the
 * event that governs it, so there is no type whose email is decided by
 * something the notifications screen does not show.
 */
enum NotificationType: string
{
    case PostPublished = 'post_published';
    case PostRejected = 'post_rejected';
    case ArticleReadyForReview = 'article_ready_for_review';
    case ArticleApproved = 'article_approved';
    case MessageReceived = 'message_received';
    case DeadlineApproaching = 'deadline_approaching';
    case DeadlineMissed = 'deadline_missed';
    case BalanceLow = 'balance_low';
    case TopUpConfirmed = 'topup_confirmed';
    case RefundProcessed = 'refund_processed';
    case OrderConfirmed = 'order_confirmed';
    case PriceChangedInCart = 'price_changed_in_cart';
    case CompetitorReportReady = 'competitor_report_ready';
    case ExportReady = 'export_ready';
    case WeeklySummary = 'weekly_summary';

    /** The preference row that decides whether this one is emailed or pushed. */
    public function preference(): NotificationEvent
    {
        return match ($this) {
            self::PostPublished => NotificationEvent::PostPublished,
            self::PostRejected => NotificationEvent::PostRejected,
            // One review loop, one switch: being told a draft has arrived and
            // being told it was accepted are the same conversation.
            self::ArticleReadyForReview, self::ArticleApproved => NotificationEvent::ArticleReady,
            self::MessageReceived => NotificationEvent::NewMessage,
            self::DeadlineApproaching, self::DeadlineMissed => NotificationEvent::DeadlineApproaching,
            self::BalanceLow => NotificationEvent::BalanceLow,
            self::TopUpConfirmed => NotificationEvent::TopUpConfirmed,
            self::RefundProcessed => NotificationEvent::RefundProcessed,
            self::OrderConfirmed => NotificationEvent::OrderConfirmed,
            self::PriceChangedInCart => NotificationEvent::PriceChanged,
            self::CompetitorReportReady, self::ExportReady => NotificationEvent::ReportReady,
            self::WeeklySummary => NotificationEvent::WeeklySummary,
        };
    }

    /**
     * The icon name the client resolves to a component.
     *
     * A name rather than markup: the payload is stored in the database and read
     * back months later, and an SVG frozen into a row is a design system that
     * cannot be changed.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PostPublished, self::ArticleApproved => 'success',
            self::PostRejected, self::DeadlineMissed => 'danger',
            self::ArticleReadyForReview => 'document',
            self::MessageReceived => 'chat',
            self::DeadlineApproaching => 'clock',
            self::BalanceLow => 'wallet',
            self::TopUpConfirmed, self::RefundProcessed => 'wallet',
            self::OrderConfirmed => 'receipt',
            self::PriceChangedInCart => 'tag',
            self::CompetitorReportReady, self::WeeklySummary => 'chart',
            self::ExportReady => 'download',
        };
    }

    /**
     * The semantic colour the icon wears.
     *
     * Drawn from the status palette the rest of the app already uses, so a
     * published placement is the same green in the drawer as it is in the post
     * table. `info` is the default rather than brand blue, because brand blue
     * is the colour of something you can click.
     */
    public function tone(): string
    {
        return match ($this) {
            self::PostPublished, self::ArticleApproved, self::TopUpConfirmed => 'success',
            self::PostRejected, self::DeadlineMissed => 'danger',
            self::DeadlineApproaching, self::BalanceLow, self::PriceChangedInCart => 'warning',
            self::RefundProcessed => 'gold',
            self::ArticleReadyForReview, self::OrderConfirmed => 'review',
            default => 'info',
        };
    }

    /**
     * Whether several of these collapse into one line in the drawer.
     *
     * Only for the ones that genuinely arrive in bursts. A weekly summary is
     * never going to have three of itself in a day, and collapsing something
     * that only ever appears once would hide it behind a disclosure triangle
     * for no reason.
     */
    public function collapsible(): bool
    {
        return match ($this) {
            self::PostPublished, self::PostRejected, self::ArticleReadyForReview,
            self::ArticleApproved, self::DeadlineApproaching, self::DeadlineMissed,
            self::PriceChangedInCart => true,
            default => false,
        };
    }

    /**
     * The collapsed headline: "3 posts published".
     *
     * The count is always plural at the call site — a group of one is never
     * collapsed — but the singular is written out anyway rather than trusting
     * that to stay true.
     */
    public function summary(int $count): string
    {
        $one = $count === 1;

        return match ($this) {
            self::PostPublished => $one ? '1 post published' : "{$count} posts published",
            self::PostRejected => $one ? '1 post rejected' : "{$count} posts rejected",
            self::ArticleReadyForReview => $one ? '1 article ready for review' : "{$count} articles ready for review",
            self::ArticleApproved => $one ? '1 article approved' : "{$count} articles approved",
            self::DeadlineApproaching => $one ? '1 deadline approaching' : "{$count} deadlines approaching",
            self::DeadlineMissed => $one ? '1 deadline missed' : "{$count} deadlines missed",
            self::PriceChangedInCart => $one ? '1 price changed in your cart' : "{$count} prices changed in your cart",
            default => $one ? "1 {$this->value}" : "{$count} × {$this->value}",
        };
    }
}
