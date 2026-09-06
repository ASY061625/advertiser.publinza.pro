<?php

declare(strict_types=1);

namespace App\Domain\Billing\Support;

use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\PaymentMethod;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Trading\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The shapes the balance page is sent.
 *
 * One place, because the same wallet is read four different ways on that screen
 * and four payloads assembled separately drift into four slightly different
 * answers about the same money — which, on a page about money, is the one thing
 * that must not happen.
 */
final class BalancePresenter
{
    /** How many periods the overview chart covers. */
    private const MONTHS = 12;

    private const RECENT = 10;

    /**
     * @return array<string, mixed>
     */
    public function overview(User $user, ?Wallet $wallet): array
    {
        $walletId = $wallet?->getKey();

        return [
            'availableCents' => $wallet?->available_cents ?? 0,
            'frozenCents' => $wallet?->frozen_cents ?? 0,
            'spent' => $this->spend($walletId),
            'frozenPosts' => $this->frozenPosts($user),
            'series' => $this->series($walletId),
            'recent' => $this->recent($walletId),
            'autoTopUp' => $this->autoTopUp($wallet),
        ];
    }

    /**
     * Lifetime and this-year spend.
     *
     * Charges only. A freeze is not spending — the money is still the
     * advertiser's, held against a post that may yet be cancelled — and
     * counting it would tell somebody they had spent money they can still get
     * back.
     *
     * @return array{lifetimeCents: int, yearCents: int, year: int}
     */
    private function spend(?int $walletId): array
    {
        $year = (int) now()->year;

        if ($walletId === null) {
            return ['lifetimeCents' => 0, 'yearCents' => 0, 'year' => $year];
        }

        $charged = fn (?Carbon $since): int => (int) abs((int) Transaction::query()
            ->where('wallet_id', $walletId)
            ->where('type', TransactionType::Charge)
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->sum('amount_cents'));

        return [
            'lifetimeCents' => $charged(null),
            'yearCents' => $charged(now()->startOfYear()),
            'year' => $year,
        ];
    }

    /**
     * What the frozen money is actually held against.
     *
     * The card says "held against active posts"; this is the list that sentence
     * promises. Without it, frozen is a number nobody can audit.
     *
     * @return array{count: int, href: string}
     */
    private function frozenPosts(User $user): array
    {
        $statuses = array_values(array_filter(
            PostStatus::cases(),
            static fn (PostStatus $status): bool => $status->holdsFrozenFunds(),
        ));

        $query = http_build_query([
            'statuses' => array_map(static fn (PostStatus $status): string => $status->value, $statuses),
        ]);

        return [
            'count' => Post::query()
                ->where('user_id', $user->id)
                ->whereIn('status', $statuses)
                ->count(),
            'href' => '/posts?'.$query,
        ];
    }

