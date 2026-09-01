<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Five attempts per email and IP per minute, then a 15-minute lockout.
 *
 * Keyed on the pair rather than on either alone: keying on email only lets one
 * attacker lock a victim out of their own account from anywhere, and keying on
 * IP only lets a botnet spread a credential-stuffing run across addresses. The
 * pair costs an attacker a fresh address for every five guesses.
 *
 * The lockout is a separate cache entry from the rate limiter, because the
 * limiter's own window is a minute and the lockout has to outlive it.
 */
final class LoginThrottle
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    private const LOCKOUT_SECONDS = 900;

    public function __construct(private readonly Request $request) {}

    /** Seconds remaining on a lockout, or null when the account is not locked. */
    public function lockedFor(string $email): ?int
    {
        $until = Cache::get($this->lockoutKey($email));

        if ($until === null) {
            return null;
        }

        $remaining = (int) $until - now()->getTimestamp();

        return $remaining > 0 ? $remaining : null;
    }

    public function isLocked(string $email): bool
    {
        return $this->lockedFor($email) !== null;
    }

    /**
     * Records a failure and locks the pair once the allowance is spent.
     *
     * Returns true when this failure triggered the lockout, so the caller can
     * tell the person how long they are waiting rather than repeating "those
     * details did not match".
     */
    public function recordFailure(string $email, ?User $user): bool
    {
        RateLimiter::hit($this->limiterKey($email), self::DECAY_SECONDS);

        if (RateLimiter::attempts($this->limiterKey($email)) < self::MAX_ATTEMPTS) {
            return false;
        }

        $lockedUntil = now()->addSeconds(self::LOCKOUT_SECONDS);
        $justLocked = Cache::add($this->lockoutKey($email), $lockedUntil->getTimestamp(), $lockedUntil);

        // Notify once per lockout, not once per subsequent attempt, and only
        // when the address belongs to a real account — mailing a stranger about
        // an account they do not have is both noise and an enumeration signal.
        if ($justLocked && $user !== null) {
            $user->notify(new AccountLockedNotification(
                ipAddress: (string) $this->request->ip(),
                userAgent: (string) $this->request->userAgent(),
                minutes: (int) (self::LOCKOUT_SECONDS / 60),
            ));
        }

        return $justLocked;
    }

    /** Called on a successful sign-in: the allowance resets, the lockout clears. */
    public function clear(string $email): void
    {
        RateLimiter::clear($this->limiterKey($email));
        Cache::forget($this->lockoutKey($email));
    }

    public function attemptsLeft(string $email): int
    {
        return max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($this->limiterKey($email)));
    }

    private function limiterKey(string $email): string
    {
        return 'login:'.$this->fingerprint($email);
    }

    private function lockoutKey(string $email): string
    {
        return 'login-lockout:'.$this->fingerprint($email);
    }

    /** Hashed so raw addresses are not sitting in cache keys. */
    private function fingerprint(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)).'|'.$this->request->ip());
    }
}
