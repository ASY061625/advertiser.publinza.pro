import { useId, type InputHTMLAttributes } from 'react';
import { cn } from '@shared/lib/cn';
import { controlBase, controlTone, describedBy, Field } from './Field';
import { MinusIcon, PlusIcon } from './icons';

export interface NumberInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value' | 'type'> {
    label: string;
    hint?: string;
    error?: string;
    value: number | '';
    onValueChange: (value: number | '') => void;
    min?: number;
    max?: number;
    step?: number;
    /** Rendered inside the field, e.g. "$" or "%". */
    unit?: string;
    hideLabel?: boolean;
}

/**
 * A number field with steppers. Digits are tabular so a column of these lines
 * up, and the value is clamped to [min, max] on every change rather than on
 * blur, so the steppers can never walk out of range.
 */
export function NumberInput({
    label,
    hint,
    error,
    value,
    onValueChange,
    min,
    max,
    step = 1,
    unit,
    hideLabel = false,
    className,
    disabled,
    id,
    ...props
}: NumberInputProps) {
    const generated = useId();
    const inputId = id ?? generated;

    function clamp(next: number): number {
        if (min !== undefined && next < min) return min;
        if (max !== undefined && next > max) return max;
        return next;
    }

    function nudge(direction: 1 | -1) {
        const current = value === '' ? (min ?? 0) : value;
        onValueChange(clamp(current + direction * step));
    }

    const atMin = value !== '' && min !== undefined && value <= min;
    const atMax = value !== '' && max !== undefined && value >= max;

    const control = (
        <div className="relative flex items-center">
            {unit && (
                <span className="pointer-events-none absolute left-3 text-base text-ink-500" aria-hidden="true">
                    {unit}
                </span>
            )}
            <input
                id={inputId}
                type="number"
                inputMode="decimal"
                value={value}
                min={min}
                max={max}
                step={step}
                disabled={disabled}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(inputId, hint, error)}
                onChange={(event) => {
                    const raw = event.target.value;
                    onValueChange(raw === '' ? '' : clamp(Number(raw)));
                }}
                className={cn(
                    controlBase,
                    controlTone(Boolean(error)),
                    'num h-9 px-3 pr-16',
                    unit && 'pl-7',
                    // The native spinners duplicate the buttons below.
                    '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                    className,
                )}
                {...props}
            />
            <span className="absolute right-1 flex items-center gap-0.5">
                <button
                    type="button"
                    aria-label={`Decrease ${label}`}
                    disabled={disabled ?? atMin}
                    onClick={() => nudge(-1)}
                    className="flex size-7 items-center justify-center rounded-button text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-700 disabled:pointer-events-none disabled:opacity-40"
                >
                    <MinusIcon size={14} />
                </button>
                <button
                    type="button"
                    aria-label={`Increase ${label}`}
                    disabled={disabled ?? atMax}
                    onClick={() => nudge(1)}
                    className="flex size-7 items-center justify-center rounded-button text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-700 disabled:pointer-events-none disabled:opacity-40"
                >
                    <PlusIcon size={14} />
                </button>
            </span>
        </div>
    );

    if (hideLabel) {
        return (
            <div>
                <label htmlFor={inputId} className="sr-only">
                    {label}
                </label>
                {control}
            </div>
        );
    }

    return (
        <Field id={inputId} label={label} hint={hint} error={error}>
            {control}
        </Field>
    );
}
