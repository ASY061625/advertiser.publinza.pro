import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { ArrowDownIcon, ArrowUpIcon } from './icons';

export interface StatCardProps {
    label: string;
    /** Pre-formatted. The card renders it as tabular digits, it does not format. */
    value: string;
    /**
     * Signed percentage change. Positive reads teal, negative reads danger,
     * exactly zero reads neutral ink — "unchanged" is not good news.
     */
    delta?: number;
    /**
     * Shown in place of a percentage when there is nothing to divide by, i.e.
     * the previous period was zero. "New" is honest; "up 100%" is not.
     */
    deltaPlaceholder?: string;
    /** What the delta is measured against, e.g. "vs last month". */
    deltaLabel?: string;
    icon?: ReactNode;
    loading?: boolean;
    className?: string;
}

export function StatCard({
    label,
    value,
    delta,
    deltaPlaceholder,
    deltaLabel,
    icon,
    loading = false,
    className,
}: StatCardProps) {
    const tone = delta === undefined || delta === 0 ? 'flat' : delta > 0 ? 'up' : 'down';
    const showChip = !loading && (delta !== undefined || deltaPlaceholder !== undefined);

    return (
        <div className={cn('rounded-card border border-subtle bg-card p-5 shadow-card', className)}>
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm text-ink-500">{label}</p>
                {icon && (
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-card bg-sunken text-ink-500">
                        {icon}
                    </span>
                )}
            </div>

            {loading ? (
                <div className="mt-3 h-[34px] w-28 animate-pulse rounded-card bg-sunken" />
            ) : (
                <p className="num mt-2 font-sora text-2xl font-semibold text-ink-900">{value}</p>
            )}

            {showChip && (
                <div className="mt-3 flex items-center gap-2">
                    <span
                        className={cn(
                            'num inline-flex items-center gap-1 rounded-pill px-2 py-0.5 text-xs font-medium',
                            tone === 'up' && 'bg-teal-subtle text-success',
                            tone === 'down' && 'bg-danger-bg text-danger',
                            tone === 'flat' && 'bg-sunken text-ink-500',
                        )}
                    >
                        {tone === 'up' && <ArrowUpIcon size={12} />}
                        {tone === 'down' && <ArrowDownIcon size={12} />}
                        {delta === undefined
                            ? deltaPlaceholder
                            : delta === 0
                              ? 'No change'
                              : `${Math.abs(delta).toFixed(1)}%`}
                    </span>
                    {deltaLabel && <span className="text-xs text-ink-500">{deltaLabel}</span>}
                </div>
            )}
        </div>
    );
}
