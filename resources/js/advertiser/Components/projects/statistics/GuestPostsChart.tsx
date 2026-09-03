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
 * Published against ordered, per period.
 *
 * Two bars side by side, not stacked: they are not parts of a whole. A post
 * ordered in March and published in May belongs to two different periods, so
 * stacking them would draw a total that means nothing.
 *
 * Teal and gold: validated at ΔE 14.1 under protan and 24.9 for normal vision.
 * Both sit under 3:1 against white, so the axis carries visible labels and the
 * table view is always present rather than the bars relying on hue alone.
 */
const PUBLISHED = 'var(--teal)';
const ORDERED = 'var(--gold)';
const PLOT_H = 170;

export function GuestPostsChart({ projectId, series, granularity, onReset }: Props) {
    const [ref, width] = useMeasuredWidth();
    const { index: hover, set: setHover } = useSharedHover();

    const empty = series.every((point) => point.publishedCount === 0 && point.ordered === 0);
    const plotW = Math.max(80, width - PAD.left - PAD.right);
    const step = series.length === 0 ? plotW : plotW / series.length;

    const max = niceMax(Math.max(...series.map((p) => Math.max(p.publishedCount, p.ordered)), 0));

    // Two bars in the slot, 2px of surface between them and 2px to the next
    // period's pair, and never wider than 14px each.
    const barW = Math.max(3, Math.min(14, (step - 6) / 2));

    const x = (i: number) => PAD.left + step * i + step / 2;
    const y = (v: number) => PAD.top + PLOT_H - (v / max) * PLOT_H;
    const stride = labelStride(series.length, plotW);
    const active = hover === null ? null : series[hover];

    return (
        <ChartCard
            title="Guest posts"
            explanation="Posts that went live in each period, against the posts you ordered in it. The two rarely land in the same period."
            empty={empty}
            onReset={onReset}
            legend={
                <ul className="flex flex-wrap items-center gap-4">
                    <LegendSwatch color={PUBLISHED} label="Published" />
                    <LegendSwatch color={ORDERED} label="Ordered, not yet published" />
                </ul>
            }
            table={
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr>
                            {['Period', 'Ordered', 'Published'].map((header) => (
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
                                <td className="num py-1 text-ink-700">{number(point.ordered)}</td>
                                <td className="num py-1 text-ink-700">{number(point.publishedCount)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            }
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
                                {Math.round(max * fraction)}
                            </text>
                        </g>
                    ))}

                    {series.map((point, i) =>
                        (
                            [
                                { key: 'published', value: point.publishedCount, color: PUBLISHED, offset: -barW - 1 },
                                { key: 'ordered', value: point.ordered, color: ORDERED, offset: 1 },
                            ] as const
                        ).map((bar) => {
                            const height = Math.max(0, PAD.top + PLOT_H - y(bar.value));

                            return (
                                <rect
                                    key={`${point.iso}-${bar.key}`}
                                    x={x(i) + bar.offset}
                                    y={y(bar.value)}
                                    width={barW}
                                    height={height}
                                    rx={height > 4 ? 4 : 0}
                                    fill={bar.color}
                                    opacity={hover === null || hover === i ? 1 : 0.45}
                                />
                            );
                        }),
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

                    {hover !== null && (
                        <line
                            x1={x(hover)}
                            x2={x(hover)}
                            y1={PAD.top}
                            y2={PAD.top + PLOT_H}
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
                            { label: 'Published', value: number(active.publishedCount) },
                            { label: 'Ordered', value: number(active.ordered) },
                        ]}
                    />
                )}
            </div>
        </ChartCard>
    );
}

function trendLabel(series: StatisticsPoint[]): string {
    const published = series.reduce((sum, point) => sum + point.publishedCount, 0);
    const ordered = series.reduce((sum, point) => sum + point.ordered, 0);

    return `Guest posts per period: ${published} published and ${ordered} ordered across ${series.length} periods.`;
}
