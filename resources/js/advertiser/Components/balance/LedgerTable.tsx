import { Link } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { money } from '@shared/lib/format';
import type { LedgerRow, TransactionKind } from '@shared/types/balance';

/**
 * The ledger, as a table.
 *
 * Used twice: ten rows on the overview and the whole filtered set on the
 * transactions tab. One component, because a "recent activity" list that
 * formats money differently from the ledger it links to is a bug waiting to be
 * reported.
 */
export function LedgerTable({
    rows,
    showBalance = true,
    compact = false,
}: {
    rows: LedgerRow[];
    showBalance?: boolean;
    compact?: boolean;
}) {
    return (
        <div className="relative overflow-x-auto rounded-card border border-subtle bg-card shadow-card">
            <table className="w-full border-collapse text-left text-base" style={{ minWidth: compact ? '560px' : '840px' }}>
                <caption className="sr-only">Wallet transactions</caption>

                <thead>
                    <tr>
                        {['When', 'Type', 'Description', 'Amount', ...(showBalance ? ['Balance after'] : [])].map(
                            (heading, index) => (
                                <th
                                    key={heading}
                                    scope="col"
                                    className={cn(
                                        'border-b border-subtle bg-sunken px-3 py-3 text-sm font-medium text-ink-500',
                                        index >= 3 && 'text-right',
                                    )}
                                >
                                    {heading}
                                </th>
                            ),
                        )}
                    </tr>
                </thead>

                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-b border-subtle last:border-0 hover:bg-row-hover">
                            <td className="whitespace-nowrap px-3 py-3 text-sm text-ink-500">
                                {row.createdAt === null ? '—' : stamp(row.createdAt)}
                            </td>

                            <td className="px-3 py-3">
                                <TypeBadge type={row.type} label={row.typeLabel} />
                            </td>

                            <td className="px-3 py-3">
                                <span className="block text-base text-ink-900">{row.description ?? '—'}</span>

                                {row.subject && (
                                    <Link
                                        href={row.subject.href}
                                        className="text-sm text-brand hover:underline"
                                    >
                                        {row.subject.label}
                                    </Link>
                                )}
                            </td>

                            <td className="num whitespace-nowrap px-3 py-3 text-right font-medium">
                                <Amount cents={row.amountCents} />
                            </td>

                            {showBalance && (
                                <td className="num whitespace-nowrap px-3 py-3 text-right text-ink-700">
                                    {money(row.balanceAfterCents)}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Teal for money arriving, ink for money leaving.
 *
 * Not red for the negatives. A charge is what the product is for — an
 * advertiser buying a placement they wanted — and painting every purchase in
 * the colour of an error makes a healthy account look alarming.
 */
function Amount({ cents }: { cents: number }) {
    const positive = cents > 0;

    return (
        <span className={positive ? 'text-teal' : 'text-ink-900'}>
            {positive ? '+' : '−'}
            {money(Math.abs(cents))}
        </span>
    );
}

/** The type vocabulary, with its own quiet palette. */
const TYPE_TONES: Record<TransactionKind, string> = {
    deposit: 'bg-teal-subtle text-teal',
    bonus: 'bg-teal-subtle text-teal',
    refund: 'bg-teal-subtle text-teal',
    charge: 'bg-sunken text-ink-700',
    freeze: 'bg-gold-subtle text-[#B45309]',
    unfreeze: 'bg-gold-subtle text-[#B45309]',
    adjustment: 'bg-sunken text-ink-700',
};

export function TypeBadge({ type, label }: { type: TransactionKind; label: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center whitespace-nowrap rounded-pill px-2.5 py-1 text-xs font-medium',
                TYPE_TONES[type] ?? 'bg-sunken text-ink-700',
            )}
        >
            {label}
        </span>
    );
}

/** Date and time — the ledger is audited, and the hour matters. */
function stamp(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
}
