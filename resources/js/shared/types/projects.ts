export interface ProjectPostMix {
    total: number;
    new: number;
    progress: number;
    posted: number;
    frozen: number;
    /** Draft, rejected, cancelled and refunded — the remainder of the total. */
    other: number;
}

export interface ProjectRow {
    id: number;
    name: string;
    websiteUrl: string;
    category: string | null;
    /** Chosen in the wizard; null on projects created before it existed. */
    color: string | null;
    status: string;
    statusLabel: string;
    isArchived: boolean;
    createdAt: string | null;
    posts: ProjectPostMix;
    frozenCents: number;
    /** Null when nothing has completed yet — there is no average to show. */
    averageCents: number | null;
    spentMonthCents: number;
    spentQuarterCents: number;
    /** Null when last month was zero: "New", not "up 100%". */
    spentMonthDeltaPct: number | null;
}

export interface ProjectTotals {
    posts: number;
    frozenCents: number;
    spentMonthCents: number;
    spentQuarterCents: number;
}

export interface ProjectFilterState {
    status?: 'active' | 'archived' | 'all';
    q?: string;
    sort?: 'name' | 'posts' | 'spent_month' | 'created_at';
    direction?: 'asc' | 'desc';
}

/** One project's own page — the header, and what the General tab renders. */
export interface ProjectDetail {
    id: number;
    name: string;
    websiteUrl: string;
    category: string | null;
    categoryId: number | null;
    color: string | null;
    status: string;
    isArchived: boolean;
    /** Sanitised on write. Safe to render as HTML; see HtmlSanitizer. */
    publisherTask: string | null;
    createdAt: string | null;
}

export interface ProjectOverviewStats {
    posts: ProjectPostMix;
    /** All-time completed spend on this project, not the list's monthly window. */
    spentCents: number;
    frozenCents: number;
    /** Null when nothing has completed — there is no average to show. */
    averageCents: number | null;
}

export interface ProjectFolderRow {
    id: number;
    name: string;
    landingPages: number;
    posts: number;
    /** Non-terminal posts. Above zero, the folder cannot be deleted. */
    activePosts: number;
    /** First 60 characters of the folder's writer brief, tags stripped. */
    taskExcerpt: string | null;
}

export type ProjectTabId = 'general' | 'posts' | 'settings' | 'statistics' | 'history' | 'competitors';
