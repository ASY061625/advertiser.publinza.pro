<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Support\LoginThrottle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The single place a sign-in is finalised.
 *
 * Both the password path and the two-factor path end here, so session
 * regeneration, throttle clearing, attempt logging and the first-login
 * redirect cannot drift apart between them.
 */
final class CompleteLogin
{
    public function __construct(
        private readonly Request $request,
        private readonly LoginThrottle $throttle,
        private readonly RecordLoginAttempt $recordAttempt,
    ) {}

    /**
     * @return string The path to send the advertiser to.
     */
    public function handle(User $user, bool $remember = false): string
    {
        // Read before writing: once last_login_at is stamped, the answer is
        // gone, and this is what decides where a brand-new account lands.
        $isFirstEver = ! $user->hasSignedInBefore();

        Auth::login($user, $remember);

        // Fresh session id, so a session fixed before sign-in is worthless.
        $this->request->session()->regenerate();
        $this->request->session()->forget(['auth.pending_user', 'auth.pending_remember']);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ])->save();

        $this->throttle->clear($user->email);
        $this->recordAttempt->handle($user->email, successful: true);

        // A first-ever sign-in has nothing to look at on the dashboard, so it
        // goes straight to creating the first project.
        return $isFirstEver ? '/projects/create' : '/dashboard';
    }
}
