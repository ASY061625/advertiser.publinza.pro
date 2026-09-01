<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Display formatting for the server-rendered marketing site.
 *
 * The advertiser app formats in TypeScript (`resources/js/shared/lib/format.ts`);
 * the marketing site is Blade, so it needs the same rules in PHP. Keep the two
 * in step — a price shown as "$1,240.00" on the marketing page and "$1240" in
 * the app reads as two different products.
 */
final class Format
{
    /** 412_000 -> "412K". Used where a quant-bar carries the magnitude. */
    public static function compact(int $value): string
    {
        if ($value >= 1_000_000) {
            return rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'M';
        }

        if ($value >= 1_000) {
            return rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.').'K';
        }

        return (string) $value;
    }

    /** Money is stored in cents everywhere in this codebase. */
    public static function money(int $cents, string $currency = 'USD'): string
    {
        $symbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            default => '',
        };

        return $symbol.number_format($cents / 100, 2);
    }

    /** Whole dollars, for headline prices where cents are noise. */
    public static function moneyRounded(int $cents, string $currency = 'USD'): string
    {
        $symbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            default => '',
        };

        return $symbol.number_format((int) round($cents / 100));
    }
}
