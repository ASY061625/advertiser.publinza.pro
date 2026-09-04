<?php

declare(strict_types=1);

namespace App\Domain\Trading\Actions;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Support\CartPresenter;
use App\Models\User;

/**
 * The advertiser's cart, loaded and priced.
 *
 * Server-side and one per user, so it survives a logout, a new laptop and a
 * phone. That is not a nicety: the thing being configured here is an order
 * worth hundreds of dollars across a dozen sites, and losing it to a cleared
 * browser is the kind of failure people do not come back from.
 */
final class GetCart
{
    public function __construct(private readonly CartPresenter $presenter) {}

    public function cart(User $user): ?Cart
    {
        return Cart::query()->with(CartPresenter::RELATIONS)->firstWhere('user_id', $user->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        return $this->fromCart($this->cart($user));
    }

    /**
     * @return array<string, mixed>
     */
    public function fromCart(?Cart $cart): array
    {
        $promo = $cart?->promoCode;

        // A code can expire, run out of redemptions or be switched off while it
        // sits on a cart. Re-checked here rather than only at checkout, so the
        // total on screen is the total that will be charged.
        $valid = $promo !== null && $promo->isRedeemableNow();
        $gross = $this->presenter->totals($cart, Money::zero());
        $discount = $valid
            ? $promo->discountFor(new Money($gross['totalCents']))
            : Money::zero();

        return $this->presenter->handle($cart) + [
            'totals' => $this->presenter->totals($cart, $discount),
            'promo' => $promo === null ? null : [
                'code' => $promo->code,
                'description' => $promo->description,
                'discountCents' => $discount->cents,
                // Says why a code that used to work has stopped, rather than
                // dropping the discount line and letting the total move on its
                // own between one page load and the next.
                'expired' => ! $valid,
                'belowMinimum' => $valid
                    && $discount->isZero()
                    && $gross['totalCents'] < $promo->minimum_spend_cents,
                'minimumSpendCents' => $promo->minimum_spend_cents,
            ],
        ];
    }
}
