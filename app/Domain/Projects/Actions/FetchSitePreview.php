<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\DTOs\ProjectWizardData;
use App\Domain\Projects\Support\UrlNormalizer;
use App\Support\OutboundUrlGuard;
use Illuminate\Support\Facades\Cache;

/**
 * Reads a site's title, description and favicon so the wizard can show the
 * advertiser what they just typed.
 *
 * Everything here is shaped by the fact that the URL comes from a form. The
 * request leaves from inside Publinza's network, so:
 *
 *   - OutboundUrlGuard vets the address, and the connection is pinned to the
 *     IP that was vetted (CURLOPT_RESOLVE). Handing curl the hostname instead
 *     would leave a window for the name to resolve again to something private.
 *   - Redirects are followed by hand, three at most, re-vetting every hop. A
 *     public URL that 302s to 169.254.169.254 is the standard bypass.
 *   - The body is capped and the transfer is time-boxed, so a slow or endless
 *     response cannot hold a worker open.
 *   - Only extracted fields are returned. The page body never reaches the
 *     client, so this cannot be used to read a response the browser could not
 *     have fetched itself.
 *
 * A failure is never an error page: the preview is a convenience, and the
 * wizard carries on without it.
 */
final class FetchSitePreview
{
    private const TIMEOUT_SECONDS = 6;

    private const MAX_BYTES = 512_000;

    private const MAX_REDIRECTS = 3;

    /**
     * @return array{ok: bool, url?: string, host?: string, title?: string|null, description?: string|null, favicon?: string|null, suggested_color?: string, reason?: string}
     */
    public function handle(string $rawUrl): array
    {
        $url = UrlNormalizer::normalize($rawUrl);

        if ($url === null) {
            return ['ok' => false, 'reason' => 'That does not look like a website address.'];
        }

        // Cached by URL, not by user: the answer is a property of the site.
        // Short, because a site someone is fixing a typo on should re-read soon.
        return Cache::remember(
            'site-preview:'.sha1($url),
            now()->addMinutes(10),
            fn (): array => $this->fetch($url),
        );
    }

    /**
     * @return array{ok: bool, url?: string, host?: string, title?: string|null, description?: string|null, favicon?: string|null, reason?: string}
     */
    private function fetch(string $url): array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $check = OutboundUrlGuard::check($current);

            if ($check['ok'] !== true) {
                return ['ok' => false, 'reason' => $check['reason']];
            }

            $result = $this->request($current, $check['host'], $check['ips']);

            if ($result === null) {
                return ['ok' => false, 'reason' => 'We could not reach that site just now.'];
            }

            [$status, $headers, $body] = $result;

            if (in_array($status, [301, 302, 303, 307, 308], true) && isset($headers['location'])) {
                $next = $this->absolute($headers['location'], $current);

                if ($next === null) {
                    return ['ok' => false, 'reason' => 'That site redirected somewhere we cannot follow.'];
                }

                $current = $next;

                continue;
            }

            if ($status >= 400) {
                return ['ok' => false, 'reason' => "That site answered with a {$status}. Check the address."];
            }

            return [
                'ok' => true,
                // The URL the preview describes is the one finally reached, so
                // a site that redirects www→apex previews the apex.
                'url' => $current,
                'host' => UrlNormalizer::hostOf($current),
                'title' => $this->title($body),
                'description' => $this->description($body),
                'favicon' => $this->favicon($body, $current),
                // Computed here rather than in the browser so the two cannot
                // disagree about what colour a domain suggests — the server
                // already applies this default when none is submitted.
                'suggested_color' => ProjectWizardData::defaultColorFor($current),
            ];
        }

        return ['ok' => false, 'reason' => 'That site redirected too many times.'];
    }

    /**
     * @param  list<string>  $ips
     * @return array{int, array<string, string>, string}|null
     */
    private function request(string $url, string $host, array $ips): ?array
    {
        $parts = parse_url($url);
        $port = (int) ($parts['port'] ?? (($parts['scheme'] ?? 'https') === 'http' ? 80 : 443));

        $handle = curl_init();
        $headers = [];
        $body = '';

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            // Pinned to the addresses the guard approved. curl connects to
            // these and never asks the resolver again.
            CURLOPT_RESOLVE => array_map(
                static fn (string $ip): string => "{$host}:{$port}:{$ip}",
                $ips,
            ),
            // Followed by hand instead, so every hop is re-vetted.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'PublinzaBot/1.0 (+https://publinza.pro/bot)',
            CURLOPT_ACCEPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($_, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
            // Returning less than the chunk length aborts the transfer, which
            // is how the body stays capped without buffering the whole page.
            CURLOPT_WRITEFUNCTION => function ($_, string $chunk) use (&$body): int {
                $body .= $chunk;

                return strlen($body) > self::MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);

        curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $failed = curl_errno($handle) !== 0 && $body === '';
        curl_close($handle);

        return $failed || $status === 0 ? null : [$status, $headers, $body];
    }

    private function absolute(string $location, string $base): ?string
    {
        $location = trim($location);

        if ($location === '') {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return UrlNormalizer::normalize($location);
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return UrlNormalizer::normalize(
            str_starts_with($location, '/')
                ? $origin.$location
                : $origin.'/'.ltrim($location, '/'),
        );
    }

    private function title(string $body): ?string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $body, $matches) === 1) {
            return $this->clean($matches[1], 160);
        }

        return $this->meta($body, 'og:title');
    }

    private function description(string $body): ?string
    {
        return $this->meta($body, 'description') ?? $this->meta($body, 'og:description');
    }

    /** Matches both `name=` and `property=`, in either attribute order. */
    private function meta(string $body, string $key): ?string
    {
        $escaped = preg_quote($key, '#');

        $patterns = [
            '#<meta[^>]+(?:name|property)\s*=\s*["\']'.$escaped.'["\'][^>]*content\s*=\s*["\']([^"\']*)["\']#is',
            '#<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*(?:name|property)\s*=\s*["\']'.$escaped.'["\']#is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches) === 1) {
                return $this->clean($matches[1], 300);
            }
        }

        return null;
    }

    private function favicon(string $body, string $url): ?string
    {
        if (preg_match('#<link[^>]+rel\s*=\s*["\'][^"\']*icon[^"\']*["\'][^>]*>#is', $body, $tag) === 1
            && preg_match('#href\s*=\s*["\']([^"\']+)["\']#i', $tag[0], $href) === 1) {
            $resolved = $this->absolute(html_entity_decode($href[1], ENT_QUOTES), $url);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $parts = parse_url($url);

        return isset($parts['host']) ? 'https://'.$parts['host'].'/favicon.ico' : null;
    }

    /**
     * Decoded, collapsed and truncated.
     *
     * The result is rendered as text in the preview card, never as markup —
     * this is a title element from a site we do not control.
     */
    private function clean(string $value, int $limit): ?string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
