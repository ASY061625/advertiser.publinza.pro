<?php

declare(strict_types=1);

namespace App\Domain\Projects\Support;

/**
 * One canonical spelling for a URL an advertiser typed.
 *
 * People type "Example.COM", "http://example.com/" and "example.com" meaning
 * the same site. Storing them as typed would make the same site look like three
 * different ones to the landing-page check and to any future dedupe, so
 * everything is normalised on the way in: https, lower-cased host, no default
 * port, no trailing slash on a bare host, no fragment.
 *
 * The path keeps its case. Hosts are case-insensitive by DNS; paths are not,
 * and lower-casing one can break the link.
 */
final class UrlNormalizer
{
    public static function normalize(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        // A bare host is what most people type. Assume https rather than
        // rejecting it — http would be a downgrade nobody asked for.
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');

        // Only the two schemes a website can be served over. Anything else —
        // javascript:, data:, file: — is not a website.
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = self::host($parts['host']);

        if ($host === null) {
            return null;
        }

        $url = 'https://'.$host;

        // A non-default port is meaningful and kept; 80 and 443 are noise.
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            $url .= ':'.(int) $parts['port'];
        }

        $path = rtrim($parts['path'] ?? '', '/');

        if ($path !== '' && $path !== '/') {
            $url .= $path;
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $url .= '?'.$parts['query'];
        }

        // The fragment never reaches the server, so it cannot identify a page
        // for anything Publinza does with it.
        return $url;
    }

    /** Lower-cased, IDN-encoded, and actually shaped like a hostname. */
    public static function host(string $host): ?string
    {
        $host = strtolower(trim($host, '.'));

        if ($host === '') {
            return null;
        }

        // Internationalised domains are stored punycoded, so "münchen.de" and
        // "xn--mnchen-3ya.de" do not read as two different sites.
        if (preg_match('/[^\x20-\x7f]/', $host) === 1 && function_exists('idn_to_ascii')) {
            $encoded = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($encoded) && $encoded !== '') {
                $host = $encoded;
            }
        }

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if (! $isIp && ! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
            return null;
        }

        return $host;
    }

    /** The host of an already-normalised URL. */
    public static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : null;
    }
}
