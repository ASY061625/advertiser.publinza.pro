import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { money, number } from '@shared/lib/format';
import { SortIcon } from '@shared/ui';
import type { StatisticsPoint } from '@shared/types/statistics';

interface Props {
    series: StatisticsPoint[];
}

type Column = { id: keyof StatisticsPoint | 'iso'; label: string; numeric?: boolean };

const COLUMNS: Column[] = [
    { id: 'iso', label: 'Period' },
    { id: 'ordered', label: 'Posts ordered', numeric: true },
    { id: 'publishedCount', label: 'Posts published', numeric: true },
    { id: 'liveLinks', label: 'Links live', numeric: true },
    { id: 'spendCents', label: 'Spend', numeric: true },
    { id: 'averageCents', label: 'Average price', numeric: true },
];

/**
 * Every period as a row, and the same rows the CSV export writes.
 *
 * Sorted in the browser rather than the server: the whole range is already on
 * screen — it is the same array the charts plot — so a round trip to reorder
 * fifty rows would be latency bought for nothing.
 */
export function StatisticsTable({ series }: Props) {
    const [sort, setSort] = useState<{ column: Column['id']; direction: 'asc' | 'desc' }>({
        column: 'iso',
        direction: 'asc',
    });

    const sorted = [...series].sort((a, b) => {
        const left = a[sort.column];
        const right = b[sort.column];

        // Null averages sort as the smallest thing rather than jumping to the
        // top: a period that published nothing has no average, not a cheap one.
        const l = left === null ? -Infinity : left;
        const r = right === null ? -Infinity : right;

        const compared = typeof l === 'string' && typeof r === 'string' ? l.localeCompare(r) : Number(l) - Number(r);

        return sort.direction === 'asc' ? compared : -compared;
    });

    return (
        <div className="overflow-x-auto rounded-card border border-subtle bg-card shadow-card">
            <table className="w-full border-collapse text-left">
                <caption className="sr-only">
                    Statistics by period. Every column sorts; the CSV export writes these same rows.
                </caption>
                <thead className="table-sticky-header">
                    <tr className="border-b border-subtle bg-card">
                        {COLUMNS.map((column) => {
                            const active = sort.column === column.id;

                            return (
                                <th
                                    key={column.id}
                                    scope="col"
                                    aria-sort={
                                        active ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'
                                    }
                                    className={cn(
                                        'px-3 py-2.5 text-xs font-medium uppercase tracking-wide text-ink-500',
                                        column.numeric && 'text-right',
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSort((current) => ({
                                                column: column.id,
                                                direction:
                                                    current.column === column.id && current.direction === 'asc'
                                                        ? 'desc'
                                                        : 'asc',
                                            }))
                                        }
                                        className={cn(
                                            'inline-flex items-center gap-1 hover:text-ink-900',
                                            column.numeric && 'flex-row-reverse',
                                            active && 'text-ink-900',
                                        )}
                                    >
                                        {column.label}
                                        <SortIcon size={12} className={active ? 'opacity-100' : 'opacity-40'} />
                                    </button>
                                </th>
                            );
                        })}
                    </tr>
                </thead>

                <tbody>
                    {sorted.map((point) => (
                        <tr key={point.iso} className="border-b border-subtle last:border-0">
                            <td className="px-3 py-2 text-sm text-ink-900">{point.label}</td>
                            <td className="num px-3 py-2 text-right text-sm text-ink-700">{number(point.ordered)}</td>
                            <td className="num px-3 py-2 text-right text-sm text-ink-700">
                                {number(point.publishedCount)}
                            </td>
                            <td className="num px-3 py-2 text-right text-sm text-ink-700">{number(point.liveLinks)}</td>
                            <td className="num px-3 py-2 text-right text-sm text-ink-900">{money(point.spendCents)}</td>
                            <td className="num px-3 py-2 text-right text-sm text-ink-700">
                                {point.averageCents === null ? '—' : money(point.averageCents)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
