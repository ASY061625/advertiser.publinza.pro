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
