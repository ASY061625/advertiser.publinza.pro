<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decides whether the server may fetch a URL a user typed.
 *
 * Fetching a user-supplied URL server-side is a server-side request forgery
 * primitive: the request comes from inside the network, with whatever the
 * network trusts. Left open it reaches cloud metadata endpoints
 * (169.254.169.254), databases on the private range, admin panels bound to
 * localhost, and anything else the firewall lets the app talk to.
 *
 * So the rule is an allowlist of shapes, not a blocklist of hosts:
 *   - http and https only;
 *   - the host must resolve, and *every* address it resolves to must be a
 *     public unicast address;
 *   - the caller fetches by pinned IP, never by hostname, so the address that
 *     was checked is the address that is connected to.
 *
 * That last point is the one usually missed. Checking the DNS answer and then
 * handing the hostname to curl leaves a window in which the name can resolve
 * again to something private — DNS rebinding. `resolve()` returns the
 * addresses so the caller can pin them.
 */
final class OutboundUrlGuard
{
    /**
     * Ranges that must never be reachable. Loopback, private, link-local
     * (which is where cloud metadata lives), carrier-grade NAT, and the IPv6
     * equivalents including the mapped and translated IPv4 forms.
     */
    private const BLOCKED_V4 = [
        ['0.0.0.0', 8],        // "this network"
        ['10.0.0.0', 8],       // private
        ['100.64.0.0', 10],    // carrier-grade NAT
        ['127.0.0.0', 8],      // loopback
        ['169.254.0.0', 16],   // link-local — cloud metadata
        ['172.16.0.0', 12],    // private
        ['192.0.0.0', 24],     // IETF protocol assignments
        ['192.0.2.0', 24],     // TEST-NET-1
        ['192.168.0.0', 16],   // private
        ['198.18.0.0', 15],    // benchmarking
        ['198.51.100.0', 24],  // TEST-NET-2
        ['203.0.113.0', 24],   // TEST-NET-3
        ['224.0.0.0', 4],      // multicast
        ['240.0.0.0', 4],      // reserved, includes 255.255.255.255
    ];

    /**
     * @return array{ok: true, host: string, ips: list<string>}|array{ok: false, reason: string}
     */
    public static function check(string $url): array
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return ['ok' => false, 'reason' => 'Only http and https addresses can be checked.'];
        }

        if (! is_string($host) || $host === '') {
            return ['ok' => false, 'reason' => 'That address has no hostname in it.'];
        }

        $host = strtolower($host);

        // A literal IP skips DNS entirely, so check it directly. This is also
        // the path that catches http://127.0.0.1 and http://[::1].
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublic($host)
                ? ['ok' => true, 'host' => $host, 'ips' => [$host]]
                : ['ok' => false, 'reason' => 'That address points inside a private network.'];
        }

        $ips = self::resolve($host);

        if ($ips === []) {
            return ['ok' => false, 'reason' => 'That domain does not resolve. Check the spelling.'];
        }

        // Every answer must be public. One private address among several is
        // enough to make the fetch unsafe, because which one gets used is not
        // ours to decide.
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                return ['ok' => false, 'reason' => 'That domain points inside a private network.'];
            }
        }

        return ['ok' => true, 'host' => $host, 'ips' => $ips];
    }

    /**
     * @return list<string>
     */
    public static function resolve(string $host): array
    {
        $ips = [];

        foreach (['A', 'AAAA'] as $type) {
            $records = @dns_get_record($host, $type === 'A' ? DNS_A : DNS_AAAA);

            if (! is_array($records)) {
                continue;
            }

            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;

                if (is_string($ip) && $ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        return array_values(array_unique($ips));
    }

    public static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::isPublicV4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        return self::isPublicV6($ip);
    }

    private static function isPublicV4(string $ip): bool
    {
        $value = ip2long($ip);

        if ($value === false) {
            return false;
        }

        foreach (self::BLOCKED_V4 as [$network, $bits]) {
            $base = ip2long($network);

            if ($base === false) {
                continue;
            }

            $mask = $bits === 0 ? 0 : -1 << (32 - $bits);

            if (($value & $mask) === ($base & $mask)) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicV6(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }

        // ::, ::1
        if ($packed === str_repeat("\0", 16) || $packed === str_repeat("\0", 15)."\1") {
            return false;
        }

        // An IPv4-mapped or IPv4-compatible address is an IPv4 address wearing
        // a hat: ::ffff:127.0.0.1 must be judged as 127.0.0.1.
        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff") || str_starts_with($packed, str_repeat("\0", 12))) {
            $v4 = inet_ntop(substr($packed, 12));

            return is_string($v4) && self::isPublicV4($v4);
        }

        $first = ord($packed[0]);
        $second = ord($packed[1]);

        // fc00::/7 unique-local, fe80::/10 link-local.
        if (($first & 0xFE) === 0xFC || ($first === 0xFE && ($second & 0xC0) === 0x80)) {
            return false;
        }

        // 64:ff9b::/96 NAT64 and 2002::/16 6to4 both wrap an IPv4 address that
        // would otherwise go unchecked.
        if (str_starts_with($packed, "\x00\x64\xff\x9b".str_repeat("\0", 8))) {
            $v4 = inet_ntop(substr($packed, 12));

            return is_string($v4) && self::isPublicV4($v4);
        }

        if ($first === 0x20 && $second === 0x02) {
            $v4 = inet_ntop(substr($packed, 2, 4));

            return is_string($v4) && self::isPublicV4($v4);
        }

        // 2001:db8::/32 documentation.
        return ! str_starts_with($packed, "\x20\x01\x0d\xb8");
    }
}
