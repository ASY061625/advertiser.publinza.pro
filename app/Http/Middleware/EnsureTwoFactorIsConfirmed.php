<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsConfirmed
{
    /**
     * Admins confirm a TOTP code once per session. The confirmation is stored
     * in the session rather than on the record so that it expires with the
     * session itself.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('admin.two_factor_confirmed_at') === null) {
            return redirect()->route('admin.two-factor');
        }

        return $next($request);
    }
}
