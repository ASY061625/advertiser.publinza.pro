import { cn } from '@shared/lib/cn';

export interface ProgressBarProps {
    /** 0–100. Omit for an indeterminate bar. */
    value?: number;
    label?: string;
    /** Shows the percentage to the right of the label. */
    showValue?: boolean;
    tone?: 'brand' | 'success' | 'warning' | 'danger';
    className?: string;
}

const TONES = {
    brand: 'bg-brand',
    success: 'bg-success',
    warning: 'bg-gold',
    danger: 'bg-danger',
} as const;

export function ProgressBar({ value, label, showValue = false, tone = 'brand', className }: ProgressBarProps) {
    const indeterminate = value === undefined;
    const pct = Math.min(100, Math.max(0, value ?? 0));

    return (
        <div className={cn('w-full', className)}>
            {(label ?? showValue) && (
                <div className="mb-1.5 flex items-center justify-between gap-2">
                    {label && <span className="text-sm text-ink-700">{label}</span>}
                    {showValue && !indeterminate && <span className="num text-sm text-ink-500">{pct.toFixed(0)}%</span>}
                </div>
            )}
            <div
                role="progressbar"
                aria-valuenow={indeterminate ? undefined : pct}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={label}
                className="h-1.5 w-full overflow-hidden rounded-pill bg-sunken"
            >
                <div
                    className={cn(
                        'h-full rounded-pill transition-[width] duration-toast ease-standard',
                        TONES[tone],
                        indeterminate && 'w-1/3 animate-pulse',
                    )}
                    style={indeterminate ? undefined : { width: `${pct}%` }}
                />
            </div>
        </div>
    );
}
