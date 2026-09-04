export interface CatalogWarning {
    kind: 'language' | 'topic';
    message: string;
}

export interface CatalogRow {
    id: number;
    slug: string;
    domain: string;
    title: string;
    category: string;
    language: { code: string; name: string };
    country: { code: string; name: string };
    /** Null where no metric has ever been recorded — not the same as zero. */
    traffic: number | null;
    domainRating: number | null;
    domainAuthority: number | null;
    spamScore: number | null;
    publicationHours: number;
    publicationLabel: string;
    linkType: 'dofollow' | 'nofollow';
    priceCents: number;
    /** Zero when the publisher does not offer to write the article. */
    writingFeeCents: number;
    isFavorite: boolean;
    isBlacklisted: boolean;
    /** The cart item, so a row already in the cart can offer to remove it. */
    cartItemId: number | null;
    usedInProject: boolean;
    warnings: CatalogWarning[];
}

export interface CatalogSiteDetail extends CatalogRow {
    description: string | null;
    guidelines: string | null;
    sampleUrl: string | null;
    minWords: number;
    maxLinks: number;
    linksAllowed: number;
    acceptsTopics: string[];
    services: { type: string; label: string; priceCents: number; writingFeeCents: number }[];
}

export interface FacetOption {
    id: number;
    name: string;
    /** ISO code, for the flag glyph. Countries and languages only. */
    code?: string;
    count: number;
}

export interface CatalogFacets {
    categories: FacetOption[];
    countries: FacetOption[];
    languages: FacetOption[];
    /** Bar heights across the whole price range, for the slider's backdrop. */
    priceHistogram: number[];
}

export interface CatalogRangeSet {
    traffic: [number, number];
    domainRating: [number, number];
    domainAuthority: [number, number];
    spamScore: [number, number];
    /** Cents. */
    price: [number, number];
}

/**
 * The filter state, exactly as it appears in the query string.
 *
 * Ranges are "10-250" strings rather than pairs, because that is what a person
 * reading the URL sees and what the server parses. Keeping the wire format here
 * means there is one representation to get right instead of two that have to
 * agree.
 */
export interface CatalogFilterState {
    q?: string;
    domain?: string;
    categories?: number[];
    countries?: number[];
    languages?: number[];
    price?: string;
    traffic?: string;
    dr?: string;
    da?: string;
    max_spam?: number;
    speed?: string[];
    link_type?: 'dofollow' | 'nofollow';
    topics?: string[];
    show_blacklisted?: boolean;
    favorites?: boolean;
    unused?: boolean;
    has_traffic?: boolean;
    sort?: string;
    per_page?: number;
    view?: 'table' | 'cards';
    project?: number;
    /** Where in the results this page starts. Not a filter, but it lives here
     *  because it is a query parameter and the URL is the whole state. */
    cursor?: string;
}

export interface CatalogOptions {
    speeds: { value: string; label: string }[];
    topics: { id: number; name: string; slug: string }[];
    services: { value: string; label: string }[];
    sorts: { value: string; label: string }[];
    perPage: number[];
}

export interface CatalogSuggestion {
    label: string;
    count: number;
    query: Record<string, unknown>;
}

export interface CatalogProject {
    id: number;
    name: string;
    color: string | null;
}

export interface CatalogPagination {
    perPage: number;
    nextCursor: string | null;
    previousCursor: string | null;
    hasMore: boolean;
}
