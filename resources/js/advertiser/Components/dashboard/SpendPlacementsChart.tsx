import { useEffect, useRef, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { money } from '@shared/lib/format';
import type { Granularity, SeriesPoint } from '@shared/types/dashboard';

interface Props {
    series: SeriesPoint[];
    granularity: Granularity;
    onGranularityChange: (value: Granularity) => void;
}

/**
 * Placements and spend over time, as two plots stacked on one shared x-axis.
 *
 * Deliberately NOT a dual-axis chart. With two independent y-scales on one
 * plot, where the bars and the line appear to cross is decided by the axis
 * ranges rather than by the data, so the chart invents a relationship. Two
 * plots sharing an x-axis and one crosshair answer the same question — "what
 * happened in this period" — without manufacturing that crossing.
 *
 * Colours are validated: brand blue and teal separate at ΔE 28.7 under deutan
 * and 31.8 for normal vision. Teal sits below 3:1 against white, so the spend
 * plot carries visible axis labels and a table view rather than relying on the
 * line's colour alone.
 */

const BAR_COLOR = 'var(--brand-blue)';
const LINE_COLOR = 'var(--teal)';

const GRANULARITIES: { value: Granularity; label: string }[] = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
];

const PAD = { left: 52, right: 16, top: 12, bottom: 22 };
const BAR_PLOT_H = 132;
const LINE_PLOT_H = 108;

function niceMax(value: number): number {
    if (value <= 0) return 1;

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const scaled = value / magnitude;
    const step = scaled <= 1 ? 1 : scaled <= 2 ? 2 : scaled <= 5 ? 5 : 10;

    return step * magnitude;
}

