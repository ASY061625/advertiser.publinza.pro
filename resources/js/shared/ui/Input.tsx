import { forwardRef, useId, type InputHTMLAttributes, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { controlBase, controlTone, describedBy, Field } from './Field';

export interface InputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> {
    label: string;
    hint?: string;
    error?: string;
    leadingIcon?: ReactNode;
    trailingSlot?: ReactNode;
    /** Renders the label for screen readers only — for toolbar search fields. */
    hideLabel?: boolean;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { label, hint, error, leadingIcon, trailingSlot, hideLabel = false, className, id, ...props },
    ref,
) {
    const generated = useId();
    const inputId = id ?? generated;

    const control = (
        <div className="relative">
            {leadingIcon && (
                <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-500">
                    {leadingIcon}
                </span>
            )}
            <input
                ref={ref}
                id={inputId}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(inputId, hint, error)}
                className={cn(
                    controlBase,
                    controlTone(Boolean(error)),
                    'h-9 px-3',
                    leadingIcon && 'pl-9',
                    trailingSlot && 'pr-9',
                    className,
                )}
                {...props}
            />
            {trailingSlot && (
                <span className="absolute inset-y-0 right-2 flex items-center text-ink-500">{trailingSlot}</span>
            )}
        </div>
    );

    // Always a Field, hidden label or not. The earlier version returned a
    // bare control for hideLabel and silently dropped `error` and `hint` with
    // it — so a validation message on a toolbar or table input was invisible,
    // and aria-describedby pointed at an element that did not exist.
    return (
        <Field id={inputId} label={label} hint={hint} error={error} required={props.required} hideLabel={hideLabel}>
            {control}
        </Field>
    );
});
