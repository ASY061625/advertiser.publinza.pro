export type RangeKey = 'last_7' | 'last_30' | 'quarter' | 'year' | 'custom';
export type Granularity = 'day' | 'week' | 'month';

export interface Stat {
    value: number;
    format: 'money' | 'count';
    /** Null when the previous period was zero — "new", not "up 100%". */
    deltaPct: number | null;
}

export interface SeriesPoint {
    iso: string;
    label: string;
    placements: number;
    spendCents: number;
}

export interface StatusSlice {
    status: string;
    label: string;
    badge: string;
    count: number;
    pct: number;
}

export interface RecentPost {
    id: number;
    domain: string;
    favicon: string | null;
    project: string | null;
    anchorText: string | null;
    status: string;
    statusLabel: string;
    badge: string;
    priceCents: number;
    createdAt: string | null;
    publishedUrl: string | null;
}

export interface TopWebsite {
    domain: string;
    placements: number;
    totalCents: number;
}

export interface Deadline {
    id: number;
    domain: string;
    statusLabel: string;
    badge: string;
    deadlineAt: string | null;
    /** Under 48 hours. The threshold is decided server-side, once. */
    urgent: boolean;
}

export interface DashboardMetrics {
    range: { key: RangeKey; from: string; to: string; label: string };
    granularity: Granularity;
    projectId: number | null;
    hasProjects: boolean;
    hasAnyPosts: boolean;
    stats: {
        totalSpent: Stat;
        availableBalance: Stat;
        frozenFunds: Stat;
        activeProjects: Stat;
        postsInProgress: Stat;
        liveLinks: Stat;
    };
    series: SeriesPoint[];
    statusBreakdown: StatusSlice[];
    recentPosts: RecentPost[];
    topWebsites: TopWebsite[];
    deadlines: Deadline[];
}
