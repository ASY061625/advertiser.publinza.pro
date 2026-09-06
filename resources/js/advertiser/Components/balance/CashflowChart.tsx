import { useState } from 'react';
import { money } from '@shared/lib/format';
import { useMeasuredWidth, niceMax } from '../projects/statistics/chartFoundation';
import type { MonthPoint } from '@shared/types/balance';

/**
 * Twelve months of money in against money out.
 *
 * Two series, so a legend is always present and both are named in the tooltip —
 * identity is never carried by hue alone. Teal sits under 3:1 against the card,
 * which is what obliges those labels and the table below rather than making it
 * optional.
 *
 * Deposits, not "everything that raised the balance": a volume bonus is not
 * money anybody paid in, and folding it in here would flatter the chart.
 */
const DEPOSITS = 'var(--teal)';
const SPEND = 'var(--brand-blue)';

const PLOT_H = 180;
const PAD = { left: 62, right: 16, top: 14, bottom: 26 };

export function CashflowChart({ series }: { series: MonthPoint[] }) {
    const [ref, width] = useMeasuredWidth();
    const [hover, setHover] = useState<number | null>(null);

    const empty = series.every((point) => point.depositCents === 0 && point.spendCents === 0);
    const plotW = Math.max(80, width - PAD.left - PAD.right);
    const step = series.length === 0 ? plotW : plotW / series.length;

    const max = niceMax(Math.max(...series.map((p) => Math.max(p.depositCents, p.spendCents)), 0));

    // Two bars in the slot, 2px of surface between them and 2px to the next
    // month's pair, never wider than 14px each.
    const barW = Math.max(3, Math.min(14, (step - 6) / 2));

    const x = (i: number) => PAD.left + step * i + step / 2;
    const y = (v: number) => PAD.top + PLOT_H - (v / max) * PLOT_H;

    const active = hover === null ? null : series[hover];

    return (
        <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div className="min-w-0">
                    <h2 className="font-sora text-md font-semibold text-ink-900">Deposits and spend</h2>
                    <p className="mt-0.5 max-w-prose text-sm text-ink-500">
                        What you paid in each month, against what placements cost you. A placement counts in the
                        month its link was verified, not the month it was ordered.
                    </p>
                </div>
            </div>

            {empty ? (
                <p className="mt-4 rounded-card bg-sunken px-6 py-10 text-center text-sm text-ink-500">
                    Nothing has moved in or out yet.
                </p>
            ) : (
                <>
                    <ul className="mt-3 flex flex-wrap items-center gap-4">
                        <Swatch color={DEPOSITS} label="Deposits" />
                        <Swatch color={SPEND} label="Spend" />
                    </ul>

                    <div ref={ref} className="relative mt-2">
                        <svg
                            width={width}
                            height={PAD.top + PLOT_H + PAD.bottom}
                            role="img"
                            aria-label={summary(series)}
                            onMouseMove={(event) => {
                                const box = event.currentTarget.getBoundingClientRect();
                                const at = Math.floor((event.clientX - box.left - PAD.left) / step);

                                setHover(at >= 0 && at < series.length ? at : null);
                            }}
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
                                        {money(max * fraction).replace('.00', '')}
                                    </text>
                                </g>
                            ))}

                            {series.map((point, i) =>
                                (
                                    [
                                        { key: 'in', value: point.depositCents, color: DEPOSITS, offset: -barW - 1 },
                                        { key: 'out', value: point.spendCents, color: SPEND, offset: 1 },
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

                            {series.map((point, i) => (
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
                            ))}
                        </svg>

                        {active && (
                            <div
                                role="status"
                                className="pointer-events-none absolute top-0 z-20 w-44 rounded-card border border-subtle bg-card px-3 py-2 shadow-card"
                                style={{
                                    left: Math.min(Math.max(x(hover!) - 88, 0), Math.max(0, width - 176)),
                                }}
                            >
                                <p className="text-sm font-medium text-ink-900">{active.label}</p>

                                <dl className="mt-1 flex flex-col gap-0.5">
                                    <Row label="Deposits" value={money(active.depositCents)} />
                                    <Row label="Spend" value={money(active.spendCents)} />
                                </dl>
                            </div>
                        )}
                    </div>
                </>
            )}

            {/* Always rendered, never open by default. Teal is under 3:1 against
                this card, so the figures have to be readable without the hue. */}
            <details className="mt-4">
                <summary className="cursor-pointer text-sm text-ink-500">View as a table</summary>
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Month', 'Deposits', 'Spend'].map((header) => (
                                    <th key={header} scope="col" className="py-1 font-medium text-ink-500">
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {series.map((point) => (
                                <tr key={point.iso}>
                                    <td className="py-1 text-ink-700">{point.iso}</td>
                                    <td className="num py-1 text-ink-700">{money(point.depositCents)}</td>
                                    <td className="num py-1 text-ink-700">{money(point.spendCents)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </section>
    );
}

function Swatch({ color, label }: { color: string; label: string }) {
    return (
        <li className="flex items-center gap-2 text-sm text-ink-700">
            <span aria-hidden="true" className="size-2.5 rounded-[2px]" style={{ background: color }} />
            {label}
        </li>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-2">
            <dt className="text-xs text-ink-500">{label}</dt>
            <dd className="num text-xs text-ink-900">{value}</dd>
        </div>
    );
}

/** What the picture says, for anyone who cannot see it. */
function summary(series: MonthPoint[]): string {
    const deposits = series.reduce((sum, p) => sum + p.depositCents, 0);
    const spend = series.reduce((sum, p) => sum + p.spendCents, 0);

    return `Over the last ${series.length} months you added ${money(deposits)} and spent ${money(spend)}.`;
}
