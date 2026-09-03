import { useId, useState } from 'react';
import { money } from '@shared/lib/format';
import { Switch } from '@shared/ui';
import type { StatisticsPoint, StatisticsGranularity } from '@shared/types/statistics';
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

interface Props {
    projectId: number;
    series: StatisticsPoint[];
    granularity: StatisticsGranularity;
    onReset: () => void;
}

const SPEND = 'var(--brand-blue)';
const PLOT_H = 190;

/**
 * Spend per period, as an area.
 *
 * One series, so no legend box is needed until the cumulative line is switched
 * on — the title names what is plotted. The cumulative line is the same hue
 * dashed rather than a second colour: it is the same measure accumulated, not
 * a different thing, and a new hue would say otherwise.
 *
 * Both series share one dollar axis, so the line and the area can be compared
 * directly. That is only true because they are the same unit; the moment two
 * measures differ in unit they get their own plot, never a second y-scale.
 */
export function BudgetChart({ projectId, series, granularity, onReset }: Props) {
    const [ref, width] = useMeasuredWidth();
    const { index: hover, set: setHover } = useSharedHover();
    const [cumulative, setCumulative] = useState(false);
    const gradientId = useId();

    const empty = series.every((point) => point.spendCents === 0);
    const plotW = Math.max(80, width - PAD.left - PAD.right);
    const step = series.length === 0 ? plotW : plotW / series.length;

    // The cumulative line is by definition the tallest thing on the plot, so
    // the axis has to make room for it or switching it on would clip it.
    const peak = Math.max(...series.map((point) => (cumulative ? point.cumulativeSpendCents : point.spendCents)), 0);
    const max = niceMax(peak);

    const x = (i: number) => PAD.left + step * i + step / 2;
    const y = (v: number) => PAD.top + PLOT_H - (v / max) * PLOT_H;

    const areaPath =
        series.length === 0
            ? ''
            : `M ${x(0)} ${PAD.top + PLOT_H} ` +
              series.map((p, i) => `L ${x(i)} ${y(p.spendCents)}`).join(' ') +
              ` L ${x(series.length - 1)} ${PAD.top + PLOT_H} Z`;

    const linePath = series.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(p.spendCents)}`).join(' ');
    const cumulativePath = series
        .map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(p.cumulativeSpendCents)}`)
        .join(' ');

    const stride = labelStride(series.length, plotW);
    const peakIndex = series.reduce((best, p, i) => (p.spendCents > (series[best]?.spendCents ?? -1) ? i : best), 0);
    const active = hover === null ? null : series[hover];

    return (
        <ChartCard
            title="Budget over time"
            explanation="What you spent in each period. A placement counts on the day its link went live."
            empty={empty}
            onReset={onReset}
            control={<Switch label="Show running total" checked={cumulative} onCheckedChange={setCumulative} />}
            legend={
                cumulative ? (
                    <ul className="flex flex-wrap items-center gap-4">
                        <LegendSwatch color={SPEND} label="Spend in period" />
                        <LegendSwatch color={SPEND} label="Running total" shape="dashed" />
                    </ul>
                ) : undefined
            }
            table={<SeriesTable series={series} />}
        >
            <div ref={ref} className="relative">
                <svg
                    width={width}
                    height={PAD.top + PLOT_H + PAD.bottom}
                    role="img"
                    aria-label={trendLabel(series)}
                    onMouseMove={(event) => setHover(indexAt(event, step, series.length))}
                    onMouseLeave={() => setHover(null)}
                    className="block"
                >
                    <defs>
                        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={SPEND} stopOpacity={0.2} />
                            <stop offset="100%" stopColor={SPEND} stopOpacity={0.02} />
                        </linearGradient>
                    </defs>

                    {[0, 0.5, 1].map((fraction) => (
                        <g key={fraction}>
                            <line
                                x1={PAD.left}
                                x2={width - PAD.right}
                                y1={y(max * fraction)}
                                y2={y(max * fraction)}
                                stroke="var(--ink-300)"
                                strokeOpacity={fraction === 0 ? 0.9 : 0.4}
                            />
                            <text
                                x={PAD.left - 8}
                                y={y(max * fraction) + 4}
                                textAnchor="end"
                                className="num"
                                fontSize="11"
                                fill="var(--ink-500)"
                            >
                                {money(Math.round(max * fraction)).replace('.00', '')}
                            </text>
                        </g>
                    ))}

                    <path d={areaPath} fill={`url(#${gradientId})`} />
                    <path d={linePath} fill="none" stroke={SPEND} strokeWidth={2} strokeLinejoin="round" />

                    {cumulative && (
                        <path
                            d={cumulativePath}
                            fill="none"
                            stroke={SPEND}
                            strokeWidth={2}
                            strokeDasharray="5 4"
                            strokeLinejoin="round"
                            opacity={0.75}
                        />
                    )}

                    {/* One direct label, on the peak. Never a number on every point. */}
                    {series[peakIndex] && series[peakIndex].spendCents > 0 && (
                        <text
                            x={x(peakIndex)}
                            y={y(series[peakIndex].spendCents) - 8}
                            textAnchor="middle"
                            className="num"
                            fontSize="11"
                            fontWeight="500"
                            fill="var(--ink-900)"
                        >
                            {money(series[peakIndex].spendCents).replace('.00', '')}
                        </text>
                    )}

                    {series.map((point, i) =>
                        i % stride === 0 ? (
                            <text
                                key={point.iso}
                                x={x(i)}
                                y={PAD.top + PLOT_H + 16}
                                textAnchor="middle"
                                fontSize="11"
                                fill="var(--ink-500)"
                            >
                                {point.label}
                            </text>
                        ) : null,
                    )}

                    {hover !== null && series[hover] && (
                        <>
                            <line
                                x1={x(hover)}
                                x2={x(hover)}
                                y1={PAD.top}
                                y2={PAD.top + PLOT_H}
                                stroke="var(--ink-500)"
                                strokeOpacity={0.35}
                                strokeDasharray="3 3"
                            />
                            <circle
                                cx={x(hover)}
                                cy={y(series[hover].spendCents)}
                                r={5}
                                fill={SPEND}
                                stroke="var(--surface-card)"
                                strokeWidth={2}
                            />
                        </>
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
                            { label: 'Spend', value: money(active.spendCents) },
                            ...(cumulative
                                ? [{ label: 'Running total', value: money(active.cumulativeSpendCents) }]
                                : []),
                        ]}
                    />
                )}
            </div>
        </ChartCard>
    );
}

