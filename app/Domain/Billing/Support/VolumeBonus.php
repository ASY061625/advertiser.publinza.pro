<?php

declare(strict_types=1);

namespace App\Domain\Billing\Support;

use App\Domain\Billing\DTOs\Money;

/**
 * Credit added on top of a large top-up.
 *
 * Computed here and only here, on the server. The summary box shows the figure
 * this class returns and the deposit writes the figure this class returns — a
 * bonus calculated in the browser is a bonus somebody can edit before sending.
 *
 * Tiers are flat percentages at thresholds rather than a curve: an advertiser
 * has to be able to work out in their head that another $500 is worth it, and
 * "5% over $2,500" is a sentence. A curve is a spreadsheet.
 */
final class VolumeBonus
{
    /**
     * Threshold in cents => percent credited. Highest first; the first match
     * wins, so the order here is the rule.
     *
     * @var array<int, int>
     */
    private const TIERS = [
        1_000_000 => 8,   // $10,000+
        500_000 => 6,     // $5,000+
        250_000 => 5,     // $2,500+
        100_000 => 3,     // $1,000+
    ];

    public function for(Money $amount): Money
    {
        foreach (self::TIERS as $threshold => $percent) {
            if ($amount->cents >= $threshold) {
                // intdiv, not round: a bonus is credited money, and rounding a
                // half-cent up on every top-up is a rounding error with a
                // direction.
                return Money::fromCents(intdiv($amount->cents * $percent, 100), $amount->currency);
            }
        }

        return Money::zero($amount->currency);
    }

    /**
     * The next tier up, when there is one worth naming.
     *
     * Only offered when the gap is small enough to be a nudge rather than an
     * upsell: nobody topping up $100 wants to hear about $10,000.
     *
     * @return array{addCents: int, atCents: int, bonusCents: int, percent: int}|null
     */
    public function nextTier(Money $amount): ?array
    {
        $tiers = array_reverse(self::TIERS, true);

        foreach ($tiers as $threshold => $percent) {
            if ($amount->cents >= $threshold) {
                continue;
            }

            // Within reach means within double what they were already adding,
            // or within $500 — whichever is more generous.
            $gap = $threshold - $amount->cents;

            if ($gap > max($amount->cents, 50_000)) {
                return null;
            }

            return [
                'addCents' => $gap,
                'atCents' => $threshold,
                'bonusCents' => intdiv($threshold * $percent, 100),
                'percent' => $percent,
            ];
        }

        return null;
    }

    /**
     * The whole ladder, for the copy beside the amount field.
     *
     * @return list<array{atCents: int, percent: int}>
     */
    public function tiers(): array
    {
        $tiers = [];

        foreach (array_reverse(self::TIERS, true) as $threshold => $percent) {
            $tiers[] = ['atCents' => $threshold, 'percent' => $percent];
        }

        return $tiers;
    }
}
