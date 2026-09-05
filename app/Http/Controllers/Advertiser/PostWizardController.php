<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Actions\GetCatalogRanges;
use App\Domain\Catalog\Actions\SearchCatalog;
use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Catalog\Support\CatalogPresenter;
use App\Domain\Posts\Models\PostDraft;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Trading\Actions\AddPostToCart;
use App\Domain\Trading\Actions\PlaceOrder;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Exceptions\InsufficientFunds;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * The add-post wizard: the same purchase as the cart, entered from the post
 * side rather than the site side.
 *
 * It ends at a `cart_items` row either way — see AddPostToCart — so there is
 * one thing to price, one set of warnings and one order path. Everything here
 * is a JSON endpoint rather than a page, because the wizard is a modal that
 * opens over whatever the advertiser was already doing and returns them to it.
 */
class PostWizardController extends Controller
{
    /**
     * The result list scrolls rather than paginates: this is a picker, and a
     * pager inside a modal step is a second navigation nobody asked for. The
     * count above it says how many were left off.
     */
    private const RESULTS = 25;

    /**
     * Everything the wizard needs to render, in one request.
     *
     * Fetched when the modal opens rather than shared on every page, because
     * four projects' folders and landing pages are a real payload and the
     * wizard is opened on a small fraction of page views.
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'projects' => $this->projects($user),
            'categories' => WebsiteCategory::query()->orderBy('name')->get(['id', 'name'])->all(),
            'services' => array_map(static fn (ServiceType $service): array => [
                'value' => $service->value,
                'label' => $service->label(),
            ], ServiceType::cases()),
            'draft' => $this->draftPayload($user),
            'wallet' => [
                'availableCents' => $user->wallet?->available_cents ?? 0,
                'frozenCents' => $user->wallet?->frozen_cents ?? 0,
            ],
        ]);
    }

    /**
     * Step 2's compact catalog.
     *
     * The same SearchCatalog the full catalog runs, with the same filters
     * parsed the same way — so a search that finds a site here finds it there,
     * and "Open the full catalog" hands over a query string that reproduces
     * exactly what the picker was showing.
     */
    public function websites(
        Request $request,
        SearchCatalog $search,
        CatalogPresenter $presenter,
        GetCatalogRanges $ranges,
    ): JsonResponse {
        $user = $request->user();
        $filters = CatalogFilters::fromRequest($request)->with(['perPage' => self::RESULTS]);
        $project = $this->ownedProject($user, $request->integer('project') ?: null);

        // The project's targeting narrows the list before anybody types. A
        // picker that opens on the whole catalog makes the advertiser redo the
        // filtering their project already describes.
        if ($project !== null && ! $request->boolean('unseeded')) {
            $filters = $filters->seededFrom($project);
        }

        $page = $search->handle($filters, $user->id);

        return response()->json([
            'sites' => $presenter->handle($page->items(), $user->id, $project),
            'total' => $search->count($filters, $user->id),
            // The quant bars scale against the whole catalog, exactly as they
            // do in the full one — a bar that means something different inside
            // the modal than outside it would be worse than no bar.
            'ranges' => $ranges->handle()->toArray(),
            // What the picker is actually showing, so the hand-off link to the
            // full catalog carries it rather than guessing at it.
            'query' => $filters->toQuery(),
        ]);
    }

    /** One site, in the detail the summary strip shows once it is chosen. */
    public function website(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        $website->loadMissing('prices');
        $prices = $website->prices->map(static fn ($price): array => [
            'type' => $price->service_type->value,
            'label' => $price->service_type->label(),
            'priceCents' => $price->price_cents,
            'writingFeeCents' => $price->writing_fee_cents,
            'expressFeeCents' => $price->express_fee_cents,
        ])->values()->all();

        return response()->json([
            'id' => $website->id,
            'slug' => $website->slug,
            'domain' => $website->domain,
            'publicationHours' => $website->publication_period_hours,
            'linkType' => $website->link_type->value,
            'linksAllowed' => $website->links_allowed,
            'maxLinks' => $website->max_links,
            'minWords' => $website->min_words,
            // Absent on most sites. The step renders the select only where the
            // publisher has actually offered a choice of length.
            'wordCountTiers' => $website->word_count_tiers ?? [],
            'guidelines' => $website->guidelines,
            'services' => $prices,
        ]);
    }

