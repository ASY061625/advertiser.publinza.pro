<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Billing\Actions\FreezeFundsForOrder;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Order;
use App\Domain\Catalog\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('purchase', $site);

        $cart = $request->session()->get('cart', []);
        $cart[$site->id] = true;
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Added to cart');
    }

    public function destroy(Request $request, int $item): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$item]);
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Removed from cart');
    }

    public function checkout(Request $request, FreezeFundsForOrder $freezeFunds): RedirectResponse
    {
        /** @var array<int, bool> $cart */
        $cart = $request->session()->get('cart', []);

        if ($cart === []) {
            return back()->with('error', 'Your cart is empty. Add a site from the catalog first.');
        }

        $user = $request->user();
        $sites = Site::query()->findMany(array_keys($cart));
        $total = (int) $sites->sum('price_minor_units');

        $order = DB::transaction(function () use ($user, $total, $freezeFunds): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'total_minor_units' => $total,
                'status' => 'new',
                'currency' => 'USD',
            ]);

            $freezeFunds->handle($user, new Money($total), $order->id);

            return $order;
        });

        $request->session()->forget('cart');

        return to_route('posts.index')->with('success', "Order #{$order->id} placed");
    }
}
