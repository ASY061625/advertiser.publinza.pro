import type { BalanceOverview, SavedCard } from '@shared/types/balance';
import { AutoTopUpCard } from './AutoTopUpCard';
import { BalanceCards } from './BalanceCards';
import { CashflowChart } from './CashflowChart';
import { LedgerTable } from './LedgerTable';

interface Props {
    overview: BalanceOverview;
    cards: SavedCard[];
    minimumCents: number;
    onTopUp: () => void;
    onSeeAll: () => void;
}

export function OverviewTab({ overview, cards, minimumCents, onTopUp, onSeeAll }: Props) {
    return (
        <div className="flex min-w-0 flex-col gap-5">
            <BalanceCards overview={overview} onTopUp={onTopUp} />

            <CashflowChart series={overview.series} />

            <section className="flex min-w-0 flex-col gap-3">
                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 className="font-sora text-md font-semibold text-ink-900">Recent activity</h2>

                    <button
                        type="button"
                        onClick={onSeeAll}
                        className="text-base font-medium text-brand hover:underline"
                    >
                        See the full ledger
                    </button>
                </div>

                {overview.recent.length === 0 ? (
                    <p className="rounded-card border border-subtle bg-card py-10 text-center text-base text-ink-500">
                        Nothing has moved yet. Your first top-up will appear here.
                    </p>
                ) : (
                    // No balance column here: ten rows out of context cannot be
                    // reconciled anyway, and the ledger tab is one click away.
                    <LedgerTable rows={overview.recent} showBalance={false} compact />
                )}
            </section>

            <AutoTopUpCard state={overview.autoTopUp} cards={cards} minimumCents={minimumCents} />
        </div>
    );
}
