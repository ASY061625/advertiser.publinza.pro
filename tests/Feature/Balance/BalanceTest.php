<?php

declare(strict_types=1);

use App\Domain\Billing\Actions\StartTopUp;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\DeclineReason;
use App\Domain\Billing\Enums\TopUpStatus;
use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentMethod;
use App\Domain\Billing\Models\TopUp;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Billing\Support\LedgerReconciler;
use App\Domain\Billing\Support\VolumeBonus;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Models\Order;
use App\Models\User;
use App\Notifications\Publinza\TopUpConfirmedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function walletFor(User $user, int $available = 0, int $frozen = 0): Wallet
{
    return Wallet::query()->create([
        'user_id' => $user->id,
        'available_cents' => $available,
        'frozen_cents' => $frozen,
        'currency' => 'USD',
    ]);
}

function card(User $user, array $attributes = []): PaymentMethod
{
    return PaymentMethod::query()->create($attributes + [
        'user_id' => $user->id,
        'provider' => 'stripe',
        'provider_reference' => 'pm_'.uniqid(),
        'brand' => 'Visa',
        'last_four' => '4242',
        'exp_month' => 12,
        'exp_year' => (int) now()->addYears(3)->year,
        'is_default' => true,
    ]);
}

// ---------------------------------------------------------------- the page

it('opens on the overview and deep-links to each tab', function (): void {
    $user = buyer();
    walletFor($user, 50_000, 20_000);

    expect(props($this->actingAs($user)->get(advertiserUrl('/balance')))['tab'])->toBe('overview');

    foreach (['top-up', 'transactions', 'invoices'] as $tab) {
        $response = $this->actingAs($user)->get(advertiserUrl("/balance?tab={$tab}"));

        $response->assertOk();
        expect(props($response)['tab'])->toBe($tab);
    }
});

it('sends the old billing address to the new one', function (): void {
    $this->actingAs(buyer())->get(advertiserUrl('/billing'))->assertRedirect('/balance');
});

it('separates available, frozen and spent', function (): void {
    $user = buyer();
    $wallet = walletFor($user, 100_00);

    $wallet->freeze(Money::fromCents(40_00));
    $wallet->charge(Money::fromCents(25_00));

    $overview = props($this->actingAs($user)->get(advertiserUrl('/balance')))['overview'];

    expect($overview['availableCents'])->toBe(60_00)
        ->and($overview['frozenCents'])->toBe(15_00)
        // A freeze is not spending — the money is still theirs until a link
        // is verified. Only the charge counts.
        ->and($overview['spent']['lifetimeCents'])->toBe(25_00);
});

it('draws twelve months even when nothing happened in them', function (): void {
    $user = buyer();
    walletFor($user)->deposit(Money::fromCents(10_000));

    $series = props($this->actingAs($user)->get(advertiserUrl('/balance')))['overview']['series'];

    expect($series)->toHaveCount(12)
        ->and(end($series)['depositCents'])->toBe(10_000);
});

// -------------------------------------------------------- the reconciliation

it('reconciles the ledger against the wallet, exactly', function (): void {
    $user = buyer();
    $wallet = walletFor($user);

    // Every kind of movement, in an order a real account would produce.
    $wallet->deposit(Money::fromCents(100_000));
    $wallet->bonus(Money::fromCents(5_000));
    $wallet->freeze(Money::fromCents(30_000));
    $wallet->freeze(Money::fromCents(20_000));
    $wallet->charge(Money::fromCents(30_000));
    $wallet->unfreeze(Money::fromCents(20_000));
    $wallet->refund(Money::fromCents(2_500));
    $wallet->deposit(Money::fromCents(7_500));

    $check = app(LedgerReconciler::class)->check($wallet->refresh());

    expect($check['reconciles'])->toBeTrue()
        ->and($check['replayed'])->toBe($check['stored'])
        ->and($check['stored']['availableCents'])->toBe(85_000)
        ->and($check['stored']['frozenCents'])->toBe(0);
});

