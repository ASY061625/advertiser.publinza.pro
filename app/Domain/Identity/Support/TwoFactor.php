<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP setup and verification.
 *
 * The secret is encrypted at rest rather than hashed, because verification
 * needs the original value to recompute the current code.
 */
final class TwoFactor
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /** Starts setup. Not enabled until a code is confirmed. */
    public function generateSecret(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey(32);

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    public function secretFor(User $user): ?string
    {
        return $user->two_factor_secret === null ? null : Crypt::decryptString($user->two_factor_secret);
    }

    /**
     * The otpauth:// URI. On a phone this is a tappable link straight into the
     * authenticator; on a desktop it is what a QR code would encode.
     */
    public function provisioningUri(User $user): ?string
    {
        $secret = $this->secretFor($user);

        if ($secret === null) {
            return null;
        }

        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name'),
            $user->email,
            $secret,
        );
    }

    /**
     * Verifies a six-digit code.
     *
     * `window: 1` accepts the previous and next 30-second step, which covers
     * ordinary clock drift between a phone and the server. Wider than that and
     * a shoulder-surfed code stays usable for too long.
     */
    public function verify(User $user, string $code): bool
    {
        $secret = $this->secretFor($user);

        if ($secret === null) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, preg_replace('/\D/', '', $code) ?? '', 1);
    }

    /** Marks setup complete once the first code has been proven. */
    public function confirm(User $user): void
    {
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Trusted devices only exist to skip the challenge. With 2FA off they
        // are dead weight, and leaving them lets a re-enable be bypassed.
        $user->trustedDevices()->delete();
    }
}
