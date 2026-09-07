/**
 * Every number and date the app renders goes through here.
 *
 * The formats are *runtime configurable* rather than baked in, because the
 * profile lets somebody choose them and the spec for that says they have to
 * drive rendering across the app — not just on the screen that sets them.
 * Eighty-odd modules import these functions; making each one take a locale
 * argument would have been eighty-odd call sites to change and one to forget.
 *
 * So configuration is module state, set once from the shared Inertia props
 * before the app mounts (see main.tsx) and again whenever the preference
 * changes. `Intl` formatters are rebuilt on change and cached in between —
 * constructing one is expensive enough to matter in a table of a hundred rows.
 */

/** The named date patterns, matching DisplayFormats on the server. */
export type DateFormatName = 'medium' | 'euro' | 'iso' | 'slash_dmy' | 'slash_mdy';

/** The named number patterns, matching DisplayFormats on the server. */
export type NumberFormatName = 'plain' | 'space' | 'euro';

export interface FormatConfig {
    date: DateFormatName;
    number: NumberFormatName;
    /** IANA zone. Timestamps are stored UTC and rendered where the reader is. */
    timeZone: string;
}

const DEFAULTS: FormatConfig = { date: 'medium', number: 'plain', timeZone: 'UTC' };

let config: FormatConfig = { ...DEFAULTS };

/**
 * The locale whose conventions produce each named pattern.
 *
 * Chosen for what `Intl` does with them rather than for the country: `de-DE`
 * is here because it groups with dots and decimals with commas, not because
 * anything about this is German.
 */
const NUMBER_LOCALES: Record<NumberFormatName, string> = {
    plain: 'en-US',
    space: 'fr-FR',
    euro: 'de-DE',
};

/*
 * Explicit components, never `dateStyle`.
 *
 * `dateStyle` cannot be combined with `hour` and `minute` — Intl throws
 * "Invalid option" rather than ignoring one — and these options are spread into
 * a date-and-time formatter below. Spelling the parts out is what lets one
 * definition serve both. `medium` here is exactly what `dateStyle: 'medium'`
 * renders in en-US.
 */
const DATE_OPTIONS: Record<DateFormatName, Intl.DateTimeFormatOptions> = {
    medium: { month: 'short', day: 'numeric', year: 'numeric' },
    euro: { day: 'numeric', month: 'long', year: 'numeric' },
    iso: { year: 'numeric', month: '2-digit', day: '2-digit' },
    slash_dmy: { day: '2-digit', month: '2-digit', year: 'numeric' },
    slash_mdy: { month: '2-digit', day: '2-digit', year: 'numeric' },
};

const DATE_LOCALES: Record<DateFormatName, string> = {
    medium: 'en-US',
    euro: 'en-GB',
    // en-CA renders ISO order natively, which beats reassembling the parts.
    iso: 'en-CA',
    slash_dmy: 'en-GB',
    slash_mdy: 'en-US',
};

/** Rebuilt on every config change, reused between them. */
let cache = build(config);

function build(current: FormatConfig) {
    const numberLocale = NUMBER_LOCALES[current.number] ?? NUMBER_LOCALES.plain;
    const dateLocale = DATE_LOCALES[current.date] ?? DATE_LOCALES.medium;
    const dateOptions = DATE_OPTIONS[current.date] ?? DATE_OPTIONS.medium;

    return {
        compact: new Intl.NumberFormat(numberLocale, { notation: 'compact', maximumFractionDigits: 1 }),
        plain: new Intl.NumberFormat(numberLocale),
        date: new Intl.DateTimeFormat(dateLocale, { ...dateOptions, timeZone: current.timeZone }),
        dateTime: new Intl.DateTimeFormat(dateLocale, {
            ...dateOptions,
            hour: 'numeric',
            minute: '2-digit',
            timeZone: current.timeZone,
        }),
        time: new Intl.DateTimeFormat(dateLocale, {
            hour: 'numeric',
            minute: '2-digit',
            timeZone: current.timeZone,
        }),
        numberLocale,
        dateLocale,
    };
}

/** Called once before mount, and again when the preference changes. */
export function configureFormats(next: Partial<FormatConfig>): void {
    config = { ...config, ...next };
    cache = build(config);
    currencyCache.clear();
}

export function formatConfig(): FormatConfig {
    return config;
}

/** 12_400 -> "12.4K". Used in metric cells where the bar carries the magnitude. */
export function compactNumber(value: number): string {
    return cache.compact.format(value);
}

export function number(value: number): string {
    return cache.plain.format(value);
}

/**
 * Money is stored in minor units everywhere in this codebase.
 *
 * The *currency* is not a preference — an invoice in dollars says dollars
 * wherever it is read — but the grouping and decimal marks follow the reader's
 * choice, so a European sees $1.234,50 rather than being told their own
 * convention is wrong.
 */
export function money(minorUnits: number, currency = 'USD'): string {
    return currencyFormatter(currency).format(minorUnits / 100);
}

/**
 * One formatter per currency, discarded when the preference changes.
 *
 * money() is the single most-called function here — a ledger page builds two of
 * these per row — and constructing an Intl.NumberFormat is the expensive part.
 */
const currencyCache = new Map<string, Intl.NumberFormat>();

function currencyFormatter(currency: string): Intl.NumberFormat {
    const key = `${cache.numberLocale}:${currency}`;
    const hit = currencyCache.get(key);

    if (hit !== undefined) return hit;

    const made = new Intl.NumberFormat(cache.numberLocale, {
        style: 'currency',
        currency,
        // narrowSymbol, or fr-FR renders USD as "$US" and de-DE as "$" only by
        // luck. The reader's grouping is a preference; renaming their currency
        // is not.
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: 2,
    });

    currencyCache.set(key, made);

    return made;
}

export function date(value: string | Date): string {
    return cache.date.format(typeof value === 'string' ? new Date(value) : value);
}

/** Date and clock time together, for a ledger or an audit row. */
export function dateTime(value: string): string {
    return cache.dateTime.format(new Date(value));
}

/**
 * Day and month, no year. Chart ticks and "fetched on" stamps, where the year
 * is either obvious or noise — but the *order* still follows the preference,
 * because "Sep 6" and "6 Sep" is most of what choosing a date format means.
 */
export function dayMonth(value: string | Date): string {
    return new Intl.DateTimeFormat(cache.dateLocale, {
        day: 'numeric',
        month: 'short',
        timeZone: config.timeZone,
    }).format(typeof value === 'string' ? new Date(value) : value);
}

/** Month and year, for a trend axis. */
export function monthYear(value: string | Date, long = false): string {
    return new Intl.DateTimeFormat(cache.dateLocale, {
        month: long ? 'long' : 'short',
        ...(long ? { year: 'numeric' as const } : {}),
        timeZone: config.timeZone,
    }).format(typeof value === 'string' ? new Date(value) : value);
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
    return cache.time.format(new Date(value));
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

    return new Intl.DateTimeFormat(cache.dateLocale, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: then.getFullYear() === today.getFullYear() ? undefined : 'numeric',
        timeZone: config.timeZone,
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
