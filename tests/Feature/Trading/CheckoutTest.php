<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PromoCode;
use App\Domain\Billing\Models\PromoRedemption;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Article;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Models\CartItem;
use App\Domain\Trading\Models\Order;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/** A verified advertiser with a funded wallet — most of checkout assumes both. */
function payer(int $cents = 1_000_00): User
{
    $user = buyer();

    Wallet::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['available_cents' => $cents, 'frozen_cents' => 0, 'currency' => 'USD'],
    );

    return $user->fresh();
}

/**
 * @return array{0: User, 1: Project, 2: CartItem}
 */
function readyToBuy(int $price = 200_00, array $lineAttributes = []): array
{
    $user = payer();
    $project = Project::factory()->for($user, 'owner')->create(['name' => 'Ledgerly']);
    $item = line($user, priced($price), $lineAttributes + [
        'project_id' => $project->id,
        'anchor_text' => 'invoicing software',
        'target_url' => 'https://ledgerly.test/invoicing',
    ]);

    return [$user, $project, $item];
}

/**
 * @return array<string, mixed>
 */
function billing(array $overrides = []): array
{
    return ['billing' => $overrides + [
        'name' => 'Dana Okonkwo',
        'email' => 'dana@ledgerly.test',
        'company' => 'Ledgerly Ltd',
        'country' => 'GB',
    ], 'terms' => true];
}

