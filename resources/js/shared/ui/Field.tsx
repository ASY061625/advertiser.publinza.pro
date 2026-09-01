import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

export interface FieldProps {
    id: string;
    label: string;
    /** Quiet guidance under the label. Hidden while an error is showing. */
    hint?: string;
    /** What happened and what to do next. */
    error?: string;
    required?: boolean;
    children: ReactNode;
    className?: string;
}

/**
 * The label/hint/error frame every form control sits in, so the three states
 * look and announce the same way everywhere.
 */
export function Field({ id, label, hint, error, required, children, className }: FieldProps) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <label htmlFor={id} className="text-sm font-medium text-ink-700">
                {label}
                {required && (
                    <span className="ml-1 text-danger" aria-hidden="true">
                        *
                    </span>
                )}
            </label>

            {children}

            {error ? (
                <p id={`${id}-error`} role="alert" className="text-sm text-danger">
                    {error}
                </p>
            ) : (
                hint && (
                    <p id={`${id}-hint`} className="text-sm text-ink-500">
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}

/** Shared control chrome: 8px radius, subtle border, brand focus ring. */
export const controlBase = cn(
    'w-full rounded-input border bg-card text-base text-ink-900',
    'transition-colors duration-fast ease-standard',
    'placeholder:text-ink-500',
    'disabled:cursor-not-allowed disabled:bg-sunken disabled:text-ink-500',
);

export function controlTone(error?: boolean): string {
    return error ? 'border-danger' : 'border-subtle hover:border-strong';
}

/** Wires a control to its hint or error for assistive technology. */
export function describedBy(id: string, hint?: string, error?: string): string | undefined {
    if (error) return `${id}-error`;
    if (hint) return `${id}-hint`;
    return undefined;
}
