<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleAdminInertiaRequests extends Middleware
{
    /**
     * The admin root template is the only one that references the admin entry
     * point, which is what keeps admin code out of the advertiser bundle.
     */
    protected $rootView = 'admin';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $admin = Auth::guard('admin')->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name').' admin',
            'auth' => [
                'admin' => $admin === null ? null : [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
