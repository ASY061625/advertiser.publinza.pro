<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\LoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes one row per sign-in attempt, successful or not.
 *
 * Every path through the login flow calls this — wrong password, locked out,
 * failed two-factor, success — so the table is a complete record rather than a
 * record of the cases someone remembered to log.
 */
final class RecordLoginAttempt
{
    public function __construct(private readonly Request $request) {}

    public function handle(string $email, bool $successful, string $guard = 'web'): LoginAttempt
    {
        return LoginAttempt::query()->create([
            'email' => Str::limit(mb_strtolower(trim($email)), 185, ''),
            'guard' => $guard,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'successful' => $successful,
        ]);
    }
}
