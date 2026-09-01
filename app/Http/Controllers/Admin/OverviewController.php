<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Website;
use App\Domain\Trading\Enums\OrderStatus;
use App\Domain\Trading\Models\Order;
use App\Http\Controllers\Controller;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(): Response
    {
        return inertia('Dashboard', [
            'stats' => [
                'pendingSites' => Website::query()->where('is_active', false)->count(),
                'openOrders' => Order::query()->whereNotIn('status', [OrderStatus::Refunded, OrderStatus::Cancelled])->count(),
                'payoutsDue' => 0,
            ],
        ]);
    }
}
