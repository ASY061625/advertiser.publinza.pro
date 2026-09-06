<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Billing\Actions\RenderInvoice;
use App\Domain\Billing\Actions\StartTopUp;
use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\TopUpMethod;
use App\Domain\Billing\Enums\TopUpStatus;
use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentMethod;
use App\Domain\Billing\Models\TopUp;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Billing\Support\BalancePresenter;
use App\Domain\Billing\Support\LedgerQuery;
use App\Domain\Billing\Support\VolumeBonus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Export\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The advertiser's money: what they hold, how they add to it, where it went,
 * and the paperwork.
 *
 * Four tabs over one URL, deep-linked as `?tab=`. One page rather than four
 * routes because they are four readings of the same wallet, and because
 * somebody who has just topped up should land back on the overview with the
 * new figure on it rather than on a separate success page.
 */
class BalanceController extends Controller
{
    private const TABS = ['overview', 'top-up', 'transactions', 'invoices'];

    private const PER_PAGE = 50;

    public function index(
        Request $request,
        BalancePresenter $presenter,
        VolumeBonus $bonus,
        PaymentGateway $gateway,
    ): Response {
        $user = $request->user();
        $wallet = $user->wallet;

        $tab = in_array($request->string('tab')->value(), self::TABS, true)
            ? $request->string('tab')->value()
            : 'overview';

        $filters = LedgerQuery::fromRequest($request);

        return inertia('Balance/Index', [
            'tab' => $tab,
            'overview' => $presenter->overview($user, $wallet),
            'cards' => $presenter->cards($user),
            'topUp' => $this->topUpConfig($bonus, $gateway),
            'ledger' => $tab === 'transactions'
                ? $this->ledger($request, $presenter, $wallet, $filters)
                : null,
            'filters' => $filters->toArray(),
            'types' => array_map(static fn (TransactionType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], TransactionType::cases()),
            'invoices' => $tab === 'invoices' ? $this->invoices($user) : null,
            'billing' => $this->billing($user),
            // A bank transfer awaiting the money. Surfaced on every tab: it is
            // the answer to "where is my top-up", and somebody asking that is
            // not going to look under Transactions for it.
            'pendingTransfers' => $this->pendingTransfers($user),
        ]);
    }

    // ------------------------------------------------------------- topping up

