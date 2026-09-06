import type { CatalogRangeSet, CatalogRow } from './catalog';

export type ListTab = 'favorites' | 'wishlist' | 'blacklist';

/** A catalog row plus whatever the list it sits in knows about it. */
export interface ListRow extends CatalogRow {
    addedAt: string | null;
    /** Wishlist only. */
    itemId?: number;
    note?: string | null;
    priority?: boolean;
    /** Blacklist only. */
    entryId?: number;
    reason?: string | null;
    blockedBy?: string;
}

export interface WishlistSummary {
    id: number;
    name: string;
    itemCount: number;
}

export interface WishlistTotals {
    siteCount: number;
    totalCents: number;
    /** Null when nothing in the list has been measured. */
    averageDr: number | null;
    totalTraffic: number;
    /** How many of the sites the average is actually of. */
    measured: number;
}

export interface ListFilters {
    q: string | null;
    category: number | null;
    sort: string;
    list: number | null;
}

export interface ImportReport {
    blocked: string[];
    already: string[];
    unmatched: string[];
}

/** What an undo toast needs to reverse a move. */
export interface MovedRecord {
    from: ListTab;
    to: ListTab;
    websiteId: number;
    domain: string;
    wishlistId: number | null;
    note: string | null;
    priority: boolean;
    reason: string | null;
}

export interface ListsPageProps {
    tab: ListTab;
    filters: ListFilters;
    rows: ListRow[];
    counts: Record<ListTab, number>;
    wishlists: WishlistSummary[];
    summary: WishlistTotals | null;
    ranges: CatalogRangeSet;
    categories: { id: number; name: string }[];
    projects: { id: number; name: string; color: string | null }[];
    sorts: { value: string; label: string }[];
}
