import { forwardRef, useId, type SelectHTMLAttributes } from 'react';
import { cn } from '@shared/lib/cn';
import { controlBase, controlTone, describedBy, Field } from './Field';
import { ChevronDownIcon } from './icons';

export interface SelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}

export interface SelectProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'children'> {
    label: string;
    hint?: string;
    error?: string;
    options: SelectOption[];
    placeholder?: string;
    hideLabel?: boolean;
}

/**
 * A native select in system chrome. Native is the right call here: it gets
 * keyboard behaviour, mobile pickers and screen-reader support for free.
 * Combobox is the searchable, custom-rendered alternative.
 */
export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
    { label, hint, error, options, placeholder, hideLabel = false, className, id, ...props },
    ref,
) {
    const generated = useId();
    const selectId = id ?? generated;

    const control = (
        <div className="relative">
            <select
                ref={ref}
                id={selectId}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(selectId, hint, error)}
                className={cn(
                    controlBase,
                    controlTone(Boolean(error)),
                    'h-9 appearance-none py-0 pl-3 pr-9',
                    className,
                )}
                {...props}
            >
                {placeholder && (
                    <option value="" disabled>
                        {placeholder}
                    </option>
                )}
                {options.map((option) => (
                    <option key={option.value} value={option.value} disabled={option.disabled}>
                        {option.label}
                    </option>
                ))}
            </select>
            <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-500">
                <ChevronDownIcon size={16} />
            </span>
        </div>
    );

    if (hideLabel) {
        return (
            <div>
                <label htmlFor={selectId} className="sr-only">
                    {label}
                </label>
                {control}
            </div>
        );
    }

    return (
        <Field id={selectId} label={label} hint={hint} error={error} required={props.required}>
            {control}
        </Field>
    );
});
