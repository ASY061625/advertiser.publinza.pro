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
