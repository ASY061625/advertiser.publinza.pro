import { cn } from '@shared/lib/cn';
import { compactNumber } from '@shared/lib/format';

interface QuantBarProps {
    value: number;
    /** [min, max] across the whole catalog, not just the visible page. */
    range: [number, number];
    /** Lower values are better (spam score), so the bar fills from the good end. */
    inverted?: boolean;
    /** Pass a formatter when the raw digits need units (e.g. "3%"). */
    format?: (value: number) => string;
    className?: string;
}

/**
 * The signature element: a number plus a thin proportional bar scaled against
 * the catalog's own range, so a buyer scanning 200 rows reads shape before
 * digits. Nothing else in the product uses this treatment — do not reach for it
 * in dashboards, drawers or admin tables.
 */
export function QuantBar({ value, range, inverted = false, format = compactNumber, className }: QuantBarProps) {
    const [min, max] = range;
    const span = max - min;
    const ratio = span <= 0 ? 0 : (value - min) / span;
    const fill = Math.min(1, Math.max(0, inverted ? 1 - ratio : ratio));

    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <span className="tabular text-base text-ink-900">{format(value)}</span>
            <span
                className="block h-1 w-full max-w-[92px] overflow-hidden rounded-pill bg-surface-sunken"
                role="presentation"
            >
                <span className="block h-full rounded-pill bg-brand" style={{ width: `${(fill * 100).toFixed(1)}%` }} />
            </span>
        </div>
    );
}
