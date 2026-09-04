<?php

declare(strict_types=1);

namespace App\Domain\Trading\Actions;

use App\Domain\Billing\Actions\FreezeFundsForOrder;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PromoCode;
use App\Domain\Billing\Models\PromoRedemption;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Article;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Enums\OrderStatus;
use App\Domain\Trading\Enums\PaidFrom;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Domain\Trading\Models\Order;
use App\Domain\Trading\Support\CartPricer;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Checkout, as one transaction.
 *
 * Six things have to happen together: the order row, a post per line, the money
 * frozen in the wallet, the promo redemption recorded, the invoice issued, and
 * the cart emptied. Any subset of those is a broken state somebody has to
 * unpick by hand — money frozen against an order that does not exist, or an
 * empty cart and no posts — so they share one transaction and either all
 * commit or none do.
 *
 * The notification is deliberately outside it. Sending mail inside a database
 * transaction means a mail server having a bad minute rolls back an order that
 * was otherwise fine, and it means the mail can go out describing a transaction
 * that later aborts.
 *
 * Prices are re-read here rather than taken from the cart's snapshot, because
 * this is the moment the money moves and the publisher's current price is the
 * only figure anybody has agreed to. The cart screen shows the same figure for
 * exactly that reason.
 */
final class PlaceOrder
{
    public function __construct(
        private readonly CartPricer $pricer,
        private readonly FreezeFundsForOrder $freezeFunds,
    ) {}

    /**
     * @param  array<string, mixed>  $billing
     */
    public function handle(User $user, Cart $cart, array $billing): Order
    {
        $order = DB::transaction(function () use ($user, $cart, $billing): Order {
            // Re-read under the transaction. A line the buyer removed in
            // another tab while the checkout was open must not be bought.
            $items = $cart->items()
                ->with(['website.prices', 'project', 'folder'])
                ->lockForUpdate()
                ->get();

            $subtotal = $this->pricer->sum($items);
            $promo = $this->redeemablePromo($cart, $user, $subtotal);
            $discount = $promo?->discountFor($subtotal) ?? Money::zero();
            $total = $subtotal->minus($discount);

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => Order::generateNumber(),
                'subtotal_cents' => $subtotal->cents,
                'discount_cents' => $discount->cents,
                'promo_code_id' => $promo?->id,
                'total_cents' => $total->cents,
                'currency' => 'USD',
                'status' => OrderStatus::Paid,
                'paid_from' => PaidFrom::Wallet,
                'paid_at' => now(),
                'billing_details' => $billing,
            ]);

            // Throws InsufficientFunds under the wallet's own row lock, which
            // rolls the whole thing back. A zero total — a code that covers the
            // order outright — has nothing to freeze and must not try: the
            // wallet rejects a non-positive amount by design.
            if ($total->isPositive()) {
                $this->freezeFunds->handle($user, $order);
            }

            foreach ($items as $item) {
                $this->createPost($order, $user, $item);
            }

            if ($promo !== null) {
                PromoRedemption::query()->create([
                    'promo_code_id' => $promo->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_cents' => $discount->cents,
                ]);

                $promo->increment('redemptions_count');
            }

            $this->issueInvoice($order, $user, $billing);

            $cart->items()->delete();
            $cart->update(['promo_code_id' => null]);

            return $order;
        });

        // Outside the transaction, and after it: a notification about an order
        // that did not commit is worse than a notification that arrives late.
        $user->notify(new OrderPlacedNotification($order));

        return $order;
    }

    /**
     * One post per line.
     *
     * "I will write it later" leaves the post in draft rather than refusing the
     * order. Draft is a real state in the lifecycle — nothing happens on it
     * until it is approved — so the buyer gets their order placed and their
     * prices held, and the article becomes a task rather than a blocker.
     */
    private function createPost(Order $order, User $user, CartItem $item): Post
    {
        $hours = $item->website?->publication_period_hours ?? 0;

        $post = Post::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'project_id' => $item->project_id,
            'folder_id' => $item->folder_id,
            'website_id' => $item->website_id,
            'status' => PostStatus::Draft,
            'anchor_text' => $item->anchor_text,
            'target_url' => $item->target_url,
            'content_mode' => $item->content_mode,
            'price_cents' => $this->pricer->total($item)->cents,
            'deadline_at' => now()->addHours($hours),
        ]);

        if ($item->hasArticle()) {
            $article = Article::query()->create([
                'post_id' => $post->id,
                'title' => $item->article_title ?? '',
                'body_html' => $item->article_body_html ?? '',
                'word_count' => $item->article_word_count ?? 0,
                'file_path' => $item->article_file_path,
                'version' => 1,
                'submitted_by' => $user->id,
            ]);

            $post->update(['article_id' => $article->id]);
        }

        // A post that is still waiting on the buyer's own article stays in
        // draft. That is the whole of the rule — it does not consult what was
        // chosen on the content step, because "I said I would do it later" and
        // "there is no article here" are the same fact, and deriving the state
        // from the article itself is the version that cannot disagree with
        // what is actually stored.
        $waiting = ! $item->content_mode->incursWritingFee() && ! $item->hasArticle();

        if (! $waiting) {
            $post->transitionTo(PostStatus::New, "Order {$order->order_number}");
        }

        return $post;
    }

    /**
     * The cart's code, if it is still redeemable at the moment of payment.
     *
     * Re-validated here rather than trusted from the cart, because a code can
     * hit its redemption cap between the summary card rendering and the buyer
     * clicking pay — and the alternative is selling at a discount that no
     * longer exists.
     */
    private function redeemablePromo(Cart $cart, User $user, Money $subtotal): ?PromoCode
    {
        $promo = $cart->promoCode;

        if ($promo === null) {
            return null;
        }

        $fresh = PromoCode::query()->lockForUpdate()->find($promo->id);

        if ($fresh === null || ! $fresh->isRedeemableNow() || $fresh->discountFor($subtotal)->isZero()) {
            return null;
        }

        $used = PromoRedemption::query()
            ->where('promo_code_id', $fresh->id)
            ->where('user_id', $user->id)
            ->exists();

        return $used ? null : $fresh;
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private function issueInvoice(Order $order, User $user, array $billing): Invoice
    {
        return Invoice::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'number' => str_replace('PZ-', 'INV-', $order->order_number),
            'subtotal_cents' => $order->subtotal_cents,
            'tax_cents' => 0,
            'total_cents' => $order->total_cents,
            'currency' => $order->currency,
            'status' => 'paid',
            'billing_details' => $billing,
            'issued_at' => now(),
            'paid_at' => now(),
        ]);
    }
}
