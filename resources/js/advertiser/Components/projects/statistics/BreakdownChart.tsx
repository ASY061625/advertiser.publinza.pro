import { money, number } from '@shared/lib/format';
import type { StatisticsBreakdownRow } from '@shared/types/statistics';
import { ChartCard } from './chartFoundation';

interface Props {
    title: string;
    explanation: string;
    rows: StatisticsBreakdownRow[];
    onReset: () => void;
}

/**
 * Spend by category or by folder, as horizontal bars.
 *
 * Horizontal because the labels are words: category names rotated to fit under
 * vertical bars are the classic unreadable chart. One series, so one hue and no
 * legend — the title says what is plotted, and each row is directly labelled
 * with its own amount, so colour carries nothing here at all.
 *
 * Rows are already sorted and already capped at ten with the rest folded into
 * "Other" — see GetProjectStatistics. A top-ten that quietly drops the tail
 * would not add up to the spend on the card above it.
 */
const BAR = 'var(--brand-blue)';

export function BreakdownChart({ title, explanation, rows, onReset }: Props) {
    const max = Math.max(...rows.map((row) => row.spentCents), 1);
    const total = rows.reduce((sum, row) => sum + row.spentCents, 0);

    return (
        <ChartCard
            title={title}
            explanation={explanation}
            empty={rows.length === 0 || total === 0}
            onReset={onReset}
            table={
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr>
                            {['Name', 'Spend', 'Placements'].map((header) => (
                                <th key={header} scope="col" className="py-1 font-medium text-ink-500">
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.label}>
                                <td className="py-1 text-ink-700">{row.label}</td>
                                <td className="num py-1 text-ink-700">{money(row.spentCents)}</td>
                                <td className="num py-1 text-ink-700">{number(row.placements)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            }
        >
            <ul
                className="flex flex-col gap-2.5"
                role="img"
                aria-label={`${title}: ${rows.length} rows, ${money(total)} in total. The full figures are in the table below.`}
            >
                {rows.map((row) => (
                    <li key={row.label} className="grid grid-cols-[minmax(6rem,10rem)_1fr_auto] items-center gap-3">
                        <span className="truncate text-sm text-ink-700" title={row.label}>
                            {row.label}
                        </span>

                        <span aria-hidden="true" className="h-3 w-full rounded-pill bg-sunken">
                            <span
                                className="block h-3 rounded-pill"
                                style={{
                                    width: `${Math.max(2, (row.spentCents / max) * 100)}%`,
                                    backgroundColor: BAR,
                                }}
                            />
                        </span>

                        <span className="flex shrink-0 items-baseline gap-2">
                            <span className="num text-sm font-medium text-ink-900">{money(row.spentCents)}</span>
                            <span className="num text-xs text-ink-500">
                                {number(row.placements)} {row.placements === 1 ? 'post' : 'posts'}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </ChartCard>
    );
}
