<?php

declare(strict_types=1);

namespace App\Domain\Trading\Support;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Trading\Models\CartItem;

/**
 * What a cart line costs, and the only place that answers it.
 *
 * The live price wins. `cart_items.unit_price_cents` is what the line was
 * quoted when it was added, and it is deliberately not what gets charged: a
 * cart left open for a month would otherwise buy at last month's price, and a
 * price that came down would still be billed at the old one. So the screen, the
 * summary and the order all read the publisher's current price, and the
 * snapshot's job is to let the cart say "this was $180 when you added it"
 * instead of quietly changing the number.
 *
 * Fees follow the same rule: they are read off the live price row rather than
 * frozen onto the line, so there is one figure to be right about per site
 * rather than three copies of it that can disagree.
 */
final class CartPricer
{
    /** The base price, ignoring the two optional fees. */
    public function base(CartItem $item): Money
    {
        return new Money($this->price($item)?->price_cents ?? 0);
    }

    /** Zero unless the publisher is writing it. */
    public function writingFee(CartItem $item): Money
    {
        if (! $item->content_mode->incursWritingFee()) {
            return Money::zero();
        }

        return new Money($this->price($item)?->writing_fee_cents ?? 0);
    }

    /** Zero unless express was asked for. */
    public function expressFee(CartItem $item): Money
    {
        if (! $item->express) {
            return Money::zero();
        }

        return new Money($this->price($item)?->express_fee_cents ?? 0);
    }

    /** Base plus whichever fees apply. */
    public function total(CartItem $item): Money
    {
        return $this->base($item)->plus($this->writingFee($item))->plus($this->expressFee($item));
    }

    /**
     * What changed since the line was added, or null if nothing did.
     *
     * Compares base prices only. A writing fee that moved because the buyer
     * switched content mode is the buyer's own doing, and reporting it as "the
     * price changed" would be untrue.
     */
    public function drift(CartItem $item): ?Money
    {
        $now = $this->base($item);

        return $now->cents === $item->unit_price_cents ? null : new Money($item->unit_price_cents);
    }

    /**
     * Sums a set of lines. Takes a plain iterable so it works over a group, the
     * whole cart, or the two lines somebody selected.
     *
     * @param  iterable<CartItem>  $items
     */
    public function sum(iterable $items): Money
    {
        $total = Money::zero();

        foreach ($items as $item) {
            $total = $total->plus($this->total($item));
        }

        return $total;
    }

    /**
     * The publisher's live price row for this line's service.
     *
     * Reads the loaded `prices` relation rather than querying, so a cart of
     * forty lines is one eager load rather than forty round trips. Null where
     * the publisher has withdrawn the service since the line was added — which
     * the cart surfaces as a warning rather than as a zero.
     */
    public function price(CartItem $item): ?WebsitePrice
    {
        return $item->website?->priceFor($item->service_type);
    }
}
