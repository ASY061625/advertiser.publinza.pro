<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class TwoFactorController extends Controller
{
    public function create(): Response
    {
        return inertia('Auth/TwoFactor');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $admin = Auth::guard('admin')->user();

        if ($admin === null || ! $admin->hasTwoFactorEnabled()) {
            return to_route('admin.login');
        }

        $valid = Google2FA::verifyKey(
            decrypt($admin->two_factor_secret),
            $request->string('code')->value(),
        );

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Wait for the next code and try again.',
            ]);
        }

        $request->session()->put('admin.two_factor_confirmed_at', now()->toIso8601String());

        return to_route('admin.overview');
    }
}
