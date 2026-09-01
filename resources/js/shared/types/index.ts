// StatusKey is owned by the Badge that renders it, so the vocabulary and
// its colours cannot drift apart.
export type { StatusKey } from '@shared/ui';

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: string;
}

export interface Flash {
    success?: string;
    error?: string;
}

/** Props Inertia shares with every page on every surface. */
export interface SharedProps {
    appName: string;
    flash: Flash;
    [key: string]: unknown;
}

export interface AdvertiserSharedProps extends SharedProps {
    auth: { user: User | null };
    balanceMinorUnits: number;
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
