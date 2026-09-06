const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });
const plain = new Intl.NumberFormat('en-US');

/** 12_400 -> "12.4K". Used in metric cells where the bar carries the magnitude. */
export function compactNumber(value: number): string {
    return compact.format(value);
}

export function number(value: number): string {
    return plain.format(value);
}

/** Money is stored in minor units everywhere in this codebase. */
export function money(minorUnits: number, currency = 'USD'): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(minorUnits / 100);
}

export function date(value: string): string {
    return new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(new Date(value));
}

const relativeFormatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['week', 604_800],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/**
 * "3 hours ago", "in 2 days", "just now".
 *
 * Works in both directions, so one function covers a message that was sent and
 * a deadline that has not arrived. Anything under a minute is "just now" rather
 * than "in 4 seconds", which is precision nobody asked for.
 */
export function relativeTime(value: string | null): string {
    if (value === null) return '';

    const seconds = (new Date(value).getTime() - Date.now()) / 1000;

    for (const [unit, size] of RELATIVE_UNITS) {
        if (Math.abs(seconds) >= size) return relativeFormatter.format(Math.round(seconds / size), unit);
    }

    return 'just now';
}

/** The clock time on a message bubble. Date lives on the day separator. */
export function time(value: string): string {
    return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' }).format(new Date(value));
}

/**
 * A day heading: "Today", "Yesterday", then the date.
 *
 * Named days for the two that people actually reason about, and a real date
 * beyond that — "5 days ago" as a separator makes somebody count backwards to
 * work out which Tuesday a promise was made on.
 */
export function dayLabel(value: string): string {
    const then = new Date(value);
    const today = new Date();
    const midnight = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const days = Math.round((midnight(today) - midnight(then)) / 86_400_000);

    if (days === 0) return 'Today';
    if (days === 1) return 'Yesterday';

    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: then.getFullYear() === today.getFullYear() ? undefined : 'numeric',
    }).format(then);
}

/** "1.2 MB". Attachment chips, where the exact byte count is noise. */
export function fileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value < 10 ? value.toFixed(1) : Math.round(value)} ${units[unit]}`;
}
