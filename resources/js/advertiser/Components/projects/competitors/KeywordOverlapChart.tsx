import { number } from '@shared/lib/format';
import type { OverlapRow } from '@shared/types/competitors';
import { ChartCard, LegendSwatch } from '../statistics/chartFoundation';
import { OVERLAP_COLORS } from './palette';

interface Props {
    rows: OverlapRow[];
    onOpenGap: (row: OverlapRow) => void;
}

/**
 * Where each competitor's keywords sit relative to yours.
 *
 * One bar per competitor, split three ways: what you both rank for, what only
 * they rank for, and what only you do. Horizontal because the labels are
 * domains, and a domain read sideways is a domain nobody reads.
 *
 * The bars are not normalised to the same length. A rival with four times your
 * keywords should look four times as wide — that is the comparison. Each
 * segment carries its own number, so the picture is a shortcut to the figures
 * rather than the only place they exist.
 */
export function KeywordOverlapChart({ rows, onOpenGap }: Props) {
    const widest = Math.max(1, ...rows.map((row) => row.shared + row.theirs + row.yours));

    return (
        <ChartCard
            title="Keyword overlap"
            explanation="Search terms you and each competitor rank for — together, and apart."
            empty={rows.length === 0}
            legend={
                <ul className="flex flex-wrap gap-x-4 gap-y-1.5">
                    <LegendSwatch color={OVERLAP_COLORS.shared} label="You both rank for" />
                    <LegendSwatch color={OVERLAP_COLORS.theirs} label="Only they rank for" />
                    <LegendSwatch color={OVERLAP_COLORS.yours} label="Only you rank for" />
                </ul>
            }
            table={<OverlapTable rows={rows} />}
        >
            <ul className="flex flex-col gap-4">
                {rows.map((row) => {
                    const total = row.shared + row.theirs + row.yours;
                    const scale = (value: number) => `${(value / widest) * 100}%`;

                    return (
                        <li key={row.id}>
                            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <p className="min-w-0 truncate text-base text-ink-900">
                                    {row.domain}
                                    {row.label && <span className="ml-1.5 text-sm text-ink-500">{row.label}</span>}
                                </p>

                                {row.gapKeywords > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => onOpenGap(row)}
                                        className="text-sm font-medium text-brand hover:underline"
                                    >
                                        View gap keywords
                                    </button>
                                )}
                            </div>

                            {/* gap-0.5 is the 2px surface gap between adjacent
                                fills: without it two segments of similar
                                lightness read as one longer segment. */}
                            <div className="mt-1.5 flex h-5 w-full gap-0.5" aria-hidden="true">
                                <Segment width={scale(row.shared)} color={OVERLAP_COLORS.shared} round="left" />
                                <Segment width={scale(row.theirs)} color={OVERLAP_COLORS.theirs} />
                                <Segment width={scale(row.yours)} color={OVERLAP_COLORS.yours} round="right" />
                            </div>

                            <p className="num mt-1 text-xs text-ink-500">
                                <span className="text-ink-700">{number(row.shared)}</span> shared ·{' '}
                                <span className="text-ink-700">{number(row.theirs)}</span> only theirs ·{' '}
                                <span className="text-ink-700">{number(row.yours)}</span> only yours · {number(total)}{' '}
                                total
                            </p>
                        </li>
                    );
                })}
            </ul>
        </ChartCard>
    );
}

function Segment({ width, color, round }: { width: string; color: string; round?: 'left' | 'right' }) {
    // A zero-width segment is left out entirely rather than rendered at 0%: a
    // 4px rounded end on an empty segment still paints, and reads as a value.
    if (width === '0%') return null;

    return (
        <span
            className="h-full"
            style={{
                width,
                backgroundColor: color,
                borderRadius: round === 'left' ? '4px 0 0 4px' : round === 'right' ? '0 4px 4px 0' : undefined,
            }}
        />
    );
}

function OverlapTable({ rows }: { rows: OverlapRow[] }) {
    return (
        <table className="w-full border-collapse text-left text-sm">
            <caption className="sr-only">Keyword overlap between your site and each competitor</caption>
            <thead>
                <tr className="border-b border-subtle">
                    <th scope="col" className="py-1.5 pr-3 font-medium text-ink-500">
                        Domain
                    </th>
                    {['Shared', 'Only theirs', 'Only yours'].map((heading) => (
                        <th key={heading} scope="col" className="py-1.5 pr-3 text-right font-medium text-ink-500">
                            {heading}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id} className="border-b border-subtle last:border-0">
                        <th scope="row" className="py-1.5 pr-3 font-normal text-ink-700">
                            {row.domain}
                        </th>
                        <td className="num py-1.5 pr-3 text-right text-ink-900">{number(row.shared)}</td>
                        <td className="num py-1.5 pr-3 text-right text-ink-900">{number(row.theirs)}</td>
                        <td className="num py-1.5 pr-3 text-right text-ink-900">{number(row.yours)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
