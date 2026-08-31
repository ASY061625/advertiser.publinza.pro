<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser\Auth;

use App\Domain\Billing\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return inertia('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'company' => ['nullable', 'string', 'max:190'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create($data);

            // Every advertiser gets a wallet up front so the header chip and
            // checkout never have to branch on a missing record.
            Wallet::query()->create([
                'user_id' => $user->id,
                'balance_minor_units' => 0,
                'frozen_minor_units' => 0,
                'currency' => 'USD',
            ]);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        return to_route('dashboard');
    }
}
