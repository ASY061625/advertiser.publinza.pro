import { cn } from '@shared/lib/cn';
import type { ChangelogTypeName } from '@shared/types/notifications';

/**
 * New / Improved / Fixed.
 *
 * The three fills the spec names — brand-blue-50, teal-50, surface-sunken — are
 * reached through the status tokens that already hold them rather than through
 * three new colour variables, so a change to the palette does not leave the
 * changelog behind.
 */
const CHIPS: Record<ChangelogTypeName, string> = {
    new: 'bg-status-new-bg text-status-new-fg',
    improved: 'bg-status-posted-bg text-status-posted-fg',
    fixed: 'bg-status-draft-bg text-ink-700',
};

export function ChangelogChip({ type, label }: { type: ChangelogTypeName; label: string }) {
    return (
        <span
            className={cn(
                'rounded-pill px-2.5 py-1 text-xs font-medium',
                CHIPS[type] ?? 'bg-sunken text-ink-500',
            )}
        >
            {label}
        </span>
    );
}