it('agrees with the balance_after column on every row', function (): void {
    $user = buyer();
    $wallet = walletFor($user);

    $wallet->deposit(Money::fromCents(50_000));
    $wallet->freeze(Money::fromCents(20_000));
    $wallet->charge(Money::fromCents(20_000));

    $rows = Transaction::query()->where('wallet_id', $wallet->id)->orderBy('id')->get();
    $reconciler = app(LedgerReconciler::class);
    $seen = [];

    foreach ($rows as $row) {
        $seen[] = $row;
        $replayed = $reconciler->replay($seen);

        expect($replayed['availableCents'])->toBe($row->balance_after_cents)
            ->and($replayed['frozenCents'])->toBe($row->frozen_after_cents);
    }
});

it('is not the naive sum of the amount column, and says so', function (): void {
    $user = buyer();
    $wallet = walletFor($user);

    $wallet->deposit(Money::fromCents(50_000));
    $wallet->freeze(Money::fromCents(20_000));
    $wallet->charge(Money::fromCents(20_000));

    $naive = (int) Transaction::query()->where('wallet_id', $wallet->id)->sum('amount_cents');

    /*
     * The trap this pins down: a charge is signed negative but debits the
     * *frozen* bucket, so summing the column subtracts it from a number it
     * never touched. Anybody "fixing" the ledger page by summing will be out
     * by the lifetime total of every completed post — here, $200.
     */
    expect($naive)->toBe(10_000)
        ->and($wallet->refresh()->available_cents)->toBe(30_000)
        ->and($naive)->not->toBe($wallet->available_cents);
});

// ------------------------------------------------------------------ topping up

it('adds funds, credits the bonus and writes two ledger rows', function (): void {
    Notification::fake();

    $user = buyer();
    walletFor($user);

    $this->actingAs($user)
        ->post(advertiserUrl('/balance/top-up'), ['amount' => '2500', 'method' => 'new_card'])
        ->assertRedirect('/balance');

    $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();

    // $2,500 at the 5% tier.
    expect($wallet->available_cents)->toBe(262_500)
        ->and(Transaction::query()->where('type', TransactionType::Deposit)->count())->toBe(1)
        ->and(Transaction::query()->where('type', TransactionType::Bonus)->count())->toBe(1);

    Notification::assertSentTo($user, TopUpConfirmedNotification::class);
});

it('computes the bonus on the server, whatever the browser sent', function (): void {
    $user = buyer();
    walletFor($user);

    // A hand-rolled request claiming a bonus. The field does not exist, and the
    // point is that adding one would change nothing.
    $this->actingAs($user)->post(advertiserUrl('/balance/top-up'), [
        'amount' => '100',
        'method' => 'new_card',
        'bonus' => '999999',
    ]);

    expect(Wallet::query()->where('user_id', $user->id)->value('available_cents'))->toBe(10_000);
});

it('gives the specific decline reason and what to try next', function (): void {
    $user = buyer();
    walletFor($user);

    // .14 is the simulated gateway's expired-card trigger, at any size.
    $this->actingAs($user)
        ->post(advertiserUrl('/balance/top-up'), ['amount' => '100.14', 'method' => 'new_card']);

    $errors = session('errors')->getBag('default');

    expect($errors->first('payment'))->toBe(DeclineReason::ExpiredCard->headline())
        ->and($errors->first('payment_advice'))->toBe(DeclineReason::ExpiredCard->advice())
        // Never a generic message.
        ->and($errors->first('payment'))->not->toContain('error')
        ->and(Wallet::query()->where('user_id', $user->id)->value('available_cents'))->toBe(0)
        ->and(TopUp::query()->first()->status)->toBe(TopUpStatus::Failed);
});

it('refuses a top-up below the minimum', function (): void {
    $user = buyer();

    $this->actingAs($user)
        ->post(advertiserUrl('/balance/top-up'), ['amount' => '20', 'method' => 'new_card'])
        ->assertSessionHasErrors('amount');
});

