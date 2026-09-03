import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { money } from '@shared/lib/format';
import type { ProjectOverviewStats } from '@shared/types/projects';

interface Props {
    stats: ProjectOverviewStats;
}

/**
 * What this project has cost, and what is still committed to it.
 *
 * Three numbers that are easy to confuse, so each one says in a line what it
 * means. "Frozen" in particular is money an advertiser has already paid for and
 * cannot spend again, and the one question it always raises — when do I get it
 * back — is answered on the row rather than in a help article.
 *
 * Frozen is gold, the token this product reserves for held funds, so the figure
 * reads the same here as it does in the posts grid and the wallet.
 */
export function FinancePanel({ stats }: Props) {
    const committed = stats.spentCents + stats.frozenCents;

    return (
        <section
            aria-labelledby="finance-heading"
            className="rounded-card border border-subtle bg-card p-5 shadow-card"
        >
            <h2 id="finance-heading" className="font-sora text-md font-semibold text-ink-900">
                Finance
            </h2>

            <dl className="mt-4 flex flex-col divide-y divide-subtle">
                <Row
                    icon={<WalletIcon />}
                    label="Spent"
                    value={money(stats.spentCents)}
                    valueClass="text-ink-900"
                    note="Placements that went live and settled."
                />

                <Row
                    icon={<SnowIcon />}
                    label="Frozen"
                    value={money(stats.frozenCents)}
                    valueClass="text-[color:var(--gold)]"
                    note="Held against posts in progress. Released when links are verified."
                />

                <Row
                    icon={<TagIcon />}
                    label="Average price"
                    // Null, not zero: nothing has completed yet, and $0.00
                    // would read as "these placements are free".
                    value={stats.averageCents === null ? '—' : money(stats.averageCents)}
                    valueClass="text-ink-700"
                    note={
                        stats.averageCents === null
                            ? 'Shown once your first placement completes.'
                            : 'Mean price of the placements that completed.'
                    }
                />
            </dl>

            {committed > 0 && (
                <div className="mt-5">
                    <div
                        role="img"
                        aria-label={`Spent ${money(stats.spentCents)}, frozen ${money(stats.frozenCents)}`}
                        className="flex h-1.5 w-full gap-px overflow-hidden rounded-pill bg-sunken"
                    >
                        <span
                            aria-hidden="true"
                            className="h-full rounded-l-pill bg-ink-900"
                            style={{ width: `${(stats.spentCents / committed) * 100}%` }}
                        />
                        <span
                            aria-hidden="true"
                            className="h-full rounded-r-pill"
                            style={{
                                width: `${(stats.frozenCents / committed) * 100}%`,
                                backgroundColor: 'var(--gold)',
                            }}
                        />
                    </div>

                    <p className="mt-2 text-xs text-ink-500">{money(committed)} committed to this project so far.</p>
                </div>
            )}

            <Link href="/billing" className="mt-4 inline-block text-sm font-medium text-brand hover:underline">
                Top up balance
            </Link>
        </section>
    );
}

function Row({
    icon,
    label,
    value,
    valueClass,
    note,
}: {
    icon: ReactNode;
    label: string;
    value: string;
    valueClass: string;
    note: string;
}) {
    return (
        <div className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
            <span className="mt-0.5 shrink-0 text-ink-500">{icon}</span>

            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-sm text-ink-700">{label}</dt>
                    <dd className={`num shrink-0 text-md font-semibold ${valueClass}`}>{value}</dd>
                </div>

                <p className="text-xs text-ink-500">{note}</p>
            </div>
        </div>
    );
}

function Icon({ children }: { children: ReactNode }) {
    return (
        <svg
            width={16}
            height={16}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {children}
        </svg>
    );
}

function WalletIcon() {
    return (
        <Icon>
            <path d="M20 12V8H6a2 2 0 0 1 0-4h12v4" />
            <path d="M4 6v12a2 2 0 0 0 2 2h14v-4" />
            <path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />
        </Icon>
    );
}

function SnowIcon() {
    return (
        <Icon>
            <path d="M12 2v20M4.2 7l15.6 10M19.8 7 4.2 17" />
        </Icon>
    );
}

function TagIcon() {
    return (
        <Icon>
            <path d="M12 2v20" />
            <path d="M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </Icon>
    );
}