export function SpendPlacementsChart({ series, granularity, onGranularityChange }: Props) {
    const wrapRef = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(720);
    const [hover, setHover] = useState<number | null>(null);

    useEffect(() => {
        const element = wrapRef.current;
        if (!element) return;

        const observer = new ResizeObserver(([entry]) => {
            if (entry) setWidth(Math.max(320, entry.contentRect.width));
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const plotW = Math.max(80, width - PAD.left - PAD.right);
    const maxPlacements = niceMax(Math.max(...series.map((p) => p.placements), 0));
    const maxSpend = niceMax(Math.max(...series.map((p) => p.spendCents), 0));

    const step = series.length === 0 ? plotW : plotW / series.length;
    // 2px of surface between adjacent bars, and never wider than 28px.
    const barW = Math.max(3, Math.min(28, step - 2));

    const xFor = (index: number) => PAD.left + step * index + step / 2;
    const barY = (v: number) => PAD.top + BAR_PLOT_H - (v / maxPlacements) * BAR_PLOT_H;
    const lineY = (v: number) => PAD.top + LINE_PLOT_H - (v / maxSpend) * LINE_PLOT_H;

    const linePath = series
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${xFor(index)} ${lineY(point.spendCents)}`)
        .join(' ');

    // Selective direct labels: the peak of each plot only, never every point.
    const peakPlacements = series.reduce(
        (best, p, i) => (p.placements > (series[best]?.placements ?? -1) ? i : best),
        0,
    );
    const peakSpend = series.reduce((best, p, i) => (p.spendCents > (series[best]?.spendCents ?? -1) ? i : best), 0);

    // Enough labels to read, never so many they collide.
    const labelEvery = Math.max(1, Math.ceil(series.length / Math.floor(plotW / 64)));

    const active = hover === null ? null : series[hover];

    function onMove(event: React.MouseEvent<SVGSVGElement>) {
        const rect = event.currentTarget.getBoundingClientRect();
        const x = event.clientX - rect.left - PAD.left;
        const index = Math.floor(x / step);

        setHover(index >= 0 && index < series.length ? index : null);
    }

    return (
        <div ref={wrapRef} className="relative">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                {/* Two series, so a legend is always present. */}
                <ul className="flex items-center gap-4">
                    <li className="flex items-center gap-2 text-sm text-ink-700">
                        <span className="size-2.5 rounded-[2px]" style={{ backgroundColor: BAR_COLOR }} />
                        Placements
                    </li>
                    <li className="flex items-center gap-2 text-sm text-ink-700">
                        <span className="h-0.5 w-4 rounded-pill" style={{ backgroundColor: LINE_COLOR }} />
                        Spend
                    </li>
                </ul>

                <div className="flex rounded-button border border-subtle p-0.5" role="group" aria-label="Granularity">
                    {GRANULARITIES.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            aria-pressed={granularity === option.value}
                            onClick={() => onGranularityChange(option.value)}
                            className={cn(
                                'rounded-[4px] px-2.5 py-1 font-sora text-sm font-medium transition-colors duration-fast',
                                granularity === option.value
                                    ? 'bg-brand-subtle text-brand'
                                    : 'text-ink-500 hover:text-ink-700',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            <svg
                width={width}
                height={PAD.top + BAR_PLOT_H + 28 + LINE_PLOT_H + PAD.bottom}
                role="img"
                aria-label="Placements and spend over time"
                onMouseMove={onMove}
                onMouseLeave={() => setHover(null)}
                className="block"
            >
                {/* ---- Placements ---- */}
                {[0, 0.5, 1].map((fraction) => (
                    <g key={`bar-grid-${fraction}`}>
                        <line
                            x1={PAD.left}
                            x2={width - PAD.right}
                            y1={barY(maxPlacements * fraction)}
                            y2={barY(maxPlacements * fraction)}
                            stroke="var(--ink-300)"
                            strokeOpacity={fraction === 0 ? 0.9 : 0.4}
                        />
                        <text
                            x={PAD.left - 8}
                            y={barY(maxPlacements * fraction) + 4}
                            textAnchor="end"
                            className="num"
                            fontSize="11"
                            fill="var(--ink-500)"
                        >
                            {Math.round(maxPlacements * fraction)}
                        </text>
                    </g>
                ))}

                {series.map((point, index) => {
                    const height = Math.max(0, PAD.top + BAR_PLOT_H - barY(point.placements));

                    return (
                        <rect
                            key={`bar-${point.iso}`}
                            x={xFor(index) - barW / 2}
                            y={barY(point.placements)}
                            width={barW}
                            height={height}
                            rx={height > 4 ? 4 : 0}
                            fill={BAR_COLOR}
                            opacity={hover === null || hover === index ? 1 : 0.45}
                        />
                    );
                })}

                {series[peakPlacements] && series[peakPlacements].placements > 0 && (
                    <text
                        x={xFor(peakPlacements)}
                        y={barY(series[peakPlacements].placements) - 6}
                        textAnchor="middle"
                        className="num"
                        fontSize="11"
                        fontWeight="500"
                        fill="var(--ink-900)"
                    >
                        {series[peakPlacements].placements}
                    </text>
                )}

                {/* ---- Spend ---- */}
                <g transform={`translate(0, ${BAR_PLOT_H + 28})`}>
                    {[0, 1].map((fraction) => (
                        <g key={`line-grid-${fraction}`}>
                            <line
                                x1={PAD.left}
                                x2={width - PAD.right}
                                y1={lineY(maxSpend * fraction)}
                                y2={lineY(maxSpend * fraction)}
                                stroke="var(--ink-300)"
                                strokeOpacity={fraction === 0 ? 0.9 : 0.4}
                            />
                            <text
                                x={PAD.left - 8}
                                y={lineY(maxSpend * fraction) + 4}
                                textAnchor="end"
                                className="num"
                                fontSize="11"
                                fill="var(--ink-500)"
                            >
                                {money(Math.round(maxSpend * fraction)).replace('.00', '')}
                            </text>
                        </g>
                    ))}

                    <path d={linePath} fill="none" stroke={LINE_COLOR} strokeWidth={2} strokeLinejoin="round" />

                    {series[peakSpend] && series[peakSpend].spendCents > 0 && (
                        <text
                            x={xFor(peakSpend)}
                            y={lineY(series[peakSpend].spendCents) - 8}
                            textAnchor="middle"
                            className="num"
                            fontSize="11"
                            fontWeight="500"
                            fill="var(--ink-900)"
                        >
                            {money(series[peakSpend].spendCents).replace('.00', '')}
                        </text>
                    )}

                    {hover !== null && series[hover] && (
                        <circle
                            cx={xFor(hover)}
                            cy={lineY(series[hover].spendCents)}
                            r={5}
                            fill={LINE_COLOR}
                            stroke="var(--surface-card)"
                            strokeWidth={2}
                        />
                    )}
                </g>

                {/* ---- Shared x-axis and crosshair ---- */}
                {series.map((point, index) =>
                    index % labelEvery === 0 ? (
                        <text
                            key={`x-${point.iso}`}
                            x={xFor(index)}
                            y={PAD.top + BAR_PLOT_H + 28 + LINE_PLOT_H + 16}
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
                        x1={xFor(hover)}
                        x2={xFor(hover)}
                        y1={PAD.top}
                        y2={PAD.top + BAR_PLOT_H + 28 + LINE_PLOT_H}
                        stroke="var(--ink-500)"
                        strokeOpacity={0.35}
                        strokeDasharray="3 3"
                    />
                )}
            </svg>

            {/* One tooltip, both measures. */}
            {active && (
                <div
                    role="status"
                    className="pointer-events-none absolute top-10 z-10 rounded-card border border-subtle bg-card px-3 py-2 shadow-card"
                    style={{
                        left: Math.min(Math.max(xFor(hover ?? 0) - 60, 0), Math.max(0, width - 132)),
                    }}
                >
                    <p className="text-sm font-medium text-ink-900">{active.label}</p>
                    <p className="num mt-1 text-sm text-ink-700">
                        {active.placements} {active.placements === 1 ? 'placement' : 'placements'}
                    </p>
                    <p className="num text-sm text-ink-700">{money(active.spendCents)} spent</p>
                </div>
            )}

            {/* Identity never rests on colour alone: the same figures as a table. */}
            <details className="mt-4">
                <summary className="cursor-pointer text-sm text-ink-500">View as a table</summary>
                <table className="mt-3 w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th scope="col" className="py-1 font-medium text-ink-500">
                                Period
                            </th>
                            <th scope="col" className="num py-1 text-right font-medium text-ink-500">
                                Placements
                            </th>
                            <th scope="col" className="num py-1 text-right font-medium text-ink-500">
                                Spend
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {series.map((point) => (
                            <tr key={`row-${point.iso}`}>
                                <td className="py-1 text-ink-700">{point.label}</td>
                                <td className="num py-1 text-right text-ink-700">{point.placements}</td>
                                <td className="num py-1 text-right text-ink-700">{money(point.spendCents)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </details>
        </div>
    );
}