it('does not credit a bank transfer until it is confirmed', function (): void {
    Notification::fake();

    $user = buyer();
    walletFor($user);

    $this->actingAs($user)
        ->post(advertiserUrl('/balance/top-up'), ['amount' => '5000', 'method' => 'bank_transfer'])
        ->assertRedirect();

    $topUp = TopUp::query()->firstOrFail();

    expect($topUp->status)->toBe(TopUpStatus::Pending)
        ->and($topUp->reference)->toStartWith('PZ-')
        ->and(Wallet::query()->where('user_id', $user->id)->value('available_cents'))->toBe(0);

    Notification::assertNothingSent();

    app(StartTopUp::class)->confirm($topUp);

    expect(Wallet::query()->where('user_id', $user->id)->value('available_cents'))->toBe(530_000);
    Notification::assertSentTo($user, TopUpConfirmedNotification::class);
});

it('confirms a transfer once, however many times it is confirmed', function (): void {
    Notification::fake();

    $user = buyer();
    walletFor($user);

    $this->actingAs($user)->post(advertiserUrl('/balance/top-up'), [
        'amount' => '500',
        'method' => 'bank_transfer',
    ]);

    $topUp = TopUp::query()->firstOrFail();
    $start = app(StartTopUp::class);

    $start->confirm($topUp);
    $start->confirm($topUp->refresh());
    $start->confirm($topUp->refresh());

    expect(Wallet::query()->where('user_id', $user->id)->value('available_cents'))->toBe(50_000)
        ->and(Transaction::query()->where('type', TransactionType::Deposit)->count())->toBe(1);
});

it('will not charge a card belonging to somebody else', function (): void {
    $theirs = card(buyer());

    $this->flushSession();

    $mine = buyer();
    walletFor($mine);

    $this->actingAs($mine)->post(advertiserUrl('/balance/top-up'), [
        'amount' => '100',
        'method' => 'saved_card',
        'payment_method_id' => $theirs->id,
    ]);

    // The card is dropped rather than used, and the top-up is not filed
    // against a payment method that was never theirs.
    expect(TopUp::query()->first()->payment_method_id)->toBeNull();
});

// -------------------------------------------------------------- volume bonus

it('credits each tier at its own rate', function (): void {
    $bonus = new VolumeBonus;

    expect($bonus->for(Money::fromCents(50_00))->cents)->toBe(0)
        ->and($bonus->for(Money::fromCents(1_000_00))->cents)->toBe(3_000)
        ->and($bonus->for(Money::fromCents(2_500_00))->cents)->toBe(12_500)
        ->and($bonus->for(Money::fromCents(5_000_00))->cents)->toBe(30_000)
        ->and($bonus->for(Money::fromCents(10_000_00))->cents)->toBe(80_000);
});

it('only mentions the next tier when it is within reach', function (): void {
    $bonus = new VolumeBonus;

    // $900 is $100 short of the first tier — worth saying.
    expect($bonus->nextTier(Money::fromCents(900_00)))->not->toBeNull();

    // $60 is nowhere near, and nobody adding $60 wants the pitch.
    expect($bonus->nextTier(Money::fromCents(60_00)))->toBeNull();

    // Already at the top.
    expect($bonus->nextTier(Money::fromCents(20_000_00)))->toBeNull();
});

// -------------------------------------------------------------- auto top-up

it('will not arm a half-finished auto top-up rule', function (): void {
    $user = buyer();

    $this->actingAs($user)
        ->patch(advertiserUrl('/balance/auto-top-up'), ['enabled' => true, 'threshold' => 100])
        ->assertSessionHasErrors(['amount', 'payment_method_id']);

    expect(Wallet::query()->where('user_id', $user->id)->value('auto_topup_enabled'))->toBeNull();
});

