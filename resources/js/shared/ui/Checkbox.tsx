import { forwardRef, useEffect, useId, useRef, type InputHTMLAttributes, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { CheckIcon, MinusIcon } from './icons';

export interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
    /**
     * Usually a string. Accepts nodes so a label can carry a glyph beside the
     * words — a flag before a country, a swatch before a colour — without the
     * caller having to rebuild the label-and-input association by hand.
     */
    label?: ReactNode;
    hint?: string;
    error?: string;
    /** Header checkbox state when only some rows are selected. */
    indeterminate?: boolean;
    /**
     * Hides the label visually for checkboxes in a table header or row, which
     * need a name for screen readers but have no room for one on screen.
     */
    hideLabel?: boolean;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(function Checkbox(
    { label, hint, error, indeterminate = false, hideLabel = false, className, id, disabled, ...props },
    ref,
) {
    const generated = useId();
    const boxId = id ?? generated;
    const inner = useRef<HTMLInputElement | null>(null);

    // `indeterminate` is a DOM property with no HTML attribute, so it has to be
    // assigned imperatively after every render.
    useEffect(() => {
        if (inner.current) inner.current.indeterminate = indeterminate;
    }, [indeterminate]);

    return (
        <div className={cn('flex gap-2.5', className)}>
            <span className="relative flex h-5 items-center">
                <input
                    ref={(node) => {
                        inner.current = node;
                        if (typeof ref === 'function') ref(node);
                        else if (ref) ref.current = node;
                    }}
                    id={boxId}
                    type="checkbox"
                    disabled={disabled}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? `${boxId}-error` : hint ? `${boxId}-hint` : undefined}
                    className={cn(
                        'peer size-4 shrink-0 appearance-none rounded-[4px] border bg-card',
                        'transition-colors duration-fast ease-standard',
                        'checked:border-brand checked:bg-brand indeterminate:border-brand indeterminate:bg-brand',
                        'disabled:cursor-not-allowed disabled:bg-sunken disabled:opacity-60',
                        // Without this the white tick sits on the disabled grey
                        // fill and a disabled+checked box reads as unchecked.
                        'disabled:checked:border-ink-500 disabled:checked:bg-ink-500',
                        'disabled:indeterminate:border-ink-500 disabled:indeterminate:bg-ink-500',
                        error ? 'border-danger' : 'border-strong',
                    )}
                    {...props}
                />
                <span className="pointer-events-none absolute left-0 top-0.5 hidden text-white peer-checked:block peer-indeterminate:hidden">
                    <CheckIcon size={16} />
                </span>
                <span className="pointer-events-none absolute left-0 top-0.5 hidden text-white peer-indeterminate:block">
                    <MinusIcon size={16} />
                </span>
            </span>

            {label && (
                <span className="flex flex-col gap-0.5">
                    <label
                        htmlFor={boxId}
                        className={cn(
                            'text-base',
                            hideLabel && 'sr-only',
                            disabled ? 'text-ink-500' : 'cursor-pointer text-ink-700',
                        )}
                    >
                        {label}
                    </label>
                    {error ? (
                        <span id={`${boxId}-error`} role="alert" className="text-sm text-danger">
                            {error}
                        </span>
                    ) : (
                        hint && (
                            <span id={`${boxId}-hint`} className="text-sm text-ink-500">
                                {hint}
                            </span>
                        )
                    )}
                </span>
            )}
        </div>
    );
});
