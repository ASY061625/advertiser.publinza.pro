import { useId } from 'react';
import { cn } from '@shared/lib/cn';

export interface RadioOption {
    value: string;
    label: string;
    hint?: string;
    disabled?: boolean;
}

export interface RadioGroupProps {
    legend: string;
    name?: string;
    options: RadioOption[];
    value: string;
    onChange: (value: string) => void;
    error?: string;
    className?: string;
}

/**
 * Radios always come as a group with a legend — a lone radio is a checkbox
 * wearing the wrong shape.
 */
export function RadioGroup({ legend, name, options, value, onChange, error, className }: RadioGroupProps) {
    const generated = useId();
    const groupName = name ?? generated;

    return (
        <fieldset className={cn('flex flex-col gap-2.5', className)}>
            <legend className="mb-1 text-sm font-medium text-ink-700">{legend}</legend>

            {options.map((option) => {
                const optionId = `${groupName}-${option.value}`;

                return (
                    <div key={option.value} className="flex gap-2.5">
                        <span className="relative flex h-5 items-center">
                            <input
                                id={optionId}
                                type="radio"
                                name={groupName}
                                value={option.value}
                                checked={value === option.value}
                                disabled={option.disabled}
                                onChange={() => onChange(option.value)}
                                className={cn(
                                    'peer size-4 shrink-0 appearance-none rounded-pill border bg-card',
                                    'transition-colors duration-fast ease-standard',
                                    'checked:border-[5px] checked:border-brand',
                                    'disabled:cursor-not-allowed disabled:bg-sunken disabled:opacity-60',
                                    error ? 'border-danger' : 'border-strong',
                                )}
                            />
                        </span>

                        <span className="flex flex-col gap-0.5">
                            <label
                                htmlFor={optionId}
                                className={cn(
                                    'text-base',
                                    option.disabled ? 'text-ink-500' : 'cursor-pointer text-ink-700',
                                )}
                            >
                                {option.label}
                            </label>
                            {option.hint && <span className="text-sm text-ink-500">{option.hint}</span>}
                        </span>
                    </div>
                );
            })}

            {error && (
                <p role="alert" className="text-sm text-danger">
                    {error}
                </p>
            )}
        </fieldset>
    );
}
