<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Billing\Actions\FreezeFundsForOrder;
use App\Domain\Catalog\Models\Website;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Enums\OrderStatus;
use App\Domain\Trading\Enums\PaidFrom;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Domain\Trading\Models\Order;
use App\Exceptions\InsufficientFunds;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function store(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('purchase', $website);

        $data = $request->validate([
            'service_type' => ['required', 'string'],
            'content_mode' => ['required', 'string'],
            'project_id' => ['nullable', 'integer'],
            'folder_id' => ['nullable', 'integer'],
            'anchor_text' => ['nullable', 'string', 'max:190'],
            'target_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $service = ServiceType::from($data['service_type']);
        $price = $website->priceFor($service);

        if ($price === null) {
            return back()->with('error', 'This site does not offer that service.');
        }

        $cart = Cart::query()->firstOrCreate(['user_id' => $request->user()->id]);

        CartItem::query()->create([
            ...$data,
            'cart_id' => $cart->id,
            'website_id' => $website->id,
            // Snapshotted so a later price change cannot alter the quote.
            'unit_price_cents' => $price->price_cents,
        ]);

        return back()->with('success', 'Added to cart');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        abort_unless($item->cart->user_id === $request->user()->id, 403);

        $item->delete();

        return back()->with('success', 'Removed from cart');
    }

    public function checkout(Request $request, FreezeFundsForOrder $freezeFunds): RedirectResponse
    {
        $user = $request->user();
        $cart = Cart::query()->with('items')->firstWhere('user_id', $user->id);

        if ($cart === null || $cart->items->isEmpty()) {
            return back()->with('error', 'Your cart is empty. Add a site from the catalog first.');
        }

        try {
            $order = DB::transaction(function () use ($user, $cart, $freezeFunds): Order {
                $subtotal = (int) $cart->items->sum('unit_price_cents');

                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'order_number' => Order::generateNumber(),
                    'subtotal_cents' => $subtotal,
                    'discount_cents' => 0,
                    'total_cents' => $subtotal,
                    'currency' => 'USD',
                    'status' => OrderStatus::Paid,
                    'paid_from' => PaidFrom::Wallet,
                    'paid_at' => now(),
                ]);

                // Throws InsufficientFunds under the wallet's row lock.
                $freezeFunds->handle($user, $order);

                foreach ($cart->items as $item) {
                    $post = Post::query()->create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'project_id' => $item->project_id,
                        'folder_id' => $item->folder_id,
                        'website_id' => $item->website_id,
                        'status' => PostStatus::Draft,
                        'anchor_text' => $item->anchor_text,
                        'target_url' => $item->target_url,
                        'content_mode' => $item->content_mode,
                        'price_cents' => $item->unit_price_cents,
                        'deadline_at' => now()->addHours($item->website->publication_period_hours),
                    ]);

                    // Paying moves it out of draft immediately.
                    $post->transitionTo(PostStatus::New, "Order {$order->order_number}");
                }

                $cart->items()->delete();

                return $order;
            });
        } catch (InsufficientFunds $exception) {
            return back()->with('error', $exception->getMessage().' Top up your balance to continue.');
        }

        return to_route('posts.index')->with('success', "Order {$order->order_number} placed");
    }
}
