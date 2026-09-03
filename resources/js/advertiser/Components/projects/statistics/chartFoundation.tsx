import { createContext, useContext, useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import type { StatisticsPoint } from '@shared/types/statistics';

/**
 * The pieces every chart on this tab is built from.
 *
 * Four charts share an x-axis of the same periods, so they share a hover index
 * too: pointing at March in one puts the crosshair on March in all of them.
 * That is the whole reason for the context — without it each chart would keep
 * its own idea of "the period being read".
 */
const HoverContext = createContext<{
    index: number | null;
    set: (index: number | null) => void;
}>({ index: null, set: () => undefined });

export function SharedHoverProvider({ children }: { children: ReactNode }) {
    const [index, setIndex] = useState<number | null>(null);

    return <HoverContext.Provider value={{ index, set: setIndex }}>{children}</HoverContext.Provider>;
}

export function useSharedHover() {
    return useContext(HoverContext);
}

/** Width of the plot, measured rather than assumed, so it survives a resize. */
export function useMeasuredWidth(fallback = 720) {
    const ref = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(fallback);

    useLayoutEffect(() => {
        const element = ref.current;
        if (!element) return;

        const observer = new ResizeObserver(([entry]) => {
            if (entry) setWidth(Math.max(280, entry.contentRect.width));
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return [ref, width] as const;
}

/** A round number at or above the value, so the axis reads in whole steps. */
export function niceMax(value: number): number {
    if (value <= 0) return 1;

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const scaled = value / magnitude;
    const step = scaled <= 1 ? 1 : scaled <= 2 ? 2 : scaled <= 5 ? 5 : 10;

    return step * magnitude;
}

/** Enough x labels to read, never so many that they collide. */
export function labelStride(count: number, plotWidth: number): number {
    return Math.max(1, Math.ceil(count / Math.max(1, Math.floor(plotWidth / 64))));
}

export const PAD = { left: 56, right: 16, top: 14, bottom: 24 };

/**
 * The card a chart sits in: title, one line of plain language, its own legend,
 * and — always — the same figures as a table for anyone the picture does not
 * reach.
 */
export function ChartCard({
    title,
    explanation,
    legend,
    control,
    children,
    table,
    empty = false,
    onReset,
}: {
    title: string;
    explanation: string;
    legend?: ReactNode;
    control?: ReactNode;
    children: ReactNode;
    table: ReactNode;
    empty?: boolean;
    onReset?: () => void;
}) {
    return (
        <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div className="min-w-0">
                    <h3 className="font-sora text-md font-semibold text-ink-900">{title}</h3>
                    <p className="mt-0.5 max-w-prose text-sm text-ink-500">{explanation}</p>
                </div>

                {control}
            </div>

            {empty ? (
                <div className="mt-4 flex flex-col items-center gap-3 rounded-card bg-sunken px-6 py-10 text-center">
                    <p className="text-sm text-ink-500">No data in this range</p>
                    {onReset && (
                        <button
                            type="button"
                            onClick={onReset}
                            className="rounded-button border border-subtle bg-card px-3 py-1.5 text-sm font-medium text-ink-700 hover:bg-sunken"
                        >
                            Reset to last 30 days
                        </button>
                    )}
                </div>
            ) : (
                <>
                    {legend && <div className="mt-3">{legend}</div>}
                    <div className="mt-2">{children}</div>
                </>
            )}

            {/* Always rendered, never visible by default. A chart that only
                exists as a picture is unreadable to a screen reader and
                unquotable to anyone who needs the number. */}
            <details className="mt-4">
                <summary className="cursor-pointer text-sm text-ink-500">View as a table</summary>
                <div className="mt-3 overflow-x-auto">{table}</div>
            </details>
        </section>
    );
}

/** A legend entry. Two series or more always get one; one series never does. */
export function LegendSwatch({
    color,
    label,
    shape = 'block',
}: {
    color: string;
    label: string;
    shape?: 'block' | 'line' | 'dashed';
}) {
    return (
        <li className="flex items-center gap-2 text-sm text-ink-700">
            {shape === 'block' ? (
                <span aria-hidden="true" className="size-2.5 rounded-[2px]" style={{ backgroundColor: color }} />
            ) : (
                <span
                    aria-hidden="true"
                    className={cn('h-0.5 w-4', shape === 'dashed' && 'opacity-90')}
                    style={
                        shape === 'dashed'
                            ? {
                                  backgroundImage: `repeating-linear-gradient(90deg, ${color} 0 4px, transparent 4px 7px)`,
                              }
                            : { backgroundColor: color }
                    }
                />
            )}
            {label}
        </li>
    );
}

/**
 * The tooltip every chart shows, including the link out to the posts behind
 * the period — a number worth reading is usually a number worth opening.
 */
export function ChartTooltip({
    point,
    x,
    width,
    projectId,
    rows,
    granularity,
}: {
    point: StatisticsPoint;
    x: number;
    width: number;
    projectId: number;
    rows: { label: string; value: string }[];
    granularity: string;
}) {
    return (
        <div
            role="status"
            className="pointer-events-auto absolute top-0 z-20 w-44 rounded-card border border-subtle bg-card px-3 py-2 shadow-card"
            style={{ left: Math.min(Math.max(x - 88, 0), Math.max(0, width - 176)) }}
        >
            <p className="text-sm font-medium text-ink-900">{point.label}</p>

            <dl className="mt-1 flex flex-col gap-0.5">
                {rows.map((row) => (
                    <div key={row.label} className="flex items-baseline justify-between gap-2">
                        <dt className="text-xs text-ink-500">{row.label}</dt>
                        <dd className="num text-xs text-ink-900">{row.value}</dd>
                    </div>
                ))}
            </dl>

            <Link
                href={postsHref(projectId, point, granularity)}
                className="mt-1.5 inline-block text-xs font-medium text-brand hover:underline"
            >
                View posts
            </Link>
        </div>
    );
}

/**
 * The post grid, filtered to exactly the period under the cursor.
 *
 * Published rather than created: every figure in these tooltips is keyed off
 * when a placement went live, so the grid it opens has to be too.
 */
function postsHref(projectId: number, point: StatisticsPoint, granularity: string): string {
    const from = new Date(point.iso);
    const to = new Date(from);

    if (granularity === 'month') to.setMonth(to.getMonth() + 1);
    else if (granularity === 'week') to.setDate(to.getDate() + 7);
    else to.setDate(to.getDate() + 1);

    to.setDate(to.getDate() - 1);

    const query = new URLSearchParams({
        tab: 'posts',
        date_field: 'published',
        from: point.iso,
        to: to.toISOString().slice(0, 10),
    });

    return `/projects/${projectId}?${query.toString()}`;
}

/** Blocks of the right shape while the numbers are on their way. */
export function ChartSkeleton({ height = 180 }: { height?: number }) {
    return (
        <div className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <div className="h-4 w-40 animate-pulse rounded-pill bg-sunken" />
            <div className="mt-2 h-3 w-72 animate-pulse rounded-pill bg-sunken" />
            <div className="mt-5 flex items-end gap-2" style={{ height }}>
                {Array.from({ length: 14 }).map((_, index) => (
                    <div
                        key={index}
                        className="flex-1 animate-pulse rounded-t-[4px] bg-sunken"
                        // A varied, deterministic skeleton: a flat row of equal
                        // blocks reads as a rendered chart with no data.
                        style={{ height: `${35 + ((index * 37) % 60)}%` }}
                    />
                ))}
            </div>
        </div>
    );
}

/** Fires the callback whenever the shared hover leaves every chart. */
export function useHoverReset(onLeave: () => void) {
    const { index } = useSharedHover();

    useEffect(() => {
        if (index === null) onLeave();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [index]);
}
