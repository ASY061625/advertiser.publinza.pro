import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

interface HeaderButtonProps {
    label: string;
    icon: ReactNode;
    /** Rendered as a badge; 0 hides it. */
    count?: number;
    /** A plain dot instead of a number, for What's new. */
    dot?: boolean;
    expanded?: boolean;
    onClick?: () => void;
}

/**
 * The shared trigger for every header item, so all six are the same size, take
 * focus the same way and carry their count in the same place.
 */
export function HeaderButton({ label, icon, count = 0, dot = false, expanded, onClick }: HeaderButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={count > 0 ? `${label}, ${count} unread` : label}
            aria-haspopup={onClick ? 'menu' : undefined}
            aria-expanded={expanded}
            className={cn(
                'relative flex size-9 items-center justify-center rounded-button text-ink-500',
                'transition-colors duration-fast ease-standard hover:bg-sunken hover:text-ink-700',
                expanded && 'bg-sunken text-ink-700',
            )}
        >
            {icon}

            {dot && count > 0 && (
                <span
                    aria-hidden="true"
                    className="absolute right-1.5 top-1.5 size-2 rounded-pill bg-brand ring-2 ring-card"
                />
            )}

            {!dot && count > 0 && (
                <span
                    aria-hidden="true"
                    className="num absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-pill bg-brand px-1 text-[10px] font-medium text-white ring-2 ring-card"
                >
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </button>
    );
}
