<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Catalog\Enums\PublicationSpeed;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Actions\GetCart;
use App\Domain\Trading\Actions\PlaceOrder;
use App\Domain\Trading\Models\CartItem;
use App\Domain\Trading\Models\Order;
use App\Exceptions\InsufficientFunds;
use App\Http\Controllers\Controller;
use App\Support\ArticleText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Checkout, in three steps.
 *
 * The step lives in the query string rather than in component state, so a
 * refresh, a back button and a shared link all land where the buyer was. The
 * cart itself is the progress: nothing is written anywhere else until the last
 * step, and abandoning the checkout at step two leaves a cart with two articles
 * already staged in it.
 */
class CheckoutController extends Controller
{
    private const STEPS = ['review', 'content', 'confirm'];

    /** Uploads are a document, not an image, and not a large one. */
    private const MAX_UPLOAD_KB = 5120;

    public function index(Request $request, GetCart $getCart): Response|RedirectResponse
    {
        $user = $request->user();
        $cart = $getCart->cart($user);

        if ($cart === null || $cart->items->isEmpty()) {
            return to_route('cart.index')->with('error', 'Your cart is empty.');
        }

        $step = in_array($request->query('step'), self::STEPS, true)
            ? (string) $request->query('step')
            : 'review';

        $payload = $getCart->fromCart($cart);
        $wallet = $user->wallet;

        return inertia('Checkout/Index', [
            'step' => $step,
            'steps' => self::STEPS,
            'cart' => $payload,
            'content' => $this->contentState($cart->items),
            'wallet' => [
                'availableCents' => $wallet?->available_cents ?? 0,
                'frozenCents' => $wallet?->frozen_cents ?? 0,
            ],
            // Prefilled from the profile and editable: the invoice snapshots
            // whatever is submitted here, so an advertiser billing through a
            // holding company does not have to change their account to do it.
            'billing' => [
                'name' => $user->name,
                'company' => $user->company,
                'email' => $user->email,
                'country' => $user->country,
                'vat_no' => $user->vat_no,
                'address' => null,
            ],
        ]);
    }

