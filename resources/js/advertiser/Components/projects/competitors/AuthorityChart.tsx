import { useState } from 'react';
import { number } from '@shared/lib/format';
import type { CompetitorRow } from '@shared/types/competitors';
import { ChartCard, LegendSwatch, PAD, useMeasuredWidth } from '../statistics/chartFoundation';
import { AUTHORITY_COLORS } from './palette';

interface Props {
    self: CompetitorRow | null;
    competitors: CompetitorRow[];
}

const HEIGHT = 240;

/**
 * Domain Rating and Domain Authority, side by side, per domain.
 *
 * Both are 0–100 scores of the same idea, so they share one axis fixed at 100
 * rather than one scaled to the tallest bar: a chart whose axis ends at 42
 * makes a DR of 40 look like a dominant site. The fixed ceiling is what makes
 * two of these charts, on two different projects, comparable at a glance.
 *
 * They come from different vendors and no vendor sells both, so in practice one
 * of the two series is usually absent. Absent is drawn as absent — no bar, and
 * a note under the chart — never as a bar of height zero.
 */
export function AuthorityChart({ self, competitors }: Props) {
    const [hover, setHover] = useState<number | null>(null);
    const [box, width] = useMeasuredWidth();

    const rows = [...(self ? [self] : []), ...competitors].filter((row) => row.metrics !== null);

    const hasDr = rows.some((row) => row.metrics?.dr != null);
    const hasDa = rows.some((row) => row.metrics?.da != null);

    const plotWidth = Math.max(80, width - PAD.left - PAD.right);
    const plotHeight = HEIGHT - PAD.top - PAD.bottom - 14;
    const groupWidth = plotWidth / Math.max(1, rows.length);
    // Two bars in the middle 62% of each group: the gap between groups has to
    // read as larger than the 2px gap inside one, or the pairs stop pairing.
    const barWidth = Math.min(26, (groupWidth * 0.62) / 2);

    const y = (score: number) => PAD.top + plotHeight - (score / 100) * plotHeight;

    return (
        <ChartCard
            title="Authority comparison"
            explanation="Domain Rating and Domain Authority are two vendors’ scores for the same idea, both out of 100."
            empty={rows.length === 0 || (!hasDr && !hasDa)}
            legend={
                <ul className="flex flex-wrap gap-x-4 gap-y-1.5">
                    {hasDr && <LegendSwatch color={AUTHORITY_COLORS.dr} label="DR — Domain Rating" />}
                    {hasDa && <LegendSwatch color={AUTHORITY_COLORS.da} label="DA — Domain Authority" />}
                </ul>
            }
            table={<AuthorityTable rows={rows} />}
        >
            <div ref={box} className="relative">
                <svg width="100%" height={HEIGHT} role="img" aria-label="Domain authority scores per domain">
                    {[0, 25, 50, 75, 100].map((score) => (
                        <g key={score}>
                            <line
                                x1={PAD.left}
                                x2={PAD.left + plotWidth}
                                y1={y(score)}
                                y2={y(score)}
                                stroke="var(--ink-300)"
                                strokeWidth={1}
                                opacity={score === 0 ? 1 : 0.5}
                            />
                            <text
                                x={PAD.left - 8}
                                y={y(score) + 4}
                                textAnchor="end"
                                className="num"
                                fontSize="11"
                                fill="var(--ink-500)"
                            >
                                {score}
                            </text>
                        </g>
                    ))}

                    {rows.map((row, index) => {
                        const centre = PAD.left + groupWidth * (index + 0.5);
                        const scores: [number | null | undefined, string][] = [
                            [row.metrics?.dr, AUTHORITY_COLORS.dr],
                            [row.metrics?.da, AUTHORITY_COLORS.da],
                        ];

                        return (
                            <g key={row.id} onMouseEnter={() => setHover(index)} onMouseLeave={() => setHover(null)}>
                                {/* Your site's group gets a tint behind it, not
                                    a different bar colour: the bars carry which
                                    measure they are, and repainting them here
                                    would make the legend wrong for one group. */}
                                {row.isSelf && (
                                    <rect
                                        x={centre - groupWidth / 2}
                                        y={PAD.top}
                                        width={groupWidth}
                                        height={plotHeight}
                                        fill="var(--brand-blue-50)"
                                    />
                                )}

                                {/* A full-height target, so the tooltip does not
                                    require hitting a 26px bar. */}
                                <rect
                                    x={centre - groupWidth / 2}
                                    y={PAD.top}
                                    width={groupWidth}
                                    height={plotHeight}
                                    fill="transparent"
                                />

                                {scores.map(([score, color], i) =>
                                    score == null ? null : (
                                        <path
                                            key={i}
                                            // Rounded at the top, square at the
                                            // baseline: a column is measured
                                            // from the axis, and rounding that
                                            // end lifts the bar off the line it
                                            // is being read against.
                                            d={column(
                                                centre - barWidth - 1 + i * (barWidth + 2),
                                                y(score),
                                                barWidth,
                                                Math.max(2, PAD.top + plotHeight - y(score)),
                                            )}
                                            fill={color}
                                        />
                                    ),
                                )}

                                <text
                                    x={centre}
                                    y={HEIGHT - 4}
                                    textAnchor="middle"
                                    fontSize="11"
                                    fill={row.isSelf ? 'var(--brand-blue)' : 'var(--ink-500)'}
                                    fontWeight={row.isSelf ? 600 : 400}
                                >
                                    {shorten(row.domain, groupWidth)}
                                </text>
                            </g>
                        );
                    })}
                </svg>

                {hover !== null && rows[hover] && (
                    <div
                        role="status"
                        className="pointer-events-none absolute top-0 z-20 w-44 rounded-card border border-subtle bg-card px-3 py-2 shadow-card"
                        style={{
                            left: Math.min(
                                Math.max(PAD.left + groupWidth * (hover + 0.5) - 88, 0),
                                Math.max(0, width - 176),
                            ),
                        }}
                    >
                        <p className="truncate text-sm font-medium text-ink-900">{rows[hover].domain}</p>
                        <dl className="mt-1 flex flex-col gap-0.5">
                            <Row label="DR" value={rows[hover].metrics?.dr} />
                            <Row label="DA" value={rows[hover].metrics?.da} />
                        </dl>
                    </div>
                )}
            </div>

            {(!hasDr || !hasDa) && (
                <p className="mt-2 text-sm text-ink-500">
                    {hasDr ? 'Domain Authority' : 'Domain Rating'} is not sold by the provider these figures came from,
                    so it is not plotted.
                </p>
            )}
        </ChartCard>
    );
}

