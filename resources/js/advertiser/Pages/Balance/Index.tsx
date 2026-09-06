import { Head, router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { Alert, Tabs } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { AdvertiserSharedProps } from '@shared/types';
import type { BalancePageProps, BalanceTab, QueryValue } from '@shared/types/balance';
import { AppShell } from '../../Layouts/AppShell';
import { InvoicesTab } from '../../Components/balance/InvoicesTab';
import { OverviewTab } from '../../Components/balance/OverviewTab';
import { TopUpTab } from '../../Components/balance/TopUpTab';
import { TransactionsTab } from '../../Components/balance/TransactionsTab';

const TABS: { id: BalanceTab; label: string }[] = [
    { id: 'overview', label: 'Overview' },
    { id: 'top-up', label: 'Top up' },
    { id: 'transactions', label: 'Transactions' },
    { id: 'invoices', label: 'Invoices' },
];

/**
 * The advertiser's money, in four readings of one wallet.
 *
 * The tab lives in the query string so a specific view is a link somebody can
 * send — a finance person asked for "the transactions page" should be able to
 * be sent one — and so a top-up lands back on the overview with the new figure
 * on it rather than on a separate success page nobody needs twice.
 */
export default function BalanceIndex() {
    const page = usePage<AdvertiserSharedProps & BalancePageProps>();
    const { tab, overview, cards, topUp, ledger, filters, types, invoices, billing, pendingTransfers } = page.props;

    const query = new URLSearchParams(page.url.split('?')[1] ?? '');

    const go = useCallback(
        (changes: Record<string, QueryValue>) => {
            const next = new URLSearchParams(window.location.search);

            for (const [key, value] of Object.entries(changes)) {
                if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
                    next.delete(key);
                    next.delete(`${key}[]`);

                    continue;
                }

                if (Array.isArray(value)) {
                    next.delete(`${key}[]`);
                    value.forEach((item) => next.append(`${key}[]`, String(item)));

                    continue;
                }

                next.set(key, String(value));
            }

            router.get(`/balance?${next.toString()}`, {}, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                // `shell` too: a top-up moves the header's balance pill, and a
                // partial reload filters shared props unless they are named.
                only: ['tab', 'overview', 'cards', 'ledger', 'filters', 'invoices', 'billing', 'pendingTransfers', 'shell'],
            });
        },
        [],
    );

    const exportHref = useCallback(
        (format: 'csv' | 'xlsx') => {
            const params = new URLSearchParams(window.location.search);
            params.set('format', format);
            params.delete('tab');

            return `/balance/export?${params.toString()}`;
        },
        [],
    );

    return (
        <AppShell title="Balance" crumbs={[{ label: 'Balance' }]}>
            <Head title="Balance" />

            <div className="flex min-w-0 flex-col gap-5">
                <header>
                    <h1 className="font-sora text-xl font-semibold text-ink-900">Balance</h1>
                    <p className="num mt-1 text-sm text-ink-500">
                        {money(overview.availableCents)} available · {money(overview.frozenCents)} frozen
                    </p>
                </header>

                {/* On every tab. Somebody asking "where is my top-up" is not
                    going to look under Transactions for it. */}
                {pendingTransfers.length > 0 && (
                    <Alert tone="info" title="A bank transfer is on its way">
                        {pendingTransfers.map((transfer) => (
                            <span key={transfer.reference} className="block">
                                <span className="num font-medium">{money(transfer.amountCents)}</span> under
                                reference <span className="num font-medium">{transfer.reference}</span>. Your
                                balance goes up once we confirm the money has landed, usually within one
                                business day.
                            </span>
                        ))}
                    </Alert>
                )}

                <Tabs
                    scrollable
                    items={TABS}
                    value={tab}
                    onChange={(next) => go({ tab: next === 'overview' ? null : next })}
                />

                {tab === 'overview' && (
                    <OverviewTab
                        overview={overview}
                        cards={cards}
                        minimumCents={topUp.minimumCents}
                        onTopUp={() => go({ tab: 'top-up' })}
                        onSeeAll={() => go({ tab: 'transactions' })}
                    />
                )}

                {tab === 'top-up' && (
                    <TopUpTab
                        config={topUp}
                        cards={cards}
                        availableCents={overview.availableCents}
                        pendingTransfers={pendingTransfers}
                        initialAmount={query.get('amount') ?? ''}
                        highlightTransfer={query.get('transfer')}
                    />
                )}

                {tab === 'transactions' && ledger !== null && (
                    <TransactionsTab
                        ledger={ledger}
                        filters={filters}
                        types={types}
                        exportHref={exportHref}
                        apply={go}
                    />
                )}

                {tab === 'invoices' && invoices !== null && (
                    <InvoicesTab invoices={invoices} billing={billing} />
                )}
            </div>
        </AppShell>
    );
}
