import { cn } from '@shared/lib/cn';

export interface QuantBarProps {
    value: number;
    /** [min, max] across the whole catalog — not the visible page. */
    range: [number, number];
    /** Lower is better (spam score): the bar fills from the good end. */
    inverted?: boolean;
    /** Formats the digits. Defaults to a compact number. */
    format?: (value: number) => string;
    className?: string;
}

const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

/**
 * The signature component.
 *
 * A number in tabular digits with a 3px proportional bar beneath it, scaled
 * against a min/max range passed as props. Brand blue by default; teal once the
 * value lands in the top quartile of the range, so a buyer scanning 200 rows
 * reads shape — and standouts — before digits.
 *
 * Two rules, both load-bearing:
 *
 * 1. The range is the whole catalog's, not the page's. Scaling per page would
 *    rescale every bar on each pagination click and make two pages
 *    incomparable.
 * 2. THIS IS USED ONLY IN THE CATALOG. Not in dashboards, not in StatCard, not
 *    in drawers, not in the admin tables. Its whole value is that it means one
 *    thing in one place.
 */
export function QuantBar({
    value,
    range,
    inverted = false,
    format = (v) => compact.format(v),
    className,
}: QuantBarProps) {
    const [min, max] = range;
    const span = max - min;
    const ratio = span <= 0 ? 0 : (value - min) / span;

    // `fill` is how much bar is drawn; `quality` is how good the value is.
    // They diverge for inverted metrics, where a short bar is the good outcome.
    const clamped = Math.min(1, Math.max(0, ratio));
    const fill = inverted ? 1 - clamped : clamped;
    const topQuartile = fill >= 0.75;

    return (
        <div className={cn('flex flex-col items-end gap-1.5', className)}>
            <span className="num text-base text-ink-900">{format(value)}</span>
            <span className="block h-[3px] w-full max-w-[92px] overflow-hidden rounded-pill bg-sunken">
                <span
                    className={cn('block h-full rounded-pill', topQuartile ? 'bg-teal' : 'bg-brand')}
                    style={{ width: `${(fill * 100).toFixed(1)}%` }}
                />
            </span>
        </div>
    );
}