function Row({ label, value }: { label: string; value: number | null | undefined }) {
    return (
        <div className="flex items-baseline justify-between gap-2">
            <dt className="text-xs text-ink-500">{label}</dt>
            <dd className="num text-xs text-ink-900">{value == null ? 'not measured' : number(value)}</dd>
        </div>
    );
}

/** A bar with a 4px radius on its top two corners only. */
function column(x: number, y: number, width: number, height: number): string {
    const r = Math.min(4, width / 2, height);

    return [
        `M ${x} ${y + height}`,
        `L ${x} ${y + r}`,
        `A ${r} ${r} 0 0 1 ${x + r} ${y}`,
        `L ${x + width - r} ${y}`,
        `A ${r} ${r} 0 0 1 ${x + width} ${y + r}`,
        `L ${x + width} ${y + height}`,
        'Z',
    ].join(' ');
}

/** Roughly 7px a character at 11px type, with room for the ellipsis. */
function shorten(domain: string, available: number): string {
    const fits = Math.max(4, Math.floor(available / 7));

    return domain.length <= fits ? domain : `${domain.slice(0, fits - 1)}…`;
}

function AuthorityTable({ rows }: { rows: CompetitorRow[] }) {
    return (
        <table className="w-full border-collapse text-left text-sm">
            <caption className="sr-only">Domain Rating and Domain Authority per domain</caption>
            <thead>
                <tr className="border-b border-subtle">
                    <th scope="col" className="py-1.5 pr-3 font-medium text-ink-500">
                        Domain
                    </th>
                    <th scope="col" className="py-1.5 pr-3 text-right font-medium text-ink-500">
                        DR
                    </th>
                    <th scope="col" className="py-1.5 text-right font-medium text-ink-500">
                        DA
                    </th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id} className="border-b border-subtle last:border-0">
                        <th scope="row" className="py-1.5 pr-3 font-normal text-ink-700">
                            {row.isSelf ? `${row.domain} (your site)` : row.domain}
                        </th>
                        <td className="num py-1.5 pr-3 text-right text-ink-900">{row.metrics?.dr ?? '—'}</td>
                        <td className="num py-1.5 text-right text-ink-900">{row.metrics?.da ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
