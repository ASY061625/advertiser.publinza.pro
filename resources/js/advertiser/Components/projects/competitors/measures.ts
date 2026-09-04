import { compactNumber, money, number } from '@shared/lib/format';
import type { CompetitorMetrics, MeasureKey } from '@shared/types/competitors';

export interface Measure {
    key: MeasureKey;
    header: string;
    /** The compact form for a table cell. */
    format: (value: number) => string;
    /** The exact figure, for a title attribute and the your-site card. */
    exact: (value: number) => string;
}

/**
 * The seven columns, defined once.
 *
 * The table, the your-site card and both numeric charts read this list, so a
 * column cannot be formatted one way in the table and another in the card —
 * which is the way a comparison quietly stops comparing.
 */
export const MEASURES: Measure[] = [
    { key: 'organicTraffic', header: 'Organic traffic', format: compactNumber, exact: number },
    { key: 'organicKeywords', header: 'Organic keywords', format: compactNumber, exact: number },
    { key: 'dr', header: 'DR', format: number, exact: number },
    { key: 'da', header: 'DA', format: number, exact: number },
    { key: 'referringDomains', header: 'Referring domains', format: compactNumber, exact: number },
    { key: 'backlinks', header: 'Backlinks', format: compactNumber, exact: number },
    {
        key: 'trafficValueCents',
        header: 'Traffic value',
        format: (value) => `$${compactNumber(value / 100)}`,
        exact: (value) => money(value),
    },
];

/**
 * A measure's value, or null when this provider does not sell it.
 *
 * DR and DA are the nullable pair — no vendor publishes both — and null has to
 * survive all the way to the cell, which prints an em dash. A zero here would
 * be a real score, and the worst one available.
 */
export function valueOf(metrics: CompetitorMetrics | null, key: MeasureKey): number | null {
    return metrics === null ? null : metrics[key];
}
