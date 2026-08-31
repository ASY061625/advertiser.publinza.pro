<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Models\Order;
use App\Domain\Catalog\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(): Response
    {
        return inertia('Dashboard', [
            'stats' => [
                'pendingSites' => Site::query()->where('status', 'pending')->count(),
                'openOrders' => Order::query()->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'payoutsDue' => 0,
            ],
        ]);
    }
}
