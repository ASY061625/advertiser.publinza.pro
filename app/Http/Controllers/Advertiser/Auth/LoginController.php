<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser\Auth;

use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Actions\RecordLoginAttempt;
use App\Domain\Identity\Support\LoginThrottle;
use App\Domain\Identity\Support\TrustedDevices;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\Auth\LoginRequest;
use App\Models\User;
use App\Support\NetworkStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(NetworkStats $stats): Response
    {
        return inertia('Auth/Login', ['proofLines' => $stats->proofLines()]);
    }

    public function store(
        LoginRequest $request,
        LoginThrottle $throttle,
        TrustedDevices $devices,
        CompleteLogin $completeLogin,
        RecordLoginAttempt $recordAttempt,
    ): RedirectResponse {
        $email = (string) $request->validated('email');

        if ($seconds = $throttle->lockedFor($email)) {
            $recordAttempt->handle($email, successful: false);

            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.ceil($seconds / 60)
                    .' minutes, or reset your password now.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        // Hash a throwaway value when the account does not exist, so a missing
        // account and a wrong password take the same time. Without it, response
        // timing enumerates the user table.
        $passwordMatches = $user === null
            ? Hash::check((string) $request->validated('password'), Hash::make('timing-equaliser'))
            : Hash::check((string) $request->validated('password'), $user->password);

        if ($user === null || ! $passwordMatches) {
            $recordAttempt->handle($email, successful: false);
            $locked = $throttle->recordFailure($email, $user);

            throw ValidationException::withMessages([
                'email' => $locked
                    ? 'Too many attempts. Your account is locked for 15 minutes — we have emailed you about it.'
                    : 'That email and password do not match an account. Check both, or reset your password.',
            ]);
        }

        if (! $user->isActive()) {
            $recordAttempt->handle($email, successful: false);

            throw ValidationException::withMessages([
                'email' => 'This account is suspended. Email hello@publinza.pro and we will explain why.',
            ]);
        }

        // Two-factor is a second step, not a second field: nothing is signed in
        // until the challenge passes, so a stolen password alone gets nowhere.
        if ($user->hasTwoFactorEnabled() && ! $devices->isTrusted($user)) {
            $request->session()->put('auth.pending_user', $user->id);
            $request->session()->put('auth.pending_remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended($completeLogin->handle($user, $request->boolean('remember')));
    }

    public function destroy(Request $request, TrustedDevices $devices): RedirectResponse
    {
        $user = $request->user();

        // Signing out drops this browser's trust too. Someone who signs out on
        // a shared machine means it.
        $forget = $user === null ? null : $devices->forgetCurrent($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = redirect()->route('login');

        return $forget === null ? $response : $response->withCookie($forget);
    }
}
