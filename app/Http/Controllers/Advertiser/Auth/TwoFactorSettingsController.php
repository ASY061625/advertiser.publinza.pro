<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser\Auth;

use App\Domain\Identity\Support\RecoveryCodes;
use App\Domain\Identity\Support\TwoFactor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/**
 * Turning two-factor on and off. Optional: an advertiser who never visits this
 * screen signs in with a password alone.
 */
class TwoFactorSettingsController extends Controller
{
    public function show(Request $request, TwoFactor $twoFactor): Response
    {
        $user = $request->user();

        return inertia('Settings/TwoFactor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'secret' => $user->two_factor_confirmed_at === null ? $twoFactor->secretFor($user) : null,
            'provisioningUri' => $user->two_factor_confirmed_at === null ? $twoFactor->provisioningUri($user) : null,
            'recoveryCodesLeft' => RecoveryCodes::remaining($user),
        ]);
    }

    /** Starts setup. Nothing is enforced until a code is confirmed. */
    public function enable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $twoFactor->generateSecret($request->user());

        return back();
    }

    public function confirm(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:16']], [
            'code.required' => 'Enter the six-digit code your authenticator is showing.',
        ]);

        $user = $request->user();

        if (! $twoFactor->verify($user, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'That code did not work. Check the clock on your phone is correct, '
                    .'wait for the next code and try again.',
            ]);
        }

        $twoFactor->confirm($user);

        // Shown once, in this response, and never retrievable again.
        return back()->with('recoveryCodes', RecoveryCodes::generate($user));
    }

    /** Regenerating invalidates every previously issued code. */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasTwoFactorEnabled(), 403);

        return back()->with('recoveryCodes', RecoveryCodes::generate($request->user()));
    }

    public function disable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        // Re-authenticate: turning off a security control is exactly the action
        // a hijacked session would take.
        $request->validate(['password' => ['required', 'string']], [
            'password.required' => 'Enter your password to turn two-factor off.',
        ]);

        if (! \Illuminate\Support\Facades\Hash::check((string) $request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'That password does not match. Try again, or reset it if you have forgotten it.',
            ]);
        }

        $twoFactor->disable($request->user());

        return back()->with('status', 'Two-factor authentication is off.');
    }
}