it('saves a complete rule and turns it off again', function (): void {
    $user = buyer();
    $saved = card($user);

    $this->actingAs($user)->patch(advertiserUrl('/balance/auto-top-up'), [
        'enabled' => true,
        'threshold' => 100,
        'amount' => 500,
        'payment_method_id' => $saved->id,
    ])->assertRedirect();

    $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();

    expect($wallet->auto_topup_enabled)->toBeTrue()
        ->and($wallet->auto_topup_threshold_cents)->toBe(10_000)
        ->and($wallet->auto_topup_amount_cents)->toBe(50_000)
        ->and($wallet->autoTopUpIsArmed())->toBeTrue();

    $this->actingAs($user)->patch(advertiserUrl('/balance/auto-top-up'), ['enabled' => false]);

    expect(Wallet::query()->where('user_id', $user->id)->value('auto_topup_enabled'))->toBeFalse();
});

it('reports a rule whose card has gone as on but not armed', function (): void {
    $user = buyer();
    $saved = card($user);

    $this->actingAs($user)->patch(advertiserUrl('/balance/auto-top-up'), [
        'enabled' => true,
        'threshold' => 100,
        'amount' => 500,
        'payment_method_id' => $saved->id,
    ]);

    $saved->delete();

    $state = props($this->actingAs($user)->get(advertiserUrl('/balance')))['overview']['autoTopUp'];

    expect($state['enabled'])->toBeTrue()
        ->and($state['armed'])->toBeFalse();
});

// -------------------------------------------------------------- transactions

it('filters the ledger by type, date, amount and description', function (): void {
    $user = buyer();
    $wallet = walletFor($user);

    $wallet->deposit(Money::fromCents(100_000), null, 'Top-up PZ-1');
    $wallet->freeze(Money::fromCents(10_000), null, 'Order PZ-9');
    $wallet->deposit(Money::fromCents(500), null, 'Small change');

    $rows = function (string $query) use ($user): array {
        $response = $this->actingAs($user)->get(advertiserUrl("/balance?tab=transactions&{$query}"));

        return props($response)['ledger']['rows'];
    };

    expect($rows('types[]=deposit'))->toHaveCount(2)
        ->and($rows('q=Small'))->toHaveCount(1)
        // Magnitudes, not signed values: $50–$150 has to catch the $100
        // freeze, whose amount is stored negative.
        ->and($rows('min=50&max=150'))->toHaveCount(1)
        ->and($rows('min=50&max=150')[0]['type'])->toBe('freeze');
});

it('exports exactly what the filters selected', function (): void {
    $user = buyer();
    $wallet = walletFor($user);

    $wallet->deposit(Money::fromCents(100_000), null, 'Kept');
    $wallet->deposit(Money::fromCents(200), null, 'Filtered out');

    $csv = $this->actingAs($user)
        ->get(advertiserUrl('/balance/export?format=csv&min=100'))
        ->streamedContent();

    expect($csv)->toContain('Kept')
        ->and($csv)->not->toContain('Filtered out');
});

it('writes a real workbook for the xlsx export', function (): void {
    $user = buyer();
    walletFor($user)->deposit(Money::fromCents(100_000), null, 'Top-up');

    $body = $this->actingAs($user)
        ->get(advertiserUrl('/balance/export?format=xlsx'))
        ->streamedContent();

    // A zip, which is what an .xlsx is. A CSV renamed would pass a status
    // check and fail in Excel.
    expect(substr($body, 0, 2))->toBe('PK');
});

// ------------------------------------------------------------------ invoices

