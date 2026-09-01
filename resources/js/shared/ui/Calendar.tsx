import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { ChevronLeftIcon, ChevronRightIcon } from './icons';
import { IconButton } from './IconButton';

/**
 * Dates move through this system as `YYYY-MM-DD` strings, never as `Date`
 * objects. A calendar day is a civil date with no time and no zone; parsing one
 * into a `Date` invites an off-by-one every time the user is west of UTC.
 */
export type IsoDate = string;

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];
const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

export function toIso(year: number, month: number, day: number): IsoDate {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function parseIso(value: IsoDate): { year: number; month: number; day: number } | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match?.[1] || !match[2] || !match[3]) return null;

    return { year: Number(match[1]), month: Number(match[2]) - 1, day: Number(match[3]) };
}

export function formatIso(value: IsoDate): string {
    const parts = parseIso(value);
    if (!parts) return value;

    return `${MONTHS[parts.month]?.slice(0, 3) ?? ''} ${parts.day}, ${parts.year}`;
}

function daysInMonth(year: number, month: number): number {
    return new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
}

/** 0 = Monday, to match the Mo–Su header. */
function firstWeekday(year: number, month: number): number {
    return (new Date(Date.UTC(year, month, 1)).getUTCDay() + 6) % 7;
}

export interface CalendarProps {
    /** A single date, or the two ends of a range. */
    selected: IsoDate | null;
    rangeStart?: IsoDate | null;
    rangeEnd?: IsoDate | null;
    onSelect: (date: IsoDate) => void;
    min?: IsoDate;
    max?: IsoDate;
}

export function Calendar({ selected, rangeStart, rangeEnd, onSelect, min, max }: CalendarProps) {
    const anchor = parseIso(selected ?? rangeStart ?? '') ?? {
        year: new Date().getFullYear(),
        month: new Date().getMonth(),
        day: 1,
    };

    const [view, setView] = useState({ year: anchor.year, month: anchor.month });

    function shift(by: number) {
        setView((current) => {
            const next = current.month + by;
            return { year: current.year + Math.floor(next / 12), month: ((next % 12) + 12) % 12 };
        });
    }

    const total = daysInMonth(view.year, view.month);
    const lead = firstWeekday(view.year, view.month);

    return (
        <div className="w-[268px] p-3">
            <div className="mb-2 flex items-center justify-between">
                <IconButton
                    size="sm"
                    label="Previous month"
                    icon={<ChevronLeftIcon size={16} />}
                    onClick={() => shift(-1)}
                />
                <span aria-live="polite" className="font-sora text-base font-medium text-ink-900">
                    {MONTHS[view.month]} {view.year}
                </span>
                <IconButton
                    size="sm"
                    label="Next month"
                    icon={<ChevronRightIcon size={16} />}
                    onClick={() => shift(1)}
                />
            </div>

            <div className="grid grid-cols-7 gap-0.5" role="grid">
                {WEEKDAYS.map((day) => (
                    <div key={day} className="pb-1 text-center text-xs text-ink-500" role="columnheader">
                        {day}
                    </div>
                ))}

                {Array.from({ length: lead }, (_, i) => (
                    <div key={`lead-${i}`} />
                ))}

                {Array.from({ length: total }, (_, i) => {
                    const day = i + 1;
                    const iso = toIso(view.year, view.month, day);

                    const disabled = (min !== undefined && iso < min) || (max !== undefined && iso > max);
                    const isSelected = iso === selected || iso === rangeStart || iso === rangeEnd;
                    const inRange = rangeStart != null && rangeEnd != null && iso > rangeStart && iso < rangeEnd;

                    return (
                        <button
                            key={iso}
                            type="button"
                            role="gridcell"
                            disabled={disabled}
                            aria-selected={isSelected}
                            onClick={() => onSelect(iso)}
                            className={cn(
                                'num flex size-8 items-center justify-center rounded-button text-sm',
                                'transition-colors duration-fast ease-standard',
                                'disabled:pointer-events-none disabled:text-ink-300',
                                isSelected
                                    ? 'bg-brand font-medium text-white'
                                    : inRange
                                      ? 'bg-brand-subtle text-brand'
                                      : 'text-ink-700 hover:bg-sunken',
                            )}
                        >
                            {day}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
