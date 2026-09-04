<?php

declare(strict_types=1);

namespace App\Domain\Trading\Actions;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\PromoCode;
use App\Domain\Billing\Models\PromoRedemption;
use App\Domain\Trading\Models\Cart;
use App\Models\User;

/**
 * Attaching a promo code to a cart, and saying no in a way that helps.
 *
 * Every rejection names its own reason. "That code is not valid" covers six
 * different situations, four of which the buyer could fix in ten seconds if
 * anybody told them which one they were in.
 */
final class ApplyPromoCode
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function handle(User $user, Cart $cart, string $code, Money $subtotal): array
    {
        $promo = PromoCode::query()->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])->first();

        if ($promo === null) {
            return $this->no("There is no code {$code}. Check for a typo.");
        }

        if (! $promo->is_active) {
            return $this->no('That code is no longer active.');
        }

        if ($promo->starts_at !== null && $promo->starts_at->isFuture()) {
            return $this->no('That code does not start until '.$promo->starts_at->format('j M Y').'.');
        }

        if ($promo->ends_at !== null && $promo->ends_at->isPast()) {
            return $this->no('That code expired on '.$promo->ends_at->format('j M Y').'.');
        }

        if ($promo->max_redemptions !== null && $promo->redemptions_count >= $promo->max_redemptions) {
            return $this->no('That code has been fully redeemed.');
        }

        // One redemption per advertiser. Checked here so a second attempt is
        // refused at the cart rather than at the end of a three-step checkout.
        $used = PromoRedemption::query()
            ->where('promo_code_id', $promo->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($used) {
            return $this->no('You have already used that code.');
        }

        if ($subtotal->cents < $promo->minimum_spend_cents) {
            $short = new Money($promo->minimum_spend_cents - $subtotal->cents);

            return $this->no(
                sprintf(
                    'That code needs an order of %s or more. Add %s to use it.',
                    (new Money($promo->minimum_spend_cents))->format(),
                    $short->format(),
                ),
            );
        }

        $cart->update(['promo_code_id' => $promo->id]);

        return [
            'ok' => true,
            'message' => sprintf('%s applied — %s off.', $promo->code, $promo->discountFor($subtotal)->format()),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function no(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
