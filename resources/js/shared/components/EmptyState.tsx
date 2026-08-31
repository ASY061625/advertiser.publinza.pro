import type { ReactNode } from 'react';

interface EmptyStateProps {
    /** One instruction. Never an apology. */
    instruction: string;
    /** Exactly one button. */
    action?: ReactNode;
}

export function EmptyState({ instruction, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center gap-4 rounded-card bg-surface-sunken px-6 py-14 text-center">
            <p className="max-w-sm text-md text-ink-700">{instruction}</p>
            {action}
        </div>
    );
}
