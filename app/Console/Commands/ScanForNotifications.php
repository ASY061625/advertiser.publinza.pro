<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Models\Wallet;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Models\CartItem;
use App\Domain\Trading\Support\CartPricer;
use App\Models\User;
use App\Notifications\Publinza\BalanceLowNotification;
use App\Notifications\Publinza\DeadlineApproachingNotification;
use App\Notifications\Publinza\DeadlineMissedNotification;
use App\Notifications\Publinza\PriceChangedInCartNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The notifications nothing else can raise.
 *
 * Most types fire from the thing that happened — a post moved status, a payment
 * cleared, a message arrived. These four have no event to hang off: a deadline
 * is missed by *nothing* happening, a balance is low relative to what is queued,
 * and a price changed on somebody else's row. Something has to come and look.
 *
 * Daily, at a fixed hour. Every notice here is about a state that persists, so
 * a more frequent scan would only mean saying the same thing more often — and
 * every one of them is deduped against what has already been sent, because a
 * scheduled scan without that is a scan that emails somebody every morning
 * about the same deadline.
 */
class ScanForNotifications extends Command
{
    protected $signature = 'notifications:scan';

    protected $description = 'Raise the notifications that come from elapsed time rather than from an event';

    /** How far ahead a deadline counts as approaching. */
    private const WARN_DAYS = 3;

    /** A balance under this share of what is queued is low. */
    private const LOW_RATIO = 1.0;

    public function handle(CartPricer $pricer): int
    {
        $this->deadlines();
        $this->lowBalances();
        $this->cartPrices($pricer);

        return self::SUCCESS;
    }

    /**
     * Posts due soon, and posts already late.
     *
     * The statuses are the ones where a deadline still means something —
     * a completed post cannot miss its date, and a draft has not started.
     */
    private function deadlines(): void
    {
        $live = [PostStatus::New, PostStatus::InProgress, PostStatus::ContentReview];

        Post::query()
            ->with(['advertiser', 'website:id,domain'])
            ->whereIn('status', $live)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now()->addDays(self::WARN_DAYS))
            ->chunkById(200, function ($posts): void {
                foreach ($posts as $post) {
                    $user = $post->advertiser;

                    if ($user === null || $post->deadline_at === null) {
                        continue;
                    }

                    $late = $post->deadline_at->isPast();

                    $type = $late ? NotificationType::DeadlineMissed : NotificationType::DeadlineApproaching;

                    // Once per post per type. A daily scan that skips this is a
                    // daily email about the same late placement.
                    if ($this->alreadyToldAbout($user, $type, 'post_id', $post->id)) {
                        continue;
                    }

                    $user->notify($late
                        ? DeadlineMissedNotification::for($post, (int) $post->deadline_at->diffInDays(now()))
                        : DeadlineApproachingNotification::for(
                            $post,
                            (int) now()->startOfDay()->diffInDays($post->deadline_at->startOfDay(), false),
                        ));
                }
            });
    }

    /**
     * Wallets that will not cover what is already frozen against them.
     *
     * Frozen is the right comparison, not the cart: money frozen against live
     * orders is money already committed, and an advertiser whose available
     * balance has fallen below it will find out at the next charge if nobody
     * says so first.
     */
    private function lowBalances(): void
    {
        Wallet::query()
            ->with('owner')
            ->where('frozen_cents', '>', 0)
            ->whereColumn('available_cents', '<', 'frozen_cents')
            ->chunkById(200, function ($wallets): void {
                foreach ($wallets as $wallet) {
                    $user = $wallet->owner;

                    if ($user === null) {
                        continue;
                    }

                    if ($wallet->available_cents >= $wallet->frozen_cents * self::LOW_RATIO) {
                        continue;
                    }

                    // Weekly, not daily: this is a standing condition, and a
                    // notice every morning about a balance somebody has decided
                    // to leave where it is trains them to ignore the bell.
                    if ($this->sentRecently($user, NotificationType::BalanceLow, 7)) {
                        continue;
                    }

                    $user->notify(new BalanceLowNotification(
                        $wallet->available_cents,
                        $wallet->frozen_cents,
                    ));
                }
            });
    }

    /**
     * Cart lines whose site has moved price since the line was added.
     *
     * The cart already charges the live price — see CartPricer — so nothing is
     * wrong here. The point is that somebody who filled a cart on Monday and
     * checks out on Friday should not discover a change at the total.
     */
    private function cartPrices(CartPricer $pricer): void
    {
        CartItem::query()
            ->with(['cart.owner', 'website:id,domain', 'website.prices'])
            ->chunkById(200, function ($items) use ($pricer): void {
                foreach ($items as $item) {
                    $user = $item->cart?->owner;
                    // Null when nothing moved. Base prices only — a fee that
                    // changed because the buyer switched content mode is their
                    // own doing.
                    $quoted = $pricer->drift($item);

                    if ($user === null || $quoted === null || $item->website === null) {
                        continue;
                    }

                    $live = $pricer->base($item);

                    if ($this->alreadyToldAbout($user, NotificationType::PriceChangedInCart, 'to_cents', $live->cents)) {
                        continue;
                    }

                    $user->notify(new PriceChangedInCartNotification(
                        $item->website->domain,
                        $quoted->cents,
                        $live->cents,
                    ));
                }
            });
    }

    /**
     * Has this exact thing already been said?
     *
     * Read out of `notifications` rather than tracked in a table of its own.
     * The record of what was sent *is* the notifications table, and a second
     * store of the same fact is a second store to keep in step.
     */
    private function alreadyToldAbout(User $user, NotificationType $type, string $key, int|string $value): bool
    {
        return $user->notifications()
            ->where('created_at', '>', now()->subDays(30))
            ->get(['data'])
            ->contains(fn (DatabaseNotification $row): bool => ($row->data['type'] ?? null) === $type->value
                && ($row->data[$key] ?? null) === $value);
    }

    private function sentRecently(User $user, NotificationType $type, int $days): bool
    {
        return $user->notifications()
            ->where('created_at', '>', now()->subDays($days))
            ->get(['data'])
            ->contains(fn (DatabaseNotification $row): bool => ($row->data['type'] ?? null) === $type->value);
    }
}
