<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser\Auth;

use App\Domain\Identity\Actions\CompleteLogin;
use App\Domain\Identity\Actions\RecordLoginAttempt;
use App\Domain\Identity\Support\LoginThrottle;
use App\Domain\Identity\Support\RecoveryCodes;
use App\Domain\Identity\Support\TrustedDevices;
use App\Domain\Identity\Support\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\NetworkStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/**
 * The second step of sign-in.
 *
 * The advertiser is not authenticated yet: the pending user id lives in the
 * session and only CompleteLogin turns it into a signed-in state.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request, NetworkStats $stats): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        return inertia('Auth/TwoFactorChallenge', [
            'proofLines' => $stats->proofLines(),
            'recoveryCodesLeft' => RecoveryCodes::remaining($user),
        ]);
    }

    public function store(
        Request $request,
        TwoFactor $twoFactor,
        TrustedDevices $devices,
        LoginThrottle $throttle,
        CompleteLogin $completeLogin,
        RecordLoginAttempt $recordAttempt,
    ): RedirectResponse {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'trust_device' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Enter the six-digit code from your authenticator app.',
        ]);

        $code = (string) $request->input('code');

        // A code with a dash is a recovery code; six digits is the app.
        $passed = str_contains($code, '-')
            ? RecoveryCodes::consume($user, $code)
            : $twoFactor->verify($user, $code);

        if (! $passed) {
            $recordAttempt->handle($user->email, successful: false);
            $locked = $throttle->recordFailure($user->email, $user);

            if ($locked) {
                // Drop the pending state: a locked-out attempt should not leave
                // a half-open sign-in sitting in the session.
                $request->session()->forget(['auth.pending_user', 'auth.pending_remember']);

                throw ValidationException::withMessages([
                    'code' => 'Too many attempts. Your account is locked for 15 minutes — we have emailed you.',
                ]);
            }

            throw ValidationException::withMessages([
                'code' => 'That code did not work. Codes change every 30 seconds, so wait for the next one '
                    .'and try again, or use a recovery code.',
            ]);
        }

        $remember = (bool) $request->session()->get('auth.pending_remember', false);
        $path = $completeLogin->handle($user, $remember);

        $response = redirect()->intended($path);

        return $request->boolean('trust_device')
            ? $response->withCookie($devices->remember($user))
            : $response;
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('auth.pending_user');

        return is_int($id) || is_string($id) ? User::query()->find($id) : null;
    }
}
