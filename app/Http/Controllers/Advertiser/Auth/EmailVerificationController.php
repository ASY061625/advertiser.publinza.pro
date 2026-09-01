<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser\Auth;

use App\Http\Controllers\Controller;
use App\Support\NetworkStats;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function notice(Request $request, NetworkStats $stats): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return inertia('Auth/VerifyEmail', [
            'email' => $request->user()->email,
            'proofLines' => $stats->proofLines(),
            // Lets the button start out disabled with a live countdown rather
            // than failing when someone taps it twice.
            'resendAvailableIn' => RateLimiter::availableIn($this->resendKey($request)),
        ]);
    }

    /** The signed link from the email. */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('dashboard')->with('status', 'Email confirmed.');
    }

    /** One resend per 60 seconds, per account. */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $key = $this->resendKey($request);

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 1)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->with('status', "We already sent one. You can ask for another in {$seconds} seconds.");
        }

        RateLimiter::hit($key, 60);
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Sent. Check your inbox, and your spam folder if it is not there.');
    }

    private function resendKey(Request $request): string
    {
        return 'verify-resend:'.$request->user()->id;
    }
}
