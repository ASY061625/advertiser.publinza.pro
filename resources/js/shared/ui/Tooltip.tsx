import { useId, useState, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

export interface TooltipProps {
    /** Short. A tooltip is a label, not documentation. */
    content: string;
    side?: 'top' | 'bottom';
    children: ReactNode;
    className?: string;
}

/**
 * Opens on hover and on focus, so it is reachable by keyboard. The trigger is
 * wrapped rather than cloned, which keeps the tooltip from fighting a child's
 * own event handlers.
 */
export function Tooltip({ content, side = 'top', children, className }: TooltipProps) {
    const id = useId();
    const [open, setOpen] = useState(false);

    return (
        <span
            className={cn('relative inline-flex', className)}
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
            onFocus={() => setOpen(true)}
            onBlur={() => setOpen(false)}
        >
            <span aria-describedby={open ? id : undefined} className="contents">
                {children}
            </span>

            {open && (
                <span
                    role="tooltip"
                    id={id}
                    className={cn(
                        'pointer-events-none absolute left-1/2 z-50 w-max max-w-56 -translate-x-1/2',
                        'animate-fade-in rounded-card bg-ink-900 px-2.5 py-1.5 text-xs text-white shadow-card',
                        side === 'top' ? 'bottom-full mb-1.5' : 'top-full mt-1.5',
                    )}
                >
                    {content}
                </span>
            )}
        </span>
    );
}