    /**
     * Autosave. Deliberately silent, like the project wizard's: a save that
     * interrupted typing would cost more than the draft it protects.
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:4'],
            'payload' => ['required', 'array'],
        ]);

        PostDraft::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['step' => (int) $validated['step'], 'payload' => $validated['payload']],
        );

        return response()->json(['saved_at' => now()->toIso8601String()]);
    }

    public function discardDraft(Request $request): JsonResponse
    {
        PostDraft::query()->where('user_id', $request->user()->id)->delete();

        return response()->json(['discarded' => true]);
    }

    /**
     * The end of the wizard, both ways out.
     *
     * "Add to cart" leaves the line in the cart with everything else. "Place
     * order now" buys that one line and leaves the rest of the cart untouched —
     * which is what makes it safe to offer somebody who is halfway through
     * assembling a larger order.
     */
    public function store(Request $request, AddPostToCart $addToCart, PlaceOrder $placeOrder): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'intent' => ['required', 'in:cart,order'],
            'project_id' => ['required', 'integer'],
            'folder_id' => ['nullable', 'integer'],
            'landing_page_id' => ['nullable', 'integer'],
            'anchor_text' => ['nullable', 'string', 'max:190'],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'website_id' => ['required', 'integer'],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'content_mode' => ['required', Rule::enum(ContentMode::class)],
            'express' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:200000'],
            'brief' => ['nullable', 'string', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', 'string', 'max:190'],
            'target_words' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'article' => ['nullable', 'file', 'mimes:doc,docx,md,markdown,txt', 'max:'.AddPostToCart::MAX_UPLOAD_KB],
            'image' => ['nullable', 'image', 'max:'.AddPostToCart::MAX_UPLOAD_KB],
        ]);

        // A landing page is required in one of its two forms. Checked here
        // rather than in the rules because "one of these two" is not something
        // a per-field rule can say without repeating itself into both fields.
        if (($data['landing_page_id'] ?? null) === null && ($data['target_url'] ?? null) === null) {
            return back()->withErrors(['target_url' => 'Choose a saved landing page, or enter a URL.']);
        }

        try {
            $item = $addToCart->handle($user, $data, $request->file('article'), $request->file('image'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['website_id' => $exception->getMessage()]);
        }

        // The draft has become a real line, so it is no longer something to
        // resume. Cleared before the order, because an order that fails must
        // not leave a draft that would recreate the line on top of it.
        PostDraft::query()->where('user_id', $user->id)->delete();

        // No flash on this path, deliberately. The wizard shows its own toast
        // with an "Open cart" action the flash system cannot carry, and a
        // server message beside it would say the same thing twice.
        if ($data['intent'] === 'cart') {
            return back();
        }

        $cart = Cart::query()->firstWhere('user_id', $user->id);

        try {
            $order = $placeOrder->handle($user, $cart, $this->billingFor($user), [$item->id]);
        } catch (InsufficientFunds $exception) {
            // The line survives in the cart. Nothing was charged, and the
            // advertiser's work is one top-up away from being an order.
            return back()->with(
                'error',
                $exception->getMessage().' It is in your cart — top up your balance and check out.',
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'We could not place that order, and nothing was charged. It is waiting in your cart.',
            );
        }

        return to_route('checkout.success', $order->order_number);
    }

    /**
     * The advertiser's profile, as billing details.
     *
     * The wizard does not ask: it is a one-line order placed from a modal, and
     * interrupting it for an address the account already has would be asking a
     * question to which the answer is already on file. The full checkout is
     * where those details are editable.
     *
     * @return array<string, mixed>
     */
    private function billingFor(User $user): array
    {
        return [
            'name' => $user->name,
            'company' => $user->company,
            'email' => $user->email,
            'country' => $user->country,
            'vat_no' => $user->vat_no,
            'address' => null,
        ];
    }

    /**
     * The projects the wizard can file a post under, each with its folders and
     * saved landing pages.
     *
     * Archived projects are left out: they are read-only, so a post filed under
     * one would be work nobody can act on.
     *
     * @return list<array<string, mixed>>
     */
    private function projects(User $user): array
    {
        $projects = Project::query()
            ->where('user_id', $user->id)
            ->where('status', ProjectStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'website_url', 'publisher_task']);

        $ids = $projects->pluck('id');

        $folders = ProjectFolder::query()
            ->whereIn('project_id', $ids)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'project_id', 'name', 'publisher_task']);

        $pages = LandingPage::query()
            ->whereIn('project_id', $ids)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'project_id', 'folder_id', 'anchor_text', 'url']);

        return $projects->map(static fn (Project $project): array => [
            'id' => $project->id,
            'name' => $project->name,
            'color' => $project->color,
            'websiteUrl' => $project->website_url,
            // The brief the publisher-writes step prefills from. A folder
            // overrides its project, which is the rule the folder editor
            // already applies.
            'publisherTask' => $project->publisher_task,
            'folders' => $folders
                ->where('project_id', $project->id)
                ->map(static fn (ProjectFolder $folder): array => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'publisherTask' => $folder->publisher_task,
                ])
                ->values()
                ->all(),
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

    /**
     * @return array<string, mixed>|null
     */
    private function draftPayload(User $user): ?array
    {
        $draft = PostDraft::query()->where('user_id', $user->id)->first();

        return $draft === null ? null : [
            'step' => $draft->step,
            'payload' => $draft->payload,
            'savedAt' => $draft->updated_at?->toIso8601String(),
        ];
    }

    private function ownedProject(User $user, ?int $projectId): ?Project
    {
        if ($projectId === null) {
            return null;
        }

        return Project::query()
            ->with(['languages:id,name', 'sensitiveTopics:id,name,slug', 'countries:id,name'])
            ->where('user_id', $user->id)
            ->find($projectId);
    }
}
