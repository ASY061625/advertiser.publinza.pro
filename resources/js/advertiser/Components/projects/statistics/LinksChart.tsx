import { number } from '@shared/lib/format';
import type { StatisticsGranularity, StatisticsPoint } from '@shared/types/statistics';
import {
    ChartCard,
    ChartTooltip,
    LegendSwatch,
    PAD,
    labelStride,
    niceMax,
    useMeasuredWidth,
    useSharedHover,
} from './chartFoundation';
import { indexAt } from './BudgetChart';

interface Props {
    projectId: number;
    series: StatisticsPoint[];
    granularity: StatisticsGranularity;
    onReset: () => void;
}

/**
 * Links built per period, and the total live across all of them.
 *
 * Two plots on one shared x-axis, NOT one plot with a second y-scale. Links
 * added per period runs to single digits; links live runs to hundreds, so
 * putting them on one plot means choosing two axis ranges — and where the bars
 * and the line then appear to cross is decided by that choice rather than by
 * the data. The chart would invent a relationship. Stacked plots answer the
 * same question, "what happened in this period and where did it leave me",
 * without manufacturing the crossing.
 *
 * Blue and teal for the split: validated at ΔE 28.7 under deutan, 31.8 for
 * normal vision. Teal is under 3:1 against white, so the axis labels are
 * visible and the table view is always there.
 */
const DOFOLLOW = 'var(--brand-blue)';
const NOFOLLOW = 'var(--teal)';
const TOTAL = 'var(--ink-700)';
const BARS_H = 130;
const LINE_H = 92;
const GAP = 30;

