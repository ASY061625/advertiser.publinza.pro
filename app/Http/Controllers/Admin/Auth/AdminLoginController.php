<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class AdminLoginController extends Controller
{
    public function create(): Response
    {
        return inertia('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials)) {
            // Deliberately vague: /asylogin is unlisted and should not confirm
            // whether an address belongs to a staff account.
            throw ValidationException::withMessages(['email' => 'Those details are not valid.']);
        }

        $request->session()->regenerate();
        $request->session()->forget('admin.two_factor_confirmed_at');

        return to_route('admin.two-factor');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }
}
