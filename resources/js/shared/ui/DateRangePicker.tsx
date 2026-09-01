import { useId, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { controlBase, controlTone, describedBy, Field } from './Field';
import { CalendarIcon } from './icons';
import { Calendar, formatIso, type IsoDate } from './Calendar';
import { useDismiss } from './usePopover';

export interface DateRange {
    start: IsoDate | null;
    end: IsoDate | null;
}

export interface DateRangePickerProps {
    label: string;
    value: DateRange;
    onChange: (value: DateRange) => void;
    hint?: string;
    error?: string;
    min?: IsoDate;
    max?: IsoDate;
    disabled?: boolean;
    className?: string;
}

/**
 * Two-click range selection: the first click sets the start and clears the end,
 * the second sets the end. Clicking before the current start restarts the range
 * rather than producing an inverted one.
 */
export function DateRangePicker({
    label,
    value,
    onChange,
    hint,
    error,
    min,
    max,
    disabled,
    className,
}: DateRangePickerProps) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    function onSelect(date: IsoDate) {
        const { start, end } = value;

        if (start === null || end !== null || date < start) {
            onChange({ start: date, end: null });
            return;
        }

        onChange({ start, end: date });
        setOpen(false);
    }

    const summary =
        value.start && value.end
            ? `${formatIso(value.start)} – ${formatIso(value.end)}`
            : value.start
              ? `${formatIso(value.start)} – …`
              : 'Pick a date range';

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
                    <span className={cn('num flex-1 truncate', value.start ? 'text-ink-900' : 'text-ink-500')}>
                        {summary}
                    </span>
                </button>

                {open && (
                    <div
                        role="dialog"
                        aria-label={label}
                        className="absolute z-40 mt-1 animate-fade-in rounded-card border border-subtle bg-card shadow-card"
                    >
                        <Calendar
                            selected={null}
                            rangeStart={value.start}
                            rangeEnd={value.end}
                            min={min}
                            max={max}
                            onSelect={onSelect}
                        />
                        <div className="flex justify-end border-t border-subtle px-3 py-2">
                            <button
                                type="button"
                                onClick={() => onChange({ start: null, end: null })}
                                className="rounded-button px-2 py-1 text-sm text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-700"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </Field>
    );
}
