import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

export interface EmptyStateProps {
    /** Illustration slot. Keep it quiet — a muted glyph, not a mascot. */
    illustration?: ReactNode;
    /** One line of direction. Never an apology, never two sentences. */
    direction: string;
    /** Exactly one button. */
    action?: ReactNode;
    className?: string;
}

export function EmptyState({ illustration, direction, action, className }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-4 rounded-card bg-sunken px-6 py-14 text-center',
                className,
            )}
        >
            {illustration && <div className="text-ink-300">{illustration}</div>}
            <p className="max-w-sm text-md text-ink-700">{direction}</p>
            {action}
        </div>
    );
}