    public function topUp(Request $request, StartTopUp $start): RedirectResponse
    {
        $minimum = (int) config('publinza.payments.minimum_top_up_cents');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.($minimum / 100), 'max:100000'],
            'method' => ['required', 'in:'.implode(',', array_column(TopUpMethod::cases(), 'value'))],
            'payment_method_id' => ['nullable', 'integer'],
            'token' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.min' => sprintf('The smallest top-up is %s.', Money::fromCents($minimum)->format()),
        ]);

        $method = TopUpMethod::from($data['method']);

        if ($method === TopUpMethod::SavedCard && ($data['payment_method_id'] ?? null) === null) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Choose which card to charge.',
            ]);
        }

        $topUp = $start->handle(
            user: $request->user(),
            amount: Money::fromMajorUnits((string) $data['amount']),
            method: $method,
            paymentMethodId: isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            token: $data['token'] ?? null,
        );

        if ($topUp->status === TopUpStatus::Failed) {
            /*
             * The specific reason, never a generic error.
             *
             * Kept on the top-up form rather than redirected away: the amount
             * and the method are still in the fields, and the next thing this
             * person does is change one of them.
             */
            return back()->withErrors([
                'payment' => $topUp->decline_code?->headline() ?? 'The payment did not go through.',
                'payment_advice' => $topUp->decline_code?->advice() ?? 'Try another payment method.',
            ])->withInput();
        }

        if ($topUp->status === TopUpStatus::Pending) {
            // A transfer has not paid anything yet, so there is nothing to
            // celebrate. The page shows the details and the reference instead.
            return redirect('/balance?tab=top-up&transfer='.$topUp->reference);
        }

        return redirect('/balance')->with(
            'success',
            sprintf('%s added to your balance.', $topUp->credited()->format()),
        );
    }

    // ------------------------------------------------------------ auto top-up

    public function updateAutoTopUp(Request $request): RedirectResponse
    {
        $minimum = (int) config('publinza.payments.minimum_top_up_cents');

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'amount' => ['nullable', 'numeric', 'min:'.($minimum / 100), 'max:100000'],
            'payment_method_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        // Switching it on needs all three parts. Storing a half-rule that would
        // fail at 2am when the balance ran out is worse than refusing it now.
        if ($data['enabled']) {
            $missing = array_keys(array_filter([
                'threshold' => ($data['threshold'] ?? null) === null,
                'amount' => ($data['amount'] ?? null) === null,
                'payment_method_id' => ($data['payment_method_id'] ?? null) === null,
            ]));

            if ($missing !== []) {
                throw ValidationException::withMessages(array_fill_keys(
                    $missing,
                    'Auto top-up needs a threshold, an amount and a card.',
                ));
            }
        }

        $card = ($data['payment_method_id'] ?? null) === null
            ? null
            : PaymentMethod::query()->where('user_id', $user->id)->find($data['payment_method_id']);

        /** @var Wallet $wallet */
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['available_cents' => 0, 'frozen_cents' => 0, 'currency' => 'USD'],
        );

        $wallet->update([
            'auto_topup_enabled' => $data['enabled'] && $card !== null,
            'auto_topup_threshold_cents' => ($data['threshold'] ?? null) === null
                ? null
                : Money::fromMajorUnits((string) $data['threshold'])->cents,
            'auto_topup_amount_cents' => ($data['amount'] ?? null) === null
                ? null
                : Money::fromMajorUnits((string) $data['amount'])->cents,
            'auto_topup_payment_method_id' => $card?->id,
        ]);

        return back()->with(
            'success',
            $wallet->auto_topup_enabled ? 'Auto top-up is on.' : 'Auto top-up is off.',
        );
    }

    // -------------------------------------------------------------- the ledger

    public function export(Request $request, BalancePresenter $presenter): StreamedResponse
    {
        $format = $request->string('format')->value() === 'xlsx' ? 'xlsx' : 'csv';
        $wallet = $request->user()->wallet;
        $filters = LedgerQuery::fromRequest($request);

        $rows = $wallet === null
            ? []
            : $presenter->rows(
                $filters
                    ->apply(Transaction::query()->where('wallet_id', $wallet->getKey()))
                    ->latest('created_at')
                    ->latest('id')
                    ->get(),
            );

        $headers = ['Date', 'Type', 'Description', 'Reference', 'Amount', 'Balance after'];
        $name = 'publinza-transactions-'.now()->format('Y-m-d');

        return $format === 'xlsx'
            ? $this->xlsx($headers, $rows, $name)
            : $this->csv($headers, $rows, $name);
    }

    // ------------------------------------------------------------- the invoices

    public function invoice(Request $request, Invoice $invoice, RenderInvoice $render): StreamedResponse
    {
        abort_if($invoice->user_id !== $request->user()->id, 404);

        ['path' => $path, 'filename' => $filename] = $render->handle($invoice);

        return Storage::disk('local')->download($path, $filename);
    }

    public function updateBilling(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company' => ['nullable', 'string', 'max:190'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'size:2'],
            'vat_no' => ['nullable', 'string', 'max:64'],
            'billing_email' => ['nullable', 'email', 'max:190'],
        ]);

        $request->user()->forceFill([
            'company' => $data['company'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'country' => isset($data['country']) ? mb_strtoupper($data['country']) : null,
            'vat_no' => $data['vat_no'] ?? null,
            'billing_email' => $data['billing_email'] ?? null,
        ])->save();

        return back()->with(
            'success',
            // Said plainly, because it is the thing people are surprised by.
            'Billing details saved. They apply to invoices issued from now on — invoices already issued keep the details they were issued with.',
        );
    }

    // -------------------------------------------------------------- internals

    /**
     * @return array<string, mixed>
     */
    private function topUpConfig(VolumeBonus $bonus, PaymentGateway $gateway): array
    {
        return [
            'minimumCents' => (int) config('publinza.payments.minimum_top_up_cents'),
            'quickAmountsCents' => array_values((array) config('publinza.payments.quick_amounts_cents')),
            'tiers' => $bonus->tiers(),
            'bank' => (array) config('publinza.payments.bank'),
            'gatewayIsLive' => $gateway->isLive(),
            // Null without a configured key: the card form says so rather than
            // rendering an input that can never be submitted.
            'stripeKey' => $gateway->publishableKey(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ledger(Request $request, BalancePresenter $presenter, ?Wallet $wallet, LedgerQuery $filters): array
    {
        if ($wallet === null) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'lastPage' => 1];
        }

        $page = $filters->apply(
            Transaction::query()->where('wallet_id', $wallet->getKey()),
        )
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'rows' => $presenter->rows($page->getCollection()),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoices(User $user): array
    {
        return Invoice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('issued_at')
            ->latest('issued_at')
            ->latest('id')
            ->get()
            ->map(static fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'issuedAt' => $invoice->issued_at?->toIso8601String(),
                'periodStart' => $invoice->period_start?->toDateString(),
                'periodEnd' => $invoice->period_end?->toDateString(),
                'totalCents' => $invoice->total_cents,
                'status' => $invoice->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function billing(User $user): array
    {
        return [
            'name' => $user->name,
            'company' => $user->company,
            'address' => $user->billing_address,
            'country' => $user->country,
            'vatNo' => $user->vat_no,
            // The account address is the fallback, and the form says so —
            // somebody who has never thought about it still gets their invoices.
            'billingEmail' => $user->billing_email,
            'accountEmail' => $user->email,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingTransfers(User $user): array
    {
        return TopUp::query()
            ->where('user_id', $user->id)
            ->where('status', TopUpStatus::Pending)
            ->latest('id')
            ->get()
            ->map(static fn ($topUp): array => [
                'reference' => $topUp->reference,
                'amountCents' => $topUp->amount_cents,
                'createdAt' => $topUp->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function csv(array $headers, array $rows, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $this->cells($row));
            }

            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function xlsx(array $headers, array $rows, string $name): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'ledger').'.xlsx';

        app(XlsxWriter::class)->write($path, 'Transactions', $headers, array_map(
            // Money as a number, not a string: a spreadsheet whose amount
            // column arrives as text is one nobody can sum, which is the one
            // thing people open it to do.
            fn (array $row): array => $this->cells($row, numeric: true),
            $rows,
        ));

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $name.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string|int|float|null>
     */
    private function cells(array $row, bool $numeric = false): array
    {
        $amount = ((int) $row['amountCents']) / 100;
        $after = ((int) $row['balanceAfterCents']) / 100;

        return [
            $row['createdAt'] === null ? '' : (string) $row['createdAt'],
            (string) $row['typeLabel'],
            (string) ($row['description'] ?? ''),
            (string) ($row['subject']['label'] ?? ''),
            $numeric ? $amount : number_format($amount, 2, '.', ''),
            $numeric ? $after : number_format($after, 2, '.', ''),
        ];
    }
}
