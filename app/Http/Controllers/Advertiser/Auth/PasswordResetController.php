<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser\Auth;

use App\Domain\Identity\Support\TrustedDevices;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\NetworkStats;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function requestForm(NetworkStats $stats): Response
    {
        return inertia('Auth/ForgotPassword', ['proofLines' => $stats->proofLines()]);
    }

    /**
     * Always answers the same way.
     *
     * A distinct "no account with that address" would turn this form into an
     * account-enumeration oracle, so the response, the status code and the
     * timing are identical whether or not the address is one of ours.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
        ], [
            'email.required' => 'Enter the email address you signed up with.',
            'email.email' => 'That address is missing something — check for a typo in the domain.',
        ]);

        Password::broker()->sendResetLink(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        return back()->with('status', 'If that address has an account, a reset link is on its way. '
            .'It works once and expires in 60 minutes.');
    }

    public function resetForm(Request $request, string $token, NetworkStats $stats): Response
    {
        return inertia('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'proofLines' => $stats->proofLines(),
        ]);
    }

    public function reset(ResetPasswordRequest $request, TrustedDevices $devices): RedirectResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($devices): void {
                $user->forceFill([
                    'password' => $password,
                    // A new remember token invalidates every "remember me"
                    // cookie already issued for this account.
                    'remember_token' => Str::random(60),
                ])->save();

                // Every device has to prove two-factor again after a reset:
                // whoever forced the reset may be sitting on a trusted browser.
                $devices->forgetAll($user);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // The broker deletes the token on use and expires it after 60
            // minutes, so both a reused and a stale link land here.
            throw ValidationException::withMessages([
                'email' => 'That reset link has already been used or has expired. Ask for a new one.',
            ]);
        }

        return redirect()->route('login')->with('status',
            'Your password is set. Sign in with it — you have been signed out everywhere else.');
    }
}