export function LinksChart({ projectId, series, granularity, onReset }: Props) {
    const [ref, width] = useMeasuredWidth();
    const { index: hover, set: setHover } = useSharedHover();

    const empty = series.every((point) => point.dofollow === 0 && point.nofollow === 0);
    const plotW = Math.max(80, width - PAD.left - PAD.right);
    const step = series.length === 0 ? plotW : plotW / series.length;

    const maxPer = niceMax(Math.max(...series.map((p) => p.dofollow + p.nofollow), 0));
    const maxLive = niceMax(Math.max(...series.map((p) => p.liveLinks), 0));

    const barW = Math.max(3, Math.min(24, step - 2));
    const x = (i: number) => PAD.left + step * i + step / 2;
    const barY = (v: number) => PAD.top + BARS_H - (v / maxPer) * BARS_H;
    const lineY = (v: number) => PAD.top + LINE_H - (v / maxLive) * LINE_H;

    const linePath = series.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${lineY(p.liveLinks)}`).join(' ');
    const stride = labelStride(series.length, plotW);
    const active = hover === null ? null : series[hover];
    const height = PAD.top + BARS_H + GAP + LINE_H + PAD.bottom;

    return (
        <ChartCard
            title="Links"
            explanation="Links built in each period, split by whether they pass authority — and the running total live underneath."
            empty={empty}
            onReset={onReset}
            legend={
                <ul className="flex flex-wrap items-center gap-4">
                    <LegendSwatch color={DOFOLLOW} label="Dofollow" />
                    <LegendSwatch color={NOFOLLOW} label="Nofollow" />
                    <LegendSwatch color={TOTAL} label="Live in total" shape="line" />
                </ul>
            }
            table={
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr>
                            {['Period', 'Dofollow', 'Nofollow', 'Live in total'].map((header) => (
                                <th key={header} scope="col" className="py-1 font-medium text-ink-500">
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {series.map((point) => (
                            <tr key={point.iso}>
                                <td className="py-1 text-ink-700">{point.label}</td>
                                <td className="num py-1 text-ink-700">{number(point.dofollow)}</td>
                                <td className="num py-1 text-ink-700">{number(point.nofollow)}</td>
                                <td className="num py-1 text-ink-700">{number(point.liveLinks)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            }
        >
            <div ref={ref} className="relative">
                <svg
                    width={width}
                    height={height}
                    role="img"
                    aria-label={trendLabel(series)}
                    onMouseMove={(event) => setHover(indexAt(event, step, series.length))}
                    onMouseLeave={() => setHover(null)}
                    className="block"
                >
                    {/* ---- Built per period ---- */}
                    {[0, 0.5, 1].map((fraction) => (
                        <g key={`bar-${fraction}`}>
                            <line
                                x1={PAD.left}
                                x2={width - PAD.right}
                                y1={barY(maxPer * fraction)}
                                y2={barY(maxPer * fraction)}
                                stroke="var(--ink-300)"
                                strokeOpacity={fraction === 0 ? 0.9 : 0.4}
                            />
                            <text
                                x={PAD.left - 8}
                                y={barY(maxPer * fraction) + 4}
                                textAnchor="end"
                                className="num"
                                fontSize="11"
                                fill="var(--ink-500)"
                            >
                                {Math.round(maxPer * fraction)}
                            </text>
                        </g>
                    ))}

                    {series.map((point, i) => {
                        const total = point.dofollow + point.nofollow;
                        const topH = Math.max(0, PAD.top + BARS_H - barY(point.dofollow));
                        const wholeH = Math.max(0, PAD.top + BARS_H - barY(total));
                        const opacity = hover === null || hover === i ? 1 : 0.45;

                        return (
                            <g key={point.iso} opacity={opacity}>
                                {/* Nofollow sits above dofollow, with 2px of
                                    surface between the segments. */}
                                {point.nofollow > 0 && (
                                    <rect
                                        x={x(i) - barW / 2}
                                        y={barY(total)}
                                        width={barW}
                                        height={Math.max(0, wholeH - topH - 2)}
                                        rx={4}
                                        fill={NOFOLLOW}
                                    />
                                )}
                                {point.dofollow > 0 && (
                                    <rect
                                        x={x(i) - barW / 2}
                                        y={barY(point.dofollow)}
                                        width={barW}
                                        height={topH}
                                        rx={topH > 4 ? 4 : 0}
                                        fill={DOFOLLOW}
                                    />
                                )}
                            </g>
                        );
                    })}

                    {/* ---- Live in total ---- */}
                    <g transform={`translate(0, ${BARS_H + GAP})`}>
                        {[0, 1].map((fraction) => (
                            <g key={`line-${fraction}`}>
                                <line
                                    x1={PAD.left}
                                    x2={width - PAD.right}
                                    y1={lineY(maxLive * fraction)}
                                    y2={lineY(maxLive * fraction)}
                                    stroke="var(--ink-300)"
                                    strokeOpacity={fraction === 0 ? 0.9 : 0.4}
                                />
                                <text
                                    x={PAD.left - 8}
                                    y={lineY(maxLive * fraction) + 4}
                                    textAnchor="end"
                                    className="num"
                                    fontSize="11"
                                    fill="var(--ink-500)"
                                >
                                    {Math.round(maxLive * fraction)}
                                </text>
                            </g>
                        ))}

                        <path d={linePath} fill="none" stroke={TOTAL} strokeWidth={2} strokeLinejoin="round" />

                        {hover !== null && series[hover] && (
                            <circle
                                cx={x(hover)}
                                cy={lineY(series[hover].liveLinks)}
                                r={5}
                                fill={TOTAL}
                                stroke="var(--surface-card)"
                                strokeWidth={2}
                            />
                        )}
                    </g>

                    {series.map((point, i) =>
                        i % stride === 0 ? (
                            <text
                                key={point.iso}
                                x={x(i)}
                                y={height - 8}
                                textAnchor="middle"
                                fontSize="11"
                                fill="var(--ink-500)"
                            >
                                {point.label}
                            </text>
                        ) : null,
                    )}

                    {/* One crosshair through both plots: they share the x-axis,
                        so they share the period being read. */}
                    {hover !== null && (
                        <line
                            x1={x(hover)}
                            x2={x(hover)}
                            y1={PAD.top}
                            y2={PAD.top + BARS_H + GAP + LINE_H}
                            stroke="var(--ink-500)"
                            strokeOpacity={0.35}
                            strokeDasharray="3 3"
                        />
                    )}
                </svg>

                {active && hover !== null && (
                    <ChartTooltip
                        point={active}
                        x={x(hover)}
                        width={width}
                        projectId={projectId}
                        granularity={granularity}
                        rows={[
                            { label: 'Dofollow', value: number(active.dofollow) },
                            { label: 'Nofollow', value: number(active.nofollow) },
                            { label: 'Live in total', value: number(active.liveLinks) },
                        ]}
                    />
                )}
            </div>
        </ChartCard>
    );
}

function trendLabel(series: StatisticsPoint[]): string {
    const dofollow = series.reduce((sum, point) => sum + point.dofollow, 0);
    const nofollow = series.reduce((sum, point) => sum + point.nofollow, 0);
    const live = series[series.length - 1]?.liveLinks ?? 0;

    return `Links built per period: ${dofollow} dofollow and ${nofollow} nofollow, ending at ${live} live in total.`;
}
