<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser\Auth;

use App\Domain\Identity\Support\RecoveryCodes;
use App\Domain\Identity\Support\SecurityLog;
use App\Domain\Identity\Support\TwoFactor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Turning two-factor on and off. Optional: an advertiser who never visits this
 * screen signs in with a password alone.
 */
class TwoFactorSettingsController extends Controller
{
    /** Starts setup. Nothing is enforced until a code is confirmed. */
    public function enable(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $twoFactor->generateSecret($request->user());

        return back();
    }

    public function confirm(Request $request, TwoFactor $twoFactor, SecurityLog $log): RedirectResponse
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
        $log->record($user, 'two_factor.enabled');

        // Shown once, in this response, and never retrievable again.
        return back()->with('recoveryCodes', RecoveryCodes::generate($user));
    }

    /**
     * Regenerating invalidates every previously issued code.
     *
     * Password-gated for the same reason disabling is: a hijacked session that
     * can mint a fresh set of recovery codes has minted itself eight standing
     * bypasses, and locked the real owner out of the set they hold on paper.
     */
    public function regenerateRecoveryCodes(Request $request, SecurityLog $log): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasTwoFactorEnabled(), 403);

        $this->confirmPassword($request, 'Enter your password to generate new recovery codes.');

        $log->record($user, 'two_factor.recovery_codes_regenerated');

        return back()->with('recoveryCodes', RecoveryCodes::generate($user));
    }

    public function disable(Request $request, TwoFactor $twoFactor, SecurityLog $log): RedirectResponse
    {
        $user = $request->user();

        /*
         * Only a *confirmed* second factor costs a password to remove. While
         * setup is still pending the secret protects nothing yet, and this same
         * endpoint is what the Cancel button calls — asking for a password to
         * abandon a half-finished setup is a dead end, not a safeguard.
         */
        if ($user->hasTwoFactorEnabled()) {
            $this->confirmPassword($request, 'Enter your password to turn two-factor off.');
            $log->record($user, 'two_factor.disabled');
        }

        $twoFactor->disable($user);

        return back()->with('status', 'Two-factor authentication is off.');
    }

    /**
     * @throws ValidationException
     */
    private function confirmPassword(Request $request, string $message): void
    {
        $request->validate(['password' => ['required', 'string']], ['password.required' => $message]);

        if (! Hash::check((string) $request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'That password does not match. Try again, or reset it if you have forgotten it.',
            ]);
        }
    }
}
