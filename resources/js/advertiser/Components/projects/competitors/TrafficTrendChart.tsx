import { useMemo, useState } from 'react';
import { compactNumber, number } from '@shared/lib/format';
import { cn } from '@shared/lib/cn';
import type { TrendSeries } from '@shared/types/competitors';
import { ChartCard, PAD, labelStride, niceMax, useMeasuredWidth } from '../statistics/chartFoundation';
import { strokeFor } from './palette';

interface Props {
    months: string[];
    series: TrendSeries[];
}

const HEIGHT = 260;

/**
 * Twelve months of organic traffic, one line per domain.
 *
 * One y-axis, always: every line is the same measure in the same unit, and a
 * second scale would make where two lines cross a decision about axis ranges
 * rather than a fact about traffic.
 *
 * Lines are hidden rather than dropped when toggled off, and the scale is
 * recomputed from what remains — isolating one site should let it fill the plot
 * instead of hugging the floor under a rival ten times its size. What does not
 * change is which colour a domain has: that is fixed by the slot it was added
 * in, so hiding a line never repaints the others.
 */
export function TrafficTrendChart({ months, series }: Props) {
    const [hidden, setHidden] = useState<Set<number>>(new Set());
    const [hover, setHover] = useState<number | null>(null);
    const [box, width] = useMeasuredWidth();

    const visible = useMemo(() => series.filter((s) => !hidden.has(s.id)), [series, hidden]);

    const max = useMemo(() => {
        const values = visible.flatMap((s) => s.points.filter((p): p is number => p !== null));

        return niceMax(values.length === 0 ? 1 : Math.max(...values));
    }, [visible]);

    const plotWidth = Math.max(80, width - PAD.left - PAD.right);
    const plotHeight = HEIGHT - PAD.top - PAD.bottom;

    const x = (index: number) =>
        PAD.left + (months.length < 2 ? plotWidth / 2 : (index / (months.length - 1)) * plotWidth);
    const y = (value: number) => PAD.top + plotHeight - (value / max) * plotHeight;

    const stride = labelStride(months.length, plotWidth);

    function toggle(id: number) {
        setHidden((current) => {
            const next = new Set(current);

            if (next.has(id)) next.delete(id);
            else next.add(id);

            return next;
        });
    }

    return (
        <ChartCard
            title="Organic traffic trend"
            explanation="Estimated monthly visits from search, for your site and every competitor you track."
            empty={series.length === 0 || months.length === 0}
            legend={
                <ul className="flex flex-wrap gap-x-4 gap-y-1.5">
                    {series.map((line) => {
                        const stroke = strokeFor(line.slot);
                        const off = hidden.has(line.id);

                        return (
                            <li key={line.id}>
                                <button
                                    type="button"
                                    aria-pressed={!off}
                                    onClick={() => toggle(line.id)}
                                    className={cn(
                                        'flex items-center gap-2 rounded-button px-1 py-0.5 text-sm hover:bg-sunken',
                                        off ? 'text-ink-500' : 'text-ink-700',
                                    )}
                                >
                                    {/* The exact stroke the plot draws, so the
                                        dash pattern is part of the key rather
                                        than something to infer. */}
                                    <svg width="16" height="10" aria-hidden="true">
                                        <line
                                            x1="0"
                                            y1="5"
                                            x2="16"
                                            y2="5"
                                            stroke={stroke.color}
                                            strokeWidth={stroke.width}
                                            strokeDasharray={stroke.dash}
                                            opacity={off ? 0.35 : 1}
                                        />
                                    </svg>
                                    <span className={cn('truncate', off && 'line-through')}>
                                        {line.isSelf ? `${line.domain} (your site)` : line.domain}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            }
            table={<TrendTable months={months} series={series} />}
        >
            <div ref={box} className="relative">
                <svg
                    width="100%"
                    height={HEIGHT}
                    role="img"
                    aria-label={`Organic traffic over ${months.length} months for ${series.length} domains`}
                    onMouseLeave={() => setHover(null)}
                    onMouseMove={(event) => {
                        const bounds = event.currentTarget.getBoundingClientRect();
                        const offset = event.clientX - bounds.left - PAD.left;
                        const step = months.length < 2 ? plotWidth : plotWidth / (months.length - 1);

                        setHover(Math.max(0, Math.min(months.length - 1, Math.round(offset / step))));
                    }}
                >
                    {[0, 0.25, 0.5, 0.75, 1].map((fraction) => (
                        <g key={fraction}>
                            <line
                                x1={PAD.left}
                                x2={PAD.left + plotWidth}
                                y1={y(max * fraction)}
                                y2={y(max * fraction)}
                                stroke="var(--ink-300)"
                                strokeWidth={1}
                                opacity={fraction === 0 ? 1 : 0.5}
                            />
                            <text
                                x={PAD.left - 8}
                                y={y(max * fraction) + 4}
                                textAnchor="end"
                                className="num"
                                fontSize="11"
                                fill="var(--ink-500)"
                            >
                                {compactNumber(max * fraction)}
                            </text>
                        </g>
                    ))}

                    {months.map((month, index) =>
                        index % stride === 0 ? (
                            <text
                                key={month}
                                x={x(index)}
                                y={HEIGHT - 6}
                                textAnchor="middle"
                                fontSize="11"
                                fill="var(--ink-500)"
                            >
                                {monthLabel(month)}
                            </text>
                        ) : null,
                    )}

                    {hover !== null && (
                        <line
                            x1={x(hover)}
                            x2={x(hover)}
                            y1={PAD.top}
                            y2={PAD.top + plotHeight}
                            stroke="var(--ink-500)"
                            strokeWidth={1}
                            strokeDasharray="3 3"
                        />
                    )}

                    {visible.map((line) => {
                        const stroke = strokeFor(line.slot);

                        return (
                            <g key={line.id}>
                                {segments(line.points).map((run, i) => (
                                    <path
                                        key={i}
                                        d={run
                                            .map((p, j) => `${j === 0 ? 'M' : 'L'} ${x(p.index)} ${y(p.value)}`)
                                            .join(' ')}
                                        fill="none"
                                        stroke={stroke.color}
                                        strokeWidth={stroke.width}
                                        strokeDasharray={stroke.dash}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                ))}

                                {hover !== null && line.points[hover] != null && (
                                    <circle
                                        cx={x(hover)}
                                        cy={y(line.points[hover])}
                                        r={4}
                                        fill={stroke.color}
                                        // A 2px ring in the surface colour, so
                                        // two markers landing on one another
                                        // still read as two.
                                        stroke="var(--surface-card)"
                                        strokeWidth={2}
                                    />
                                )}
                            </g>
                        );
                    })}
                </svg>

                {hover !== null && visible.length > 0 && (
                    <div
                        role="status"
                        className="pointer-events-none absolute top-0 z-20 w-56 rounded-card border border-subtle bg-card px-3 py-2 shadow-card"
                        style={{ left: Math.min(Math.max(x(hover) - 112, 0), Math.max(0, width - 224)) }}
                    >
                        <p className="text-sm font-medium text-ink-900">{monthLabel(months[hover] ?? '', true)}</p>

                        <dl className="mt-1 flex flex-col gap-0.5">
                            {visible.map((line) => (
                                <div key={line.id} className="flex items-baseline justify-between gap-2">
                                    <dt className="truncate text-xs text-ink-500">{line.domain}</dt>
                                    <dd className="num text-xs text-ink-900">
                                        {line.points[hover] == null ? '—' : number(line.points[hover])}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                )}
            </div>
        </ChartCard>
    );
}

/**
 * Unbroken runs of measured months.
 *
 * A missing month is a month the provider had no figure for, which is not the
 * same as a month with no traffic. Drawing through the gap invents a slope;
 * dropping to zero and back invents a crash and a recovery. The line stops.
 */
function segments(points: (number | null)[]): { index: number; value: number }[][] {
    const runs: { index: number; value: number }[][] = [];
    let run: { index: number; value: number }[] = [];

    points.forEach((value, index) => {
        if (value === null) {
            if (run.length > 0) runs.push(run);
            run = [];
        } else {
            run.push({ index, value });
        }
    });

    if (run.length > 0) runs.push(run);

    // A single measured month surrounded by gaps has no line to draw, so it is
    // given a hairline of its own rather than disappearing.
    return runs.map((r) => (r.length === 1 ? [r[0]!, r[0]!] : r));
}

function monthLabel(month: string, long = false): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1, 1);

    return new Intl.DateTimeFormat('en-US', long ? { month: 'long', year: 'numeric' } : { month: 'short' }).format(
        date,
    );
}

function TrendTable({ months, series }: Props) {
    return (
        <table className="w-full border-collapse text-left text-sm">
            <caption className="sr-only">Monthly organic traffic per domain</caption>
            <thead>
                <tr className="border-b border-subtle">
                    <th scope="col" className="py-1.5 pr-3 font-medium text-ink-500">
                        Domain
                    </th>
                    {months.map((month) => (
                        <th key={month} scope="col" className="py-1.5 pr-3 text-right font-medium text-ink-500">
                            {monthLabel(month)}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {series.map((line) => (
                    <tr key={line.id} className="border-b border-subtle last:border-0">
                        <th scope="row" className="py-1.5 pr-3 font-normal text-ink-700">
                            {line.isSelf ? `${line.domain} (your site)` : line.domain}
                        </th>
                        {line.points.map((point, index) => (
                            <td key={index} className="num py-1.5 pr-3 text-right text-ink-900">
                                {point === null ? '—' : number(point)}
                            </td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
