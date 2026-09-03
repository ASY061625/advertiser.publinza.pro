export interface StatisticsPoint {
    /** Bucket start, ISO date. The tooltip's "View posts" link derives its window from it. */
    iso: string;
    label: string;
    ordered: number;
    publishedCount: number;
    dofollow: number;
    nofollow: number;
    spendCents: number;
    cumulativeSpendCents: number;
    /** Running total of links live at the end of this period, including any carried in. */
    liveLinks: number;
    /** Null when nothing published in the period — there is no average to show. */
    averageCents: number | null;
}

export interface StatisticsBreakdownRow {
    label: string;
    spentCents: number;
    placements: number;
}

export interface StatisticsSummary {
    spentCents: number;
    spentDeltaPct: number | null;
    published: number;
    publishedDeltaPct: number | null;
    links: number;
    linksDeltaPct: number | null;
    averageCents: number | null;
    averageDeltaPct: number | null;
}

export type StatisticsGranularity = 'day' | 'week' | 'month';

export interface StatisticsPayload {
    range: { key: string; from: string; to: string; label: string };
    granularity: StatisticsGranularity;
    folderId: number | null;
    summary: StatisticsSummary;
    series: StatisticsPoint[];
    byCategory: StatisticsBreakdownRow[];
    byFolder: StatisticsBreakdownRow[];
    /** False only when the project has never had a post at all. */
    hasEverHadPosts: boolean;
    folders: { id: number; name: string }[];
}
