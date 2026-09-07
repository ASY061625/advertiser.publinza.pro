<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

/**
 * A user agent string, reduced to "Chrome on macOS".
 *
 * Not a user-agent parsing library, and it does not try to be one: the job is
 * to help somebody recognise their own laptop in a list of four sessions. A
 * wrong guess costs a moment of confusion; a dependency that ships a
 * regularly-updated regex database costs a dependency.
 *
 * Order matters in both lists below — Edge and Opera both claim to be Chrome,
 * and Chrome claims to be Safari, so the specific names are tested first.
 */
final class DeviceLabel
{
    /** @var list<array{string, string}> */
    private const BROWSERS = [
        ['/\bEdg[A-Z]?\//i', 'Edge'],
        ['/\bOPR\/|\bOpera\b/i', 'Opera'],
        ['/\bSamsungBrowser\//i', 'Samsung Internet'],
        ['/\bFirefox\/|\bFxiOS\//i', 'Firefox'],
        ['/\bCriOS\//i', 'Chrome'],
        // 'HeadlessChrome/' has no word boundary before 'Chrome', so the
        // compound name is spelled out rather than left to \b.
        ['/\b(?:Headless)?Chrome\//i', 'Chrome'],
        ['/\bSafari\//i', 'Safari'],
        ['/\bcurl\//i', 'curl'],
        ['/\bPostmanRuntime\//i', 'Postman'],
    ];

    /** @var list<array{string, string}> */
    private const PLATFORMS = [
        ['/\biPhone\b/i', 'iPhone'],
        ['/\biPad\b/i', 'iPad'],
        ['/\bAndroid\b/i', 'Android'],
        ['/\bWindows NT 1[01]/i', 'Windows 11'],
        ['/\bWindows NT\b/i', 'Windows'],
        ['/\bMac OS X\b|\bMacintosh\b/i', 'macOS'],
        ['/\bCrOS\b/i', 'ChromeOS'],
        ['/\bLinux\b/i', 'Linux'],
    ];

    /**
     * @return array{browser: string, platform: string, label: string}
     */
    public static function parse(?string $userAgent): array
    {
        $agent = trim((string) $userAgent);

        if ($agent === '') {
            return ['browser' => 'Unknown', 'platform' => 'Unknown', 'label' => 'Unknown device'];
        }

        $browser = self::firstMatch(self::BROWSERS, $agent) ?? 'Unknown browser';
        $platform = self::firstMatch(self::PLATFORMS, $agent) ?? 'Unknown device';

        return [
            'browser' => $browser,
            'platform' => $platform,
            'label' => $platform === 'Unknown device' ? $browser : "{$browser} on {$platform}",
        ];
    }

    /**
     * @param  list<array{string, string}>  $patterns
     */
    private static function firstMatch(array $patterns, string $agent): ?string
    {
        foreach ($patterns as [$pattern, $name]) {
            if (preg_match($pattern, $agent)) {
                return $name;
            }
        }

        return null;
    }
}
