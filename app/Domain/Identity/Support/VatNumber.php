<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

/**
 * VAT number format checking, per country.
 *
 * Format only. This says a number is *shaped* like a Estonian or British VAT
 * number; it does not say the number exists or belongs to this company — that
 * needs VIES, which is a network call to a service with famously uneven
 * uptime, and is a job for a queued verification rather than a form field.
 *
 * Being clear about that boundary matters: a validator that says "valid" when
 * it means "well-formed" is how an invoice goes out with a reverse charge
 * applied against a number that was never registered.
 */
final class VatNumber
{
    /**
     * Country code => [pattern, human-readable example].
     *
     * @var array<string, array{string, string}>
     */
    private const FORMATS = [
        'AT' => ['/^ATU\d{8}$/', 'ATU12345678'],
        'BE' => ['/^BE0\d{9}$/', 'BE0123456789'],
        'BG' => ['/^BG\d{9,10}$/', 'BG123456789'],
        'CY' => ['/^CY\d{8}[A-Z]$/', 'CY12345678L'],
        'CZ' => ['/^CZ\d{8,10}$/', 'CZ12345678'],
        'DE' => ['/^DE\d{9}$/', 'DE123456789'],
        'DK' => ['/^DK\d{8}$/', 'DK12345678'],
        'EE' => ['/^EE\d{9}$/', 'EE123456789'],
        'ES' => ['/^ES[A-Z0-9]\d{7}[A-Z0-9]$/', 'ESX12345678'],
        'FI' => ['/^FI\d{8}$/', 'FI12345678'],
        'FR' => ['/^FR[A-Z0-9]{2}\d{9}$/', 'FRXX123456789'],
        'GB' => ['/^GB(\d{9}|\d{12}|GD\d{3}|HA\d{3})$/', 'GB123456789'],
        // Greece writes EL on the VAT number and GR everywhere else, so the
        // pattern accepts both prefixes under the ISO country code.
        'GR' => ['/^(EL|GR)\d{9}$/', 'EL123456789'],
        'HR' => ['/^HR\d{11}$/', 'HR12345678901'],
        'HU' => ['/^HU\d{8}$/', 'HU12345678'],
        'IE' => ['/^IE(\d{7}[A-W]{1,2}|\d[A-Z*+]\d{5}[A-W])$/', 'IE1234567A'],
        'IT' => ['/^IT\d{11}$/', 'IT12345678901'],
        'LT' => ['/^LT(\d{9}|\d{12})$/', 'LT123456789'],
        'LU' => ['/^LU\d{8}$/', 'LU12345678'],
        'LV' => ['/^LV\d{11}$/', 'LV12345678901'],
        'MT' => ['/^MT\d{8}$/', 'MT12345678'],
        'NL' => ['/^NL\d{9}B\d{2}$/', 'NL123456789B01'],
        'PL' => ['/^PL\d{10}$/', 'PL1234567890'],
        'PT' => ['/^PT\d{9}$/', 'PT123456789'],
        'RO' => ['/^RO\d{2,10}$/', 'RO1234567890'],
        'SE' => ['/^SE\d{12}$/', 'SE123456789001'],
        'SI' => ['/^SI\d{8}$/', 'SI12345678'],
        'SK' => ['/^SK\d{10}$/', 'SK1234567890'],
        'CH' => ['/^CHE\d{9}(MWST|TVA|IVA)?$/', 'CHE123456789MWST'],
        'NO' => ['/^NO\d{9}MVA$/', 'NO123456789MVA'],
    ];

    /** Spaces, dots and dashes are how people write these; strip them. */
    public static function normalise(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[\s.\-]/', '', trim($value)));
    }

    /**
     * @return bool True when the number is well-formed for the country, and
     *              also when the country has no known format — refusing a
     *              number we have no rule for would block real customers.
     */
    public static function matches(string $value, ?string $country): bool
    {
        $value = self::normalise($value);

        if ($value === '') {
            return true;
        }

        $country = $country === null ? null : mb_strtoupper($country);

        if ($country === null || ! isset(self::FORMATS[$country])) {
            // No rule, so only the shape common to all of them is checked: a
            // two-letter prefix and at least six more characters.
            return (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{6,}$/', $value);
        }

        return (bool) preg_match(self::FORMATS[$country][0], $value);
    }

    /** The example shown beside the field, so the rule is visible not guessed. */
    public static function exampleFor(?string $country): ?string
    {
        $country = $country === null ? null : mb_strtoupper($country);

        return $country !== null && isset(self::FORMATS[$country])
            ? self::FORMATS[$country][1]
            : null;
    }

    /**
     * @return list<string> Countries with a format rule, for the client hint.
     */
    public static function knownCountries(): array
    {
        return array_keys(self::FORMATS);
    }
}
