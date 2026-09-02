<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Registrable-domain extraction, used to decide whether two URLs belong to the
 * same site.
 *
 * This is an approximation of the Public Suffix List, not the list itself.
 * The real PSL is ~9,000 rules and needs a package and a refresh schedule; what
 * is here covers the multi-label suffixes an advertiser is realistically going
 * to use (co.uk, com.au, com.br and the rest) and falls back to "last two
 * labels" for everything else.
 *
 * The failure mode is deliberate: an unknown multi-label suffix makes two
 * genuinely different sites look like the same registrable domain, so the
 * check is *permissive* where it is wrong. A landing-page rule that wrongly
 * blocks someone's own URL is worse than one that lets an unusual TLD through,
 * because this is a data-entry aid, not a security boundary.
 */
final class PublicSuffix
{
    /**
     * Second-level suffixes under which registrations happen. Anything matching
     * `*.<one of these>` needs three labels to be registrable.
     */
    private const MULTI_LABEL = [
        'co.uk', 'org.uk', 'me.uk', 'ltd.uk', 'plc.uk', 'net.uk', 'sch.uk', 'ac.uk', 'gov.uk',
        'com.au', 'net.au', 'org.au', 'edu.au', 'gov.au', 'id.au',
        'co.nz', 'net.nz', 'org.nz', 'govt.nz',
        'com.br', 'net.br', 'org.br', 'gov.br',
        'com.mx', 'com.ar', 'com.co', 'com.pe', 'com.ve', 'com.uy',
        'co.jp', 'ne.jp', 'or.jp', 'ac.jp', 'go.jp',
        'co.kr', 'or.kr', 'go.kr',
        'com.cn', 'net.cn', 'org.cn', 'gov.cn',
        'com.tw', 'com.hk', 'com.sg', 'com.my', 'com.ph', 'co.th', 'co.id', 'co.in', 'net.in', 'org.in',
        'co.za', 'org.za', 'com.tr', 'com.ua', 'com.pl', 'com.es', 'com.pt', 'com.gr',
        'co.il', 'com.sa', 'com.eg', 'com.ng', 'co.ke',
    ];

    /**
     * "shop.example.co.uk" → "example.co.uk". Null when the host is an IP or
     * has no registrable part.
     */
    public static function registrable(string $host): ?string
    {
        $host = strtolower(trim($host, ". \t\n\r\0\x0B"));

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $labels = explode('.', $host);

        if (count($labels) < 2) {
            return null;
        }

        $lastTwo = implode('.', array_slice($labels, -2));

        if (in_array($lastTwo, self::MULTI_LABEL, true)) {
            return count($labels) >= 3 ? implode('.', array_slice($labels, -3)) : null;
        }

        return $lastTwo;
    }

    /** Whether two hosts sit on the same registrable domain. */
    public static function sameSite(string $a, string $b): bool
    {
        $left = self::registrable($a);
        $right = self::registrable($b);

        return $left !== null && $left === $right;
    }
}
