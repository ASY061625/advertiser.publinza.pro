import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { ArrowDownIcon, ArrowUpIcon } from './icons';

export interface StatCardProps {
    label: string;
    /** Pre-formatted. The card renders it as tabular digits, it does not format. */
    value: string;
    /** Signed percentage change. Positive reads teal, negative reads danger. */
    delta?: number;
    /** What the delta is measured against, e.g. "vs last month". */
    deltaLabel?: string;
    icon?: ReactNode;
    loading?: boolean;
    className?: string;
}

export function StatCard({ label, value, delta, deltaLabel, icon, loading = false, className }: StatCardProps) {
    const positive = (delta ?? 0) >= 0;

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

            {delta !== undefined && !loading && (
                <div className="mt-3 flex items-center gap-2">
                    <span
                        className={cn(
                            'num inline-flex items-center gap-1 rounded-pill px-2 py-0.5 text-xs font-medium',
                            positive ? 'bg-teal-subtle text-success' : 'bg-danger-bg text-danger',
                        )}
                    >
                        {positive ? <ArrowUpIcon size={12} /> : <ArrowDownIcon size={12} />}
                        {Math.abs(delta).toFixed(1)}%
                    </span>
                    {deltaLabel && <span className="text-xs text-ink-500">{deltaLabel}</span>}
                </div>
            )}
        </div>
    );
}
