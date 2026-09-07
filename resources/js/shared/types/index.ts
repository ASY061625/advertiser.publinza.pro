import type { ImportReport, MovedRecord } from './lists';

// StatusKey is owned by the Badge that renders it, so the vocabulary and
// its colours cannot drift apart.
export type { StatusKey } from '@shared/ui';

import type { Shell } from './shell';

export interface User {
    id: number;
    name: string;
    /** What the app calls them. Falls back to the first word of `name`. */
    displayName: string;
    email: string;
    /** Null until one is uploaded — Avatar draws initials instead. */
    avatarUrl: string | null;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: string;
}

export interface Flash {
    /** Every key is present on every response, and null when unset. */
    success?: string | null;
    error?: string | null;
    /**
     * A list move, with everything undo needs to reverse it. It travels with
     * the response because the note and the reason are gone from the database
     * by the time the toast renders.
     */
    moved?: MovedRecord | null;
    /** What a blacklist import did, in three named groups. */
    importReport?: ImportReport | null;
    /**
     * Recovery codes and API tokens exist in plaintext exactly once — in the
     * response that created them. Both travel here for that one render and are
     * never retrievable again.
     */
    recoveryCodes?: string[] | null;
    newToken?: { name: string; plain: string } | null;
}

/** Props Inertia shares with every page on every surface. */
export interface SharedProps {
    appName: string;
    flash: Flash;
    [key: string]: unknown;
}

export interface AdvertiserSharedProps extends SharedProps {
    auth: { user: User | null };
    balanceCents: number;
    /** Null before sign-in; every authenticated page has it. */
    shell: Shell | null;
}

export interface AdminSharedProps extends SharedProps {
    auth: { admin: AdminUser | null };
}

/** One catalog row as the advertiser sees it. */
export interface CatalogSite {
    id: number;
    domain: string;
    language: string;
    category: string;
    priceMinorUnits: number;
    traffic: number;
    domainRating: number;
    domainAuthority: number;
    spamScore: number;
}

/** Min/max per metric across the whole filtered catalog, so a quant-bar is
 *  scaled against the catalog's own range rather than the visible page. */
export interface CatalogRanges {
    traffic: [number, number];
    domainRating: [number, number];
    domainAuthority: [number, number];
    spamScore: [number, number];
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