it('walks the three steps from one URL', function (): void {
    [$user] = readyToBuy();

    foreach (['review', 'content', 'confirm'] as $step) {
        $this->actingAs($user)
            ->get(advertiserUrl("/checkout?step={$step}"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Checkout/Index')
                ->where('step', $step),
            );
    }

    // An unknown step is the first one rather than a 404: the step is a hint
    // about where to resume, not a resource that can be missing.
    $this->actingAs($user)
        ->get(advertiserUrl('/checkout?step=nonsense'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('step', 'review'));
});

it('sends an empty cart back rather than into a checkout with nothing in it', function (): void {
    $this->actingAs(payer())
        ->get(advertiserUrl('/checkout'))
        ->assertRedirect(advertiserUrl('/cart'));
});

it('marks publisher-written lines done and counts only what the buyer owes', function (): void {
    $user = payer();
    $project = Project::factory()->for($user, 'owner')->create();

    line($user, priced(100_00, writing: 40_00, attributes: ['min_words' => 800]), [
        'project_id' => $project->id,
        'content_mode' => ContentMode::PublisherWrites,
    ]);
    line($user, priced(120_00, attributes: ['min_words' => 800]), ['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(advertiserUrl('/checkout?step=content'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            // Two lines, one of which needs nothing from the buyer. "One
            // article to write" is the useful number, not "two items".
            ->where('content.needed', 1)
            ->where('content.ready', 0)
            ->where('content.items.0.state', 'not_needed')
            ->where('content.items.1.state', 'empty'),
        );
});

it('counts words and calls a short draft short rather than done', function (): void {
    [$user, , $item] = readyToBuy(lineAttributes: []);
    $item->website->update(['min_words' => 50]);

    $this->actingAs($user)
        ->post(advertiserUrl("/checkout/{$item->id}/article"), [
            'title' => 'Ten words',
            'body' => str_repeat('word ', 10),
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(advertiserUrl('/checkout?step=content'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('content.items.0.wordCount', 10)
            ->where('content.items.0.state', 'short')
            ->where('content.ready', 0),
        );

    $this->actingAs($user)
        ->post(advertiserUrl("/checkout/{$item->id}/article"), ['body' => str_repeat('word ', 60)])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(advertiserUrl('/checkout?step=content'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('content.items.0.state', 'ready')
            ->where('content.ready', 1),
        );
});

it('reads the words out of an uploaded document', function (): void {
    Storage::fake('local');

    [$user, , $item] = readyToBuy();
    $item->website->update(['min_words' => 5]);

    $this->actingAs($user)
        ->post(advertiserUrl("/checkout/{$item->id}/article"), [
            'file' => UploadedFile::fake()->createWithContent(
                'draft.md',
                "# A heading\n\nSix more words go here now.",
            ),
        ])
        ->assertRedirect();

    // The count is the point of reading the file at all: a publisher's minimum
    // is a real acceptance criterion, and "we did not look inside" costs a
    // round trip through the publisher days later.
    expect($item->fresh()->article_word_count)->toBe(9)
        ->and($item->fresh()->article_file_path)->not->toBeNull();
});

it('refuses an article that is neither pasted nor uploaded', function (): void {
    [$user, , $item] = readyToBuy();

    $this->actingAs($user)
        ->post(advertiserUrl("/checkout/{$item->id}/article"), ['body' => '   '])
        ->assertSessionHasErrors('body');
});

it('keeps one advertiser out of another’s article', function (): void {
    [, , $item] = readyToBuy();

    $this->flushSession();

    $this->actingAs(payer())
        ->post(advertiserUrl("/checkout/{$item->id}/article"), ['body' => str_repeat('word ', 50)])
        ->assertForbidden();
});

it('places the order, freezes the money and empties the cart, all at once', function (): void {
    Notification::fake();

    [$user, $project, $item] = readyToBuy(200_00);
    $item->update([
        'article_title' => 'A draft',
        'article_body_html' => str_repeat('word ', 900),
        'article_word_count' => 900,
    ]);

    $this->actingAs($user)
        ->post(advertiserUrl('/checkout'), billing())
        ->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect($order->total_cents)->toBe(200_00)
        ->and($order->billing_details['company'])->toBe('Ledgerly Ltd');

    $post = Post::query()->firstOrFail();

    expect($post->order_id)->toBe($order->id)
        ->and($post->project_id)->toBe($project->id)
        ->and($post->status)->toBe(PostStatus::New)
        ->and($post->price_cents)->toBe(200_00);

    // The staged article came with it.
    expect(Article::query()->where('post_id', $post->id)->value('word_count'))->toBe(900);

    // Frozen, not spent: the balance moves between buckets and the wallet's
    // total holdings are unchanged.
    $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();

    expect($wallet->available_cents)->toBe(800_00)
        ->and($wallet->frozen_cents)->toBe(200_00)
        ->and(Transaction::query()->where('type', TransactionType::Freeze)->count())->toBe(1);

    expect(CartItem::query()->count())->toBe(0);
    expect(Invoice::query()->where('order_id', $order->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, OrderPlacedNotification::class);
});

it('leaves a line with no article as a draft rather than refusing the order', function (): void {
    Notification::fake();

    $user = payer();
    $project = Project::factory()->for($user, 'owner')->create();

    $written = line($user, priced(100_00, writing: 40_00), [
        'project_id' => $project->id,
        'content_mode' => ContentMode::PublisherWrites,
    ]);
    line($user, priced(120_00), ['project_id' => $project->id]);

    $this->actingAs($user)->post(advertiserUrl('/checkout'), billing())->assertRedirect();

    $posts = Post::query()->with('website')->get()->keyBy(fn (Post $post): int => $post->website_id);

    // The publisher writes one of them, so it needs nothing and goes into the
    // queue. The other is waiting on the buyer and stays a draft — nothing
    // happens on it until they submit the copy.
    expect($posts[$written->website_id]->status)->toBe(PostStatus::New)
        ->and($posts->except($written->website_id)->first()->status)->toBe(PostStatus::Draft);

    // Both are paid for either way: the money is frozen against the order.
    expect(Wallet::query()->where('user_id', $user->id)->value('frozen_cents'))->toBe(260_00);
});

it('rolls everything back and keeps the cart when the balance is short', function (): void {
    Notification::fake();

    $user = payer(50_00);
    $project = Project::factory()->for($user, 'owner')->create();
    line($user, priced(200_00), ['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(advertiserUrl('/checkout'), billing())
        ->assertRedirect()
        ->assertSessionHas('error');

    // Nothing partial survives: no order, no post, no invoice, no ledger row —
    // and, most of all, the cart is exactly as it was.
    expect(Order::query()->count())->toBe(0)
        ->and(Post::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(CartItem::query()->count())->toBe(1);

    $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();

    expect($wallet->available_cents)->toBe(50_00)->and($wallet->frozen_cents)->toBe(0);

    Notification::assertNothingSent();
});

it('redeems the promo code once and records it against the order', function (): void {
    Notification::fake();

    [$user] = readyToBuy(200_00);

    $promo = PromoCode::query()->create([
        'code' => 'SPRING20',
        'percent_off' => 20,
        'minimum_spend_cents' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(advertiserUrl('/cart/promo'), ['code' => 'SPRING20']);
    $this->actingAs($user)->post(advertiserUrl('/checkout'), billing())->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect($order->subtotal_cents)->toBe(200_00)
        ->and($order->discount_cents)->toBe(40_00)
        ->and($order->total_cents)->toBe(160_00)
        // Only the discounted total is frozen. Freezing the subtotal would take
        // money the advertiser does not owe.
        ->and(Wallet::query()->where('user_id', $user->id)->value('frozen_cents'))->toBe(160_00)
        ->and(PromoRedemption::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($promo->fresh()->redemptions_count)->toBe(1);
});

it('refuses to place an order without the terms box', function (): void {
    [$user] = readyToBuy();

    $this->actingAs($user)
        ->post(advertiserUrl('/checkout'), [...billing(), 'terms' => false])
        ->assertSessionHasErrors('terms');

    expect(Order::query()->count())->toBe(0);
});

it('shows the receipt with a publication window per site', function (): void {
    Notification::fake();

    [$user] = readyToBuy();

    $this->actingAs($user)->post(advertiserUrl('/checkout'), billing());

    $order = Order::query()->firstOrFail();

    $this->actingAs($user)
        ->get(advertiserUrl("/checkout/{$order->order_number}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Checkout/Success')
            ->where('order.number', $order->order_number)
            ->where('order.totalCents', 200_00)
            ->has('posts', 1)
            // A window, not a date: a publication period is a promise about a
            // range, and one day implies a precision nobody offered.
            ->where('posts.0.window', fn (string $window): bool => $window !== ''),
        );
});

it('keeps one advertiser out of another’s receipt and invoice', function (): void {
    Notification::fake();

    [$user] = readyToBuy();
    $this->actingAs($user)->post(advertiserUrl('/checkout'), billing());

    $order = Order::query()->firstOrFail();

    // A fresh session before switching advertiser: AuthenticateSession is in
    // the advertiser stack and logs out a second user reusing the first one's
    // session, which would answer 302 before the lookup ever runs.
    $this->flushSession();
    $outsider = payer();

    $this->actingAs($outsider)->get(advertiserUrl("/checkout/{$order->order_number}"))->assertNotFound();
    $this->actingAs($outsider)->get(advertiserUrl("/checkout/{$order->order_number}/invoice"))->assertNotFound();
});

it('bills the invoice to what was submitted, not to the profile', function (): void {
    Notification::fake();

    [$user] = readyToBuy();

    $this->actingAs($user)->post(advertiserUrl('/checkout'), billing(['company' => 'A Holding Company Ltd']));

    $order = Order::query()->firstOrFail();

    // Then the advertiser changes their profile. The invoice must not follow:
    // a receipt has to keep saying what it said when it was issued.
    $user->update(['company' => 'Something Else Entirely']);

    $body = $this->actingAs($user)
        ->get(advertiserUrl("/checkout/{$order->order_number}/invoice"))
        ->assertOk()
        ->streamedContent();

    expect($body)->toContain('A Holding Company Ltd')
        ->and($body)->not->toContain('Something Else Entirely');
});
