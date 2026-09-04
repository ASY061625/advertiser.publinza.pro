import { cn } from '@shared/lib/cn';
import type { CompetitorDelta } from '@shared/types/competitors';

/**
 * How one figure stands against your site.
 *
 * The number is about the competitor — "+34%" means they have a third more of
 * whatever the column measures — and the colour is about the reader: red when
 * that puts you behind, teal when it puts you ahead.
 *
 * The sign carries the same fact as the colour, so the chip is still readable
 * with no colour vision at all, printed in black and white, or in forced-colours
 * mode. Colour alone would make seven columns of this page unreadable to
 * roughly one man in twelve.
 */
export function DeltaChip({ delta }: { delta: CompetitorDelta | undefined }) {
    if (!delta || delta.percent === null) {
        // Nothing to compare against: a measure this provider does not sell, or
        // a baseline of zero, which has no percentage difference from anything.
        return null;
    }

    const { percent, leading } = delta;
    const rounded = Math.abs(percent) >= 1000 ? Math.round(percent) : percent;

    return (
        <span
            className={cn(
                'num ml-1.5 inline-flex shrink-0 items-center rounded-pill px-1.5 py-0.5 text-xs font-medium',
                leading === null && 'bg-sunken text-ink-500',
                leading === true && 'bg-teal-subtle text-success',
                leading === false && 'bg-danger-bg text-danger',
            )}
            title={
                leading === null
                    ? 'The same as your site'
                    : leading
                      ? 'Your site is ahead on this measure'
                      : 'Your site is behind on this measure'
            }
        >
            {percent > 0 ? '+' : ''}
            {rounded}%
        </span>
    );
}
