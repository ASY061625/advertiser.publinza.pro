<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Catalog\Models\Website;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Actions\ApplyPromoCode;
use App\Domain\Trading\Actions\GetCart;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * The cart: one per advertiser, on the server.
 *
 * Server-side rather than in the browser, because the thing being assembled is
 * an order worth several hundred dollars across a dozen sites and it takes more
 * than one sitting to assemble. A cart that does not survive a logout, a second
 * device or a cleared browser is a cart people lose, and losing one is the kind
 * of failure they do not come back from.
 */
class CartController extends Controller
{
    public function index(Request $request, GetCart $getCart): Response
    {
        $user = $request->user();
        $wallet = $user->wallet;

        return inertia('Cart/Index', [
            'cart' => $getCart->handle($user),
            'wallet' => [
                'availableCents' => $wallet?->available_cents ?? 0,
                'frozenCents' => $wallet?->frozen_cents ?? 0,
            ],
            // The move-to-project menu, and the project picker in the editor.
            'projects' => $this->projects($user),
        ]);
    }

    public function store(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('purchase', $website);

        $data = $request->validate([
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'content_mode' => ['required', Rule::enum(ContentMode::class)],
            'project_id' => ['nullable', 'integer'],
            'folder_id' => ['nullable', 'integer'],
            'anchor_text' => ['nullable', 'string', 'max:190'],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'express' => ['nullable', 'boolean'],
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
            'project_id' => $this->ownedProjectId($request->user(), $data['project_id'] ?? null),
            'express' => (bool) ($data['express'] ?? false),
            // What this line was quoted, kept so the cart can say the price
            // moved. It is not what gets charged — see CartPricer.
            'unit_price_cents' => $price->price_cents,
        ]);

        return back()->with('success', "Added {$website->domain} to your cart.");
    }

    /** Reopens the configuration popover on a line already in the cart. */
    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->owns($request, $item);

        $data = $request->validate([
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'content_mode' => ['required', Rule::enum(ContentMode::class)],
            'project_id' => ['nullable', 'integer'],
            'folder_id' => ['nullable', 'integer'],
            'anchor_text' => ['nullable', 'string', 'max:190'],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'express' => ['nullable', 'boolean'],
        ]);

        $item->update([
            ...$data,
            'project_id' => $this->ownedProjectId($request->user(), $data['project_id'] ?? null),
            'express' => (bool) ($data['express'] ?? false),
        ]);

        return back()->with('success', 'Updated.');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->owns($request, $item);

        $domain = $item->website?->domain ?? 'That site';
        $item->delete();

        return back()->with('success', "Removed {$domain} from your cart.");
    }

    /**
     * Remove or reassign several lines at once.
     *
     * Both operations are scoped to the caller's own cart in the query itself
     * rather than checked per row, so a request naming somebody else's line
     * ids simply affects nothing.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:remove,move'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $cart = Cart::query()->firstWhere('user_id', $request->user()->id);

        if ($cart === null) {
            return back();
        }

        $query = CartItem::query()->where('cart_id', $cart->id)->whereIn('id', $data['ids']);

        if ($data['action'] === 'remove') {
            $count = $query->count();
            $query->delete();

            return back()->with('success', $this->plural($count, 'line').' removed.');
        }

        $projectId = $this->ownedProjectId($request->user(), $data['project_id'] ?? null);

        if ($projectId === null) {
            return back()->with('error', 'Choose a project to move them to.');
        }

        // The folder belongs to the old project, so it cannot come along. Left
        // set, it would point a line at a folder in a project it is no longer
        // part of, and the checkout would file the post in the wrong place.
        $count = $query->count();
        $query->update(['project_id' => $projectId, 'folder_id' => null]);

        $name = Project::query()->whereKey($projectId)->value('name');

        return back()->with('success', $this->plural($count, 'line')." moved to {$name}.");
    }

    /** Hides one advisory warning on one line, for good. */
    public function dismissWarning(Request $request, CartItem $item): RedirectResponse
    {
        $this->owns($request, $item);

        $kind = $request->validate([
            'kind' => ['required', 'string', 'max:32'],
        ])['kind'];

        $item->update([
            'dismissed_warnings' => array_values(array_unique([...$item->dismissed_warnings ?? [], $kind])),
        ]);

        return back();
    }

    public function applyPromo(Request $request, ApplyPromoCode $apply, GetCart $getCart): RedirectResponse
    {
        $code = $request->validate(['code' => ['required', 'string', 'max:48']])['code'];

        $cart = $getCart->cart($request->user());

        if ($cart === null || $cart->items->isEmpty()) {
            return back()->with('error', 'Add something to your cart first.');
        }

        $totals = $getCart->fromCart($cart)['totals'];
        // Against the pre-discount total, so re-entering a code cannot compound
        // with the one already on the cart.
        $subtotal = new Money($totals['totalCents'] + $totals['discountCents']);

        $result = $apply->handle($request->user(), $cart, $code, $subtotal);

        return $result['ok']
            ? back()->with('success', $result['message'])
            : back()->withErrors(['code' => $result['message']]);
    }

    public function removePromo(Request $request): RedirectResponse
    {
        Cart::query()->where('user_id', $request->user()->id)->update(['promo_code_id' => null]);

        return back()->with('success', 'Promo code removed.');
    }

    /**
     * The projects a line can belong to.
     *
     * Archived projects are left out: they are read-only, so moving a line into
     * one would put an order against something that cannot be worked on.
     *
     * @return list<array<string, mixed>>
     */
    private function projects(User $user): array
    {
        $projects = Project::query()
            ->where('user_id', $user->id)
            ->where('status', ProjectStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $pages = LandingPage::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'project_id', 'folder_id', 'anchor_text', 'url']);

        return $projects->map(static fn (Project $project): array => [
            'id' => $project->id,
            'name' => $project->name,
            'color' => $project->color,
            'folders' => $project->folders()->orderBy('sort_order')->orderBy('id')->get(['id', 'name'])->all(),
            'landingPages' => $pages
                ->where('project_id', $project->id)
                ->map(static fn (LandingPage $page): array => [
                    'id' => $page->id,
                    'folderId' => $page->folder_id,
                    'anchorText' => $page->anchor_text,
                    'url' => $page->url,
                ])
                ->values()
                ->all(),
        ])->all();
    }

    /** Null unless the project exists and belongs to this advertiser. */
    private function ownedProjectId(User $user, ?int $projectId): ?int
    {
        if ($projectId === null) {
            return null;
        }

        return Project::query()
            ->where('user_id', $user->id)
            ->whereKey($projectId)
            ->value('id');
    }

    private function owns(Request $request, CartItem $item): void
    {
        abort_unless($item->cart->user_id === $request->user()->id, 403);
    }

    private function plural(int $count, string $noun): string
    {
        return $count.' '.$noun.($count === 1 ? '' : 's');
    }
}
