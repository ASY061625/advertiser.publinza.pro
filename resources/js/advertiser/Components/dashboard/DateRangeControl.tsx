import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { CalendarIcon, DateRangePicker, useDismiss, type DateRange } from '@shared/ui';
import type { RangeKey } from '@shared/types/dashboard';

export interface RangeSelection {
    key: RangeKey;
    from?: string;
    to?: string;
}

interface Props {
    value: RangeSelection;
    /** The server's label for the active range — the control never invents one. */
    activeLabel: string;
    onChange: (value: RangeSelection) => void;
    disabled?: boolean;
}

const PRESETS: { key: Exclude<RangeKey, 'custom'>; label: string }[] = [
    { key: 'last_7', label: 'Last 7 days' },
    { key: 'last_30', label: 'Last 30 days' },
    { key: 'quarter', label: 'Quarter' },
    { key: 'year', label: 'Year' },
];

/**
 * The one control every panel on the dashboard obeys.
 *
 * Presets are segmented buttons rather than a select, because four options that
 * get changed constantly should cost one click, not two. Custom opens a popover
 * so the segmented row keeps its width when the calendar is closed.
 */
export function DateRangeControl({ value, activeLabel, onChange, disabled = false }: Props) {
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));
    const [draft, setDraft] = useState<DateRange>({
        start: value.from ?? null,
        end: value.to ?? null,
    });

    function apply(next: DateRange) {
        setDraft(next);

        if (next.start && next.end) {
            onChange({ key: 'custom', from: next.start, to: next.end });
            setOpen(false);
        }
    }

    return (
        <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Date range">
            <div className="inline-flex rounded-card border border-subtle bg-card p-0.5 shadow-card">
                {PRESETS.map((preset) => {
                    const active = value.key === preset.key;

                    return (
                        <button
                            key={preset.key}
                            type="button"
                            disabled={disabled}
                            aria-pressed={active}
                            onClick={() => onChange({ key: preset.key })}
                            className={cn(
                                'whitespace-nowrap rounded-button px-3 py-1.5 text-sm font-medium',
                                'transition-colors duration-fast ease-standard',
                                'disabled:pointer-events-none disabled:opacity-50',
                                active
                                    ? 'bg-brand-subtle text-brand'
                                    : 'text-ink-500 hover:bg-sunken hover:text-ink-700',
                            )}
                        >
                            {preset.label}
                        </button>
                    );
                })}
            </div>

            <div ref={ref} className="relative">
                <button
                    type="button"
                    disabled={disabled}
                    aria-expanded={open}
                    aria-haspopup="dialog"
                    onClick={() => setOpen((v) => !v)}
                    className={cn(
                        'inline-flex items-center gap-2 whitespace-nowrap rounded-card border px-3 py-2',
                        'text-sm font-medium shadow-card transition-colors duration-fast ease-standard',
                        'disabled:pointer-events-none disabled:opacity-50',
                        value.key === 'custom'
                            ? 'border-brand bg-brand-subtle text-brand'
                            : 'border-subtle bg-card text-ink-700 hover:bg-sunken',
                    )}
                >
                    <CalendarIcon size={14} />
                    {value.key === 'custom' ? activeLabel : 'Custom'}
                </button>

                {open && (
                    <div
                        role="dialog"
                        aria-label="Choose a custom range"
                        className={cn(
                            'absolute right-0 z-40 mt-1 w-80 animate-scale-in rounded-card',
                            'border border-subtle bg-card p-4 shadow-card',
                        )}
                    >
                        <DateRangePicker
                            label="Custom range"
                            value={draft}
                            onChange={apply}
                            max={new Date().toISOString().slice(0, 10)}
                            hint="Pick a start and an end date."
                        />
                    </div>
                )}
            </div>
        </div>
    );
}