it('lists issued invoices and downloads one as a PDF', function (): void {
    Storage::fake('local');

    $user = buyer();

    $invoice = Invoice::query()->create([
        'user_id' => $user->id,
        'number' => 'INV-2024-0001',
        'subtotal_cents' => 40_000,
        'tax_cents' => 0,
        'total_cents' => 40_000,
        'currency' => 'USD',
        'status' => 'paid',
        'billing_details' => ['company' => 'Northwind Ltd', 'address' => '1 Test Street', 'vat_no' => 'GB123'],
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    $listed = props($this->actingAs($user)->get(advertiserUrl('/balance?tab=invoices')))['invoices'];

    expect($listed)->toHaveCount(1)
        ->and($listed[0]['number'])->toBe('INV-2024-0001');

    $body = $this->actingAs($user)
        ->get(advertiserUrl("/balance/invoices/{$invoice->id}"))
        ->assertOk()
        ->streamedContent();

    expect($body)->toStartWith('%PDF-')
        ->and($body)->toContain('%%EOF')
        // Both parties, the number and the status are on the page.
        ->and($body)->toContain('INV-2024-0001')
        ->and($body)->toContain('Northwind Ltd')
        ->and($body)->toContain('PUBLINZA');
});

it('paginates an invoice whose placements do not fit on one page', function (): void {
    Storage::fake('local');

    $user = buyer();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'subtotal_cents' => 0,
        'total_cents' => 0,
    ]);

    // Twenty lines is more than a page holds. Before this was paginated the
    // rows ran over the footer and the totals block was never drawn at all.
    for ($i = 0; $i < 20; $i++) {
        Post::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'website_id' => site(['domain' => "site{$i}.example"])->id,
            'price_cents' => 10_000,
            'anchor_text' => "anchor {$i}",
        ]);
    }

    $invoice = Invoice::query()->create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'number' => 'INV-LONG',
        'subtotal_cents' => 200_000,
        'tax_cents' => 0,
        'total_cents' => 200_000,
        'status' => 'paid',
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    $pdf = $this->actingAs($user)
        ->get(advertiserUrl("/balance/invoices/{$invoice->id}"))
        ->streamedContent();

    /*
     * The invariant, not a proxy for it.
     *
     * "More than one page" was the first thing asserted here and it proved
     * nothing: the totals block breaks to its own page whether or not the
     * lines do, so the test passed with line pagination switched off while the
     * rows still ran over the footer and off the bottom of the sheet.
     *
     * What actually has to hold is that nothing is drawn below the footer, so
     * that is what is measured — every text baseline in every content stream.
     */
    preg_match_all('/([\d.]+) ([\d.]+) Td/', $pdf, $matches);

    $baselines = array_map('floatval', $matches[2]);

    expect($baselines)->not->toBeEmpty()
        ->and(min($baselines))->toBeGreaterThanOrEqual(40.0)
        ->and(substr_count($pdf, '/Type /Page '))->toBeGreaterThan(1)
        // Escaped in the stream: a PDF string escapes its own parentheses.
        ->and($pdf)->toContain('\\(continued\\)')
        ->and($pdf)->toContain('Total');
});

it('keeps one advertiser out of another’s invoice', function (): void {
    $theirs = Invoice::query()->create([
        'user_id' => buyer()->id,
        'number' => 'INV-2024-0002',
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'status' => 'paid',
        'issued_at' => now(),
    ]);

    $this->flushSession();

    $this->actingAs(buyer())
        ->get(advertiserUrl("/balance/invoices/{$theirs->id}"))
        ->assertNotFound();
});

it('does not list an invoice that has not been issued', function (): void {
    $user = buyer();

    Invoice::query()->create([
        'user_id' => $user->id,
        'number' => 'INV-DRAFT',
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'status' => 'draft',
    ]);

    expect(props($this->actingAs($user)->get(advertiserUrl('/balance?tab=invoices')))['invoices'])->toHaveCount(0);
});

it('saves billing details and leaves issued invoices alone', function (): void {
    $user = buyer();

    $invoice = Invoice::query()->create([
        'user_id' => $user->id,
        'number' => 'INV-2024-0003',
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'status' => 'paid',
        'billing_details' => ['company' => 'Old Name Ltd'],
        'issued_at' => now(),
    ]);

    $this->actingAs($user)->patch(advertiserUrl('/balance/billing'), [
        'company' => 'New Name Ltd',
        'billing_address' => '9 New Road',
        'country' => 'de',
        'vat_no' => 'DE999',
        'billing_email' => 'accounts@example.com',
    ])->assertRedirect();

    expect($user->fresh()->company)->toBe('New Name Ltd')
        ->and($user->fresh()->country)->toBe('DE')
        // The snapshot is untouched. An invoice keeps saying what it said.
        ->and($invoice->fresh()->billing_details['company'])->toBe('Old Name Ltd');
});
