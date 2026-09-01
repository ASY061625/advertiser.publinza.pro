<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Billing\Actions\GetWalletBalance;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleAdvertiserInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly GetWalletBalance $getWalletBalance) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            // The wallet chip sits in the header on every advertiser screen.
            'balanceCents' => fn (): int => $user === null
                ? 0
                : $this->getWalletBalance->handle($user)->cents,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
