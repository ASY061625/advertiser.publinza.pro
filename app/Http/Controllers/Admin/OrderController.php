<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Orders/Index', [
            'orders' => Order::query()
                ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
                ->with('buyer')
                ->latest()
                ->paginate(50),
        ]);
    }

    public function show(Order $order): Response
    {
        return inertia('Orders/Show', ['order' => $order->load(['buyer', 'posts.site'])]);
    }

    public function refund(Order $order): RedirectResponse
    {
        $order->update(['status' => 'refunded']);

        return back()->with('success', 'Order refunded');
    }
}