    /**
     * Twelve months of deposits against spend.
     *
     * Every month is present even when nothing happened in it — a bar chart
     * that silently drops empty periods compresses a quiet summer into nothing
     * and makes the year look busier than it was.
     *
     * @return list<array{iso: string, label: string, depositCents: int, spendCents: int}>
     */
    private function series(?int $walletId): array
    {
        $start = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $buckets = [];

        for ($i = 0; $i < self::MONTHS; $i++) {
            $month = $start->copy()->addMonths($i);

            $buckets[$month->format('Y-m')] = [
                'iso' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'depositCents' => 0,
                'spendCents' => 0,
            ];
        }

        if ($walletId === null) {
            return array_values($buckets);
        }

        $rows = Transaction::query()
            ->where('wallet_id', $walletId)
            ->whereIn('type', [TransactionType::Deposit, TransactionType::Charge])
            ->where('created_at', '>=', $start)
            ->get(['type', 'amount_cents', 'created_at']);

        foreach ($rows as $row) {
            // Not nullsafe: the framework stamps created_at on insert, and the
            // ledger is append-only, so a row without one does not exist.
            $key = $row->created_at->format('Y-m');

            if (! isset($buckets[$key])) {
                continue;
            }

            $field = $row->type === TransactionType::Deposit ? 'depositCents' : 'spendCents';
            $buckets[$key][$field] += abs($row->amount_cents);
        }

        return array_values($buckets);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recent(?int $walletId): array
    {
        if ($walletId === null) {
            return [];
        }

        return $this->rows(
            Transaction::query()
                ->where('wallet_id', $walletId)
                ->latest('created_at')
                ->latest('id')
                ->take(self::RECENT)
                ->get(),
        );
    }

    /**
     * Ledger rows, with each one's subject resolved to a link.
     *
     * References are resolved in two queries for the whole page rather than one
     * per row: a ledger of a hundred lines is a hundred round trips otherwise,
     * and this is the table people scroll.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return list<array<string, mixed>>
     */
    public function rows($transactions): array
    {
        $subjects = $this->subjects($transactions);
        $rows = [];

        foreach ($transactions as $transaction) {
            $key = $transaction->reference_type.'#'.$transaction->reference_id;

            $rows[] = [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'typeLabel' => $transaction->type->label(),
                // Signed as stored. The table colours on this sign, so it has
                // to be the ledger's own answer rather than a re-derivation.
                'amountCents' => $transaction->amount_cents,
                'balanceAfterCents' => $transaction->balance_after_cents,
                'frozenAfterCents' => $transaction->frozen_after_cents,
                'description' => $transaction->description,
                'createdAt' => $transaction->created_at->toIso8601String(),
                'subject' => $subjects[$key] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Resolves reference_type/reference_id pairs to something clickable.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, array{label: string, href: string}>
     */
    private function subjects($transactions): array
    {
        $byType = [];

        foreach ($transactions as $transaction) {
            if ($transaction->reference_type === null || $transaction->reference_id === null) {
                continue;
            }

            $byType[$transaction->reference_type][] = $transaction->reference_id;
        }

        $subjects = [];

        foreach ($byType as $type => $ids) {
            $ids = array_unique($ids);

            if (is_a($type, Post::class, true)) {
                Post::query()->with('website:id,domain')->findMany($ids)
                    ->each(function (Post $post) use ($type, &$subjects): void {
                        $subjects[$type.'#'.$post->id] = [
                            'label' => $post->website?->domain ?? "Post #{$post->id}",
                            'href' => "/posts?post={$post->id}",
                        ];
                    });

                continue;
            }

            if (is_a($type, Order::class, true)) {
                Order::query()->findMany($ids)
                    ->each(function (Order $order) use ($type, &$subjects): void {
                        $subjects[$type.'#'.$order->id] = [
                            'label' => $order->order_number,
                            'href' => "/checkout/{$order->order_number}",
                        ];
                    });
            }
        }

        return $subjects;
    }

    /**
     * @return array<string, mixed>
     */
    private function autoTopUp(?Wallet $wallet): array
    {
        return [
            'enabled' => (bool) $wallet?->auto_topup_enabled,
            'armed' => (bool) $wallet?->autoTopUpIsArmed(),
            'thresholdCents' => $wallet?->auto_topup_threshold_cents,
            'amountCents' => $wallet?->auto_topup_amount_cents,
            'paymentMethodId' => $wallet?->auto_topup_payment_method_id,
        ];
    }

    /**
     * The advertiser's saved cards.
     *
     * `provider_reference` is hidden on the model and never leaves the server —
     * the browser addresses a card by our own id.
     *
     * @return list<array<string, mixed>>
     */
    public function cards(User $user): array
    {
        return PaymentMethod::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(static fn (PaymentMethod $card): array => [
                'id' => $card->id,
                'brand' => $card->brand,
                'lastFour' => $card->last_four,
                'expMonth' => $card->exp_month,
                'expYear' => $card->exp_year,
                'isDefault' => $card->is_default,
                'expired' => $card->exp_year !== null
                    && $card->exp_month !== null
                    && Carbon::create($card->exp_year, $card->exp_month)->endOfMonth()->isPast(),
            ])
            ->values()
            ->all();
    }

    /**
     * Month-by-month totals for the CSV and XLSX exports' summary sheet.
     *
     * @return array{deposits: int, spend: int}
     */
    public function totals(?int $walletId): array
    {
        if ($walletId === null) {
            return ['deposits' => 0, 'spend' => 0];
        }

        $sums = Transaction::query()
            ->where('wallet_id', $walletId)
            ->selectRaw('type, SUM(ABS(amount_cents)) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'deposits' => (int) ($sums[TransactionType::Deposit->value] ?? 0),
            'spend' => (int) ($sums[TransactionType::Charge->value] ?? 0),
        ];
    }
}
