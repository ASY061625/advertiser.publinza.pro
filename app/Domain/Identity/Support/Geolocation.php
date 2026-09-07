<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Domain\Catalog\Models\Country;
use Illuminate\Http\Request;

/**
 * Roughly where a request came from.
 *
 * Read from the edge's own header rather than looked up here. Cloudflare,
 * Fastly and most load balancers already resolve the client IP to a country
 * and pass it down; doing it again in PHP would mean either a per-request
 * network call or shipping and updating a GeoIP database, and both are worse
 * than reading a header the infrastructure already sets.
 *
 * Where no such header exists — local development, a bare origin — this returns
 * null and every screen says "Unknown" rather than guessing. A location that is
 * quietly wrong is worse than one that is honestly missing, because the whole
 * point of showing it is for somebody to spot a session that is not theirs.
 */
final class Geolocation
{
    public static function countryFor(Request $request): ?string
    {
        $header = (string) config('publinza.geolocation.country_header');

        if ($header === '') {
            return null;
        }

        $value = mb_strtoupper(trim((string) $request->header($header)));

        // Cloudflare sends XX for anonymising proxies and T1 for Tor.
        if ($value === '' || in_array($value, ['XX', 'T1'], true) || ! preg_match('/^[A-Z]{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /** "United Kingdom" for GB, falling back to the code we were given. */
    public static function name(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return Country::query()->where('code', $code)->value('name') ?? $code;
    }
}