    /**
     * Stages one article against one cart line.
     *
     * Written to the cart rather than held in the browser so the content step
     * survives a reload — a buyer who has pasted four articles and lost the
     * fifth to a refresh has lost all five, because they will not do it again.
     */
    public function saveArticle(Request $request, CartItem $item): RedirectResponse
    {
        $this->owns($request, $item);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:200000'],
            'file' => ['nullable', 'file', 'mimes:doc,docx,md,markdown,txt', 'max:'.self::MAX_UPLOAD_KB],
        ]);

        $file = $request->file('file');

        if ($file === null && trim((string) ($data['body'] ?? '')) === '') {
            return back()->withErrors(['body' => 'Paste the article, or upload a file.']);
        }

        if ($file !== null) {
            // Private disk: an unpublished article is the advertiser's, and a
            // guessable public URL would hand it to anyone before it runs.
            $path = $file->store("articles/{$item->cart->user_id}", 'local');
            $text = ArticleText::fromFile($file);

            $item->update([
                'article_title' => $data['title'] ?? null ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'article_body_html' => $text,
                'article_word_count' => ArticleText::countWords($text),
                'article_file_path' => $path,
            ]);

            return back()->with('success', 'Article uploaded.');
        }

        $body = (string) ($data['body'] ?? '');

        $item->update([
            'article_title' => ($data['title'] ?? null) ?: null,
            'article_body_html' => $body,
            'article_word_count' => ArticleText::countWords($body),
            'article_file_path' => null,
        ]);

        return back()->with('success', 'Article saved.');
    }

    public function clearArticle(Request $request, CartItem $item): RedirectResponse
    {
        $this->owns($request, $item);

        if ($item->article_file_path !== null) {
            Storage::disk('local')->delete($item->article_file_path);
        }

        $item->update([
            'article_title' => null,
            'article_body_html' => null,
            'article_word_count' => null,
            'article_file_path' => null,
        ]);

        return back()->with('success', 'Article removed.');
    }

    /**
     * Pays.
     *
     * Everything that can fail is inside PlaceOrder's transaction, so a failure
     * here leaves the cart exactly as it was. That matters more than the error
     * message: an advertiser whose payment failed and whose cart also vanished
     * has lost an hour of work to a problem that was not theirs.
     */
    public function store(Request $request, GetCart $getCart, PlaceOrder $placeOrder): RedirectResponse
    {
        $user = $request->user();
        $cart = $getCart->cart($user);

        if ($cart === null || $cart->items->isEmpty()) {
            return to_route('cart.index')->with('error', 'Your cart is empty.');
        }

        $billing = $request->validate([
            'billing.name' => ['required', 'string', 'max:190'],
            'billing.company' => ['nullable', 'string', 'max:190'],
            'billing.email' => ['required', 'email', 'max:190'],
            'billing.country' => ['nullable', 'string', 'max:64'],
            'billing.vat_no' => ['nullable', 'string', 'max:64'],
            'billing.address' => ['nullable', 'string', 'max:500'],
            'terms' => ['accepted'],
        ])['billing'];

        try {
            $order = $placeOrder->handle($user, $cart, $billing);
        } catch (InsufficientFunds $exception) {
            return back()->with(
                'error',
                $exception->getMessage().' Top up your balance and try again — your cart is untouched.',
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'We could not place that order, and nothing was charged. Your cart is exactly as you left it — please try again.',
            );
        }

        return to_route('checkout.success', $order->order_number);
    }

    public function success(Request $request, string $order): Response
    {
        $record = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_number', $order)
            ->with(['posts.website', 'posts.project'])
            ->firstOrFail();

        return inertia('Checkout/Success', [
            'order' => [
                'number' => $record->order_number,
                'subtotalCents' => $record->subtotal_cents,
                'discountCents' => $record->discount_cents,
                'totalCents' => $record->total_cents,
                'placedAt' => $record->paid_at?->toIso8601String(),
                'invoiceNumber' => str_replace('PZ-', 'INV-', $record->order_number),
            ],
            'posts' => $record->posts->map(static fn (Post $post): array => [
                'id' => $post->id,
                'domain' => $post->website?->domain ?? '',
                'project' => $post->project?->name,
                'status' => $post->status->value,
                'statusLabel' => $post->status->label(),
                'isDraft' => $post->status === PostStatus::Draft,
                'priceCents' => $post->price_cents,
                // The window, not a date. A publication period is a promise
                // about a range, and printing one day implies a precision the
                // publisher has not offered.
                'window' => PublicationSpeed::describe($post->website?->publication_period_hours ?? 0),
                'deadlineAt' => $post->deadline_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * The invoice, as a plain-text document.
     *
     * Generated on demand from the order rather than stored as a PDF at
     * checkout: an invoice nobody downloads is a file nobody needed, and the
     * order rows are already the authority for every figure on it. The billing
     * block comes from the order's own snapshot, so an advertiser who has since
     * changed their company details still gets the invoice as it was issued.
     */
    public function invoice(Request $request, string $order): StreamedResponse
    {
        $record = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('order_number', $order)
            ->with(['posts.website'])
            ->firstOrFail();

        $number = str_replace('PZ-', 'INV-', $record->order_number);

        return response()->streamDownload(function () use ($record, $number): void {
            $out = fopen('php://output', 'wb');
            $billing = $record->billing_details ?? [];

            fwrite($out, "PUBLINZA\n");
            fwrite($out, "Invoice {$number}\n");
            fwrite($out, 'Issued '.($record->paid_at?->format('j F Y') ?? '—')."\n");
            fwrite($out, "Order {$record->order_number}\n\n");

            fwrite($out, "BILLED TO\n");

            foreach (['name', 'company', 'address', 'country', 'vat_no', 'email'] as $field) {
                if (! empty($billing[$field])) {
                    fwrite($out, $billing[$field]."\n");
                }
            }

            fwrite($out, "\nPLACEMENTS\n");

            foreach ($record->posts as $post) {
                fwrite($out, sprintf(
                    "%-40s %12s\n",
                    $post->website?->domain ?? 'Site removed',
                    (new Money($post->price_cents, $record->currency))->format(),
                ));
            }

            fwrite($out, "\n");
            fwrite($out, sprintf("%-40s %12s\n", 'Subtotal', (new Money($record->subtotal_cents, $record->currency))->format()));

            if ($record->discount_cents > 0) {
                fwrite($out, sprintf("%-40s %12s\n", 'Discount', '-'.(new Money($record->discount_cents, $record->currency))->format()));
            }

            fwrite($out, sprintf("%-40s %12s\n\n", 'Total', (new Money($record->total_cents, $record->currency))->format()));
            fwrite($out, "Paid from your Publinza balance. Funds are held against each\n");
            fwrite($out, "placement and released to the publisher only once the link is\n");
            fwrite($out, "verified as live.\n");

            fclose($out);
        }, "{$number}.txt", ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Which lines still need an article, in the shape the step reads.
     *
     * A line the publisher writes is complete on arrival — there is nothing for
     * the buyer to do — and saying so beside the ones that do need work is what
     * turns "twelve items" into "three to write".
     *
     * @param  Collection<int, CartItem>  $items
     * @return array<string, mixed>
     */
    private function contentState($items): array
    {
        $rows = $items->map(static function (CartItem $item): array {
            $minWords = $item->website?->min_words ?? 0;
            $words = $item->article_word_count;
            $publisherWrites = $item->content_mode->incursWritingFee();

            return [
                'itemId' => $item->id,
                'domain' => $item->website?->domain ?? '',
                'publisherWrites' => $publisherWrites,
                'minWords' => $minWords,
                'wordCount' => $words,
                'title' => $item->article_title,
                'fileName' => $item->article_file_path === null
                    ? null
                    : basename($item->article_file_path),
                'body' => $item->article_file_path === null ? $item->article_body_html : null,
                // Three states, not two. "Too short" is not the same as "not
                // started", and a buyer who pasted 400 words against a 1,200
                // minimum needs to be told which of those they are in.
                'state' => match (true) {
                    $publisherWrites => 'not_needed',
                    $words === null => 'empty',
                    $words < $minWords => 'short',
                    default => 'ready',
                },
            ];
        })->values();

        return [
            'items' => $rows->all(),
            'needed' => $rows->where('state', '!=', 'not_needed')->count(),
            'ready' => $rows->where('state', 'ready')->count(),
        ];
    }

    private function owns(Request $request, CartItem $item): void
    {
        abort_unless($item->cart->user_id === $request->user()->id, 403);
    }
}
