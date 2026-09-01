import { useId, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { controlBase, controlTone, describedBy, Field } from './Field';
import { CalendarIcon, XIcon } from './icons';
import { Calendar, formatIso, type IsoDate } from './Calendar';
import { useDismiss } from './usePopover';

export interface DatePickerProps {
    label: string;
    value: IsoDate | null;
    onChange: (value: IsoDate | null) => void;
    hint?: string;
    error?: string;
    min?: IsoDate;
    max?: IsoDate;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
}

export function DatePicker({
    label,
    value,
    onChange,
    hint,
    error,
    min,
    max,
    placeholder = 'Pick a date',
    disabled,
    className,
}: DatePickerProps) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    return (
        <Field id={id} label={label} hint={hint} error={error} className={className}>
            <div ref={ref} className="relative">
                <button
                    id={id}
                    type="button"
                    disabled={disabled}
                    aria-haspopup="dialog"
                    aria-expanded={open}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy(id, hint, error)}
                    onClick={() => setOpen((v) => !v)}
                    className={cn(
                        controlBase,
                        controlTone(Boolean(error)),
                        'flex h-9 items-center gap-2 px-3 text-left',
                    )}
                >
                    <CalendarIcon size={16} className="shrink-0 text-ink-500" />
                    <span className={cn('num flex-1 truncate', value ? 'text-ink-900' : 'text-ink-500')}>
                        {value ? formatIso(value) : placeholder}
                    </span>
                    {value && !disabled && (
                        <span
                            role="button"
                            tabIndex={-1}
                            aria-label="Clear date"
                            onClick={(event) => {
                                event.stopPropagation();
                                onChange(null);
                            }}
                            className="rounded-pill p-0.5 text-ink-500 transition-colors duration-fast hover:bg-sunken"
                        >
                            <XIcon size={14} />
                        </span>
                    )}
                </button>

                {open && (
                    <div
                        role="dialog"
                        aria-label={label}
                        className="absolute z-40 mt-1 animate-fade-in rounded-card border border-subtle bg-card shadow-card"
                    >
                        <Calendar
                            selected={value}
                            min={min}
                            max={max}
                            onSelect={(date) => {
                                onChange(date);
                                setOpen(false);
                            }}
                        />
                    </div>
                )}
            </div>
        </Field>
    );
}
