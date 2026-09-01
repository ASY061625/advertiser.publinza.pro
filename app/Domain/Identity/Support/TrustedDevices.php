<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * "Trust this device for 30 days" — skipping the two-factor challenge on a
 * browser the person has already proven once.
 */
final class TrustedDevices
{
    public const COOKIE = 'publinza_device';

    private const DAYS = 30;

    public function __construct(private readonly Request $request) {}

    /** True when this browser holds a live, unexpired token for the user. */
    public function isTrusted(User $user): bool
    {
        $device = $this->resolve($user);

        if ($device === null) {
            return false;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    /**
     * Issues a token and returns the cookie to attach to the response.
     *
     * httpOnly so script cannot read it, SameSite=Lax so it is not sent on
     * cross-site POSTs, and Secure wherever HTTPS is served.
     */
    public function remember(User $user): SymfonyCookie
    {
        $token = Str::random(64);

        TrustedDevice::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::DAYS),
        ]);

        return Cookie::make(
            name: self::COOKIE,
            value: $token,
            minutes: self::DAYS * 24 * 60,
            path: '/',
            domain: null,
            secure: (bool) config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    /** Called on sign-out from this device only. */
    public function forgetCurrent(User $user): SymfonyCookie
    {
        $this->resolve($user)?->delete();

        return Cookie::forget(self::COOKIE);
    }

    /** Called when the password changes: every device has to prove itself again. */
    public function forgetAll(User $user): void
    {
        $user->trustedDevices()->delete();
    }

    private function resolve(User $user): ?TrustedDevice
    {
        $token = $this->request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return TrustedDevice::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }
}
