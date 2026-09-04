export type CompetitorFetchState = 'pending' | 'ready' | 'failed';

export interface CompetitorMetrics {
    organicTraffic: number;
    organicKeywords: number;
    /** Null where the configured provider does not sell that score. */
    dr: number | null;
    da: number | null;
    referringDomains: number;
    backlinks: number;
    trafficValueCents: number;
}

export type MeasureKey = keyof CompetitorMetrics;

export interface CompetitorDelta {
    /** How far ahead of your site they are, as a percentage. Null if incomparable. */
    percent: number | null;
    /** True when your site leads, false when it trails, null when level or unknown. */
    leading: boolean | null;
}

export interface CompetitorRow {
    id: number;
    domain: string;
    label: string | null;
    isSelf: boolean;
    /** Position among the tracked rivals, which fixes this row's chart colour. */
    slot: number | null;
    state: CompetitorFetchState;
    error: string | null;
    metrics: CompetitorMetrics | null;
    deltas: Record<MeasureKey, CompetitorDelta> | null;
    updatedAt: string | null;
    provider: string | null;
    /** Seconds until Refresh is allowed again; 0 means now. */
    cooldownSeconds: number;
    gapKeywords: number;
}

export interface TrendSeries {
    id: number;
    domain: string;
    label: string | null;
    isSelf: boolean;
    slot: number | null;
    /** One per month, null where that month is missing for this domain. */
    points: (number | null)[];
}

export interface OverlapRow {
    id: number;
    domain: string;
    label: string | null;
    slot: number;
    shared: number;
    theirs: number;
    yours: number;
    gapKeywords: number;
}

export interface Recommendation {
    category: string;
    categoryId: number | null;
    count: number;
    competitor: string;
    competitorId: number;
}

export interface GapKeyword {
    keyword: string;
    position: number;
    volume: number;
    difficulty: number;
    url: string | null;
}

export interface CompetitorsPayload {
    self: CompetitorRow | null;
    competitors: CompetitorRow[];
    slots: { used: number; limit: number };
    source: {
        provider: string;
        updatedAt: string | null;
        /** True when a fetch failed and what is on screen is the last good answer. */
        showingCached: boolean;
        cachedSince: string | null;
        cacheDays: number;
    };
    trend: { months: string[]; series: TrendSeries[] };
    overlap: OverlapRow[];
    recommendations: Recommendation[];
    maxGapKeywords: number;
}
