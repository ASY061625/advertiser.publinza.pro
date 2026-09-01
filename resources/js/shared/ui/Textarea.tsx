import { forwardRef, useId, type TextareaHTMLAttributes } from 'react';
import { cn } from '@shared/lib/cn';
import { controlBase, controlTone, describedBy, Field } from './Field';

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label: string;
    hint?: string;
    error?: string;
    /** Shows "n / maxLength" under the field. Requires maxLength. */
    showCount?: boolean;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
    { label, hint, error, showCount = false, className, id, rows = 4, maxLength, value, ...props },
    ref,
) {
    const generated = useId();
    const fieldId = id ?? generated;
    const used = typeof value === 'string' ? value.length : 0;

    return (
        <Field id={fieldId} label={label} hint={hint} error={error} required={props.required}>
            <textarea
                ref={ref}
                id={fieldId}
                rows={rows}
                maxLength={maxLength}
                value={value}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(fieldId, hint, error)}
                className={cn(controlBase, controlTone(Boolean(error)), 'resize-y px-3 py-2', className)}
                {...props}
            />
            {showCount && maxLength !== undefined && (
                <p className="num text-right text-xs text-ink-500">
                    {used} / {maxLength}
                </p>
            )}
        </Field>
    );
});