/** The pointer's x, as an index into the series. */
export function indexAt(event: React.MouseEvent<SVGSVGElement>, step: number, count: number): number | null {
    const rect = event.currentTarget.getBoundingClientRect();
    const index = Math.floor((event.clientX - rect.left - PAD.left) / step);

    return index >= 0 && index < count ? index : null;
}

/** What a screen reader is told the shape of the line is. */
function trendLabel(series: StatisticsPoint[]): string {
    if (series.length === 0) return 'Spend over time. No periods in this range.';

    const first = series[0]?.spendCents ?? 0;
    const last = series[series.length - 1]?.spendCents ?? 0;
    const total = series.reduce((sum, point) => sum + point.spendCents, 0);

    const direction = last > first ? 'rising' : last < first ? 'falling' : 'flat';

    return `Spend over time, ${direction} across ${series.length} periods, ${money(total)} in total.`;
}

export function SeriesTable({ series }: { series: StatisticsPoint[] }) {
    return (
        <table className="w-full text-left text-sm">
            <thead>
                <tr>
                    {['Period', 'Spend', 'Running total'].map((header) => (
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
                        <td className="num py-1 text-ink-700">{money(point.spendCents)}</td>
                        <td className="num py-1 text-ink-700">{money(point.cumulativeSpendCents)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
