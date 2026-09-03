import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

export interface EmptyStateProps {
    /** Illustration slot. Keep it quiet — a muted glyph, not a mascot. */
    illustration?: ReactNode;
    /** One line of direction. Never an apology, never two sentences. */
    direction: string;
    /**
     * A supporting line under the direction, for the states that have to say
     * what happens next. Given one, `direction` reads as the heading and takes
     * the heavier weight.
     */
    body?: string;
    /** The way out: one button, or a primary and a secondary. Not a menu. */
    action?: ReactNode;
    className?: string;
}

export function EmptyState({ illustration, direction, body, action, className }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-4 rounded-card bg-sunken px-6 py-14 text-center',
                className,
            )}
        >
            {illustration && <div className="text-ink-300">{illustration}</div>}

            <div className="flex max-w-md flex-col gap-1.5">
                <p className={cn('text-md', body ? 'font-sora font-semibold text-ink-900' : 'text-ink-700')}>
                    {direction}
                </p>
                {body && <p className="text-sm text-ink-500">{body}</p>}
            </div>

            {action}
        </div>
    );
}
