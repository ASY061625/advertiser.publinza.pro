import type { CatalogRangeSet, CatalogRow } from './catalog';

export interface WizardFolder {
    id: number;
    name: string;
    /** Overrides the project's brief when set — the folder editor's own rule. */
    publisherTask: string | null;
}

export interface WizardLandingPage {
    id: number;
    folderId: number | null;
    anchorText: string;
    url: string;
}

export interface WizardProject {
    id: number;
    name: string;
    color: string | null;
    websiteUrl: string | null;
    publisherTask: string | null;
    folders: WizardFolder[];
    landingPages: WizardLandingPage[];
}

export interface WizardService {
    type: string;
    label: string;
    priceCents: number;
    writingFeeCents: number;
    expressFeeCents: number;
}

/** The chosen site, in the detail the summary strip and step 3 need. */
export interface WizardWebsite {
    id: number;
    slug: string;
    domain: string;
    publicationHours: number;
    linkType: 'dofollow' | 'nofollow';
    linksAllowed: number;
    maxLinks: number;
    minWords: number;
    /** Empty on most sites; the length select renders only where it is not. */
    wordCountTiers: number[];
    guidelines: string | null;
    services: WizardService[];
}

export interface WizardOptions {
    projects: WizardProject[];
    categories: { id: number; name: string }[];
    services: { value: string; label: string }[];
    draft: { step: number; payload: Record<string, unknown>; savedAt: string | null } | null;
    wallet: { availableCents: number; frozenCents: number };
}

/**
 * The whole wizard, in one object.
 *
 * One state rather than per-step state is what makes "back never loses data"
 * true by construction: the steps render from this and own nothing, so going
 * back changes which step is visible and nothing else.
 */
export interface PostWizardState {
    projectId: string;
    folderId: string;
    /** The saved anchor/URL pair, or "" when entering one by hand. */
    landingPageId: string;
    anchorText: string;
    targetUrl: string;
    websiteId: string;
    /** The same site by its public key: every route that acts on a site takes a slug. */
    websiteSlug: string;
    serviceType: string;
    express: boolean;
    contentMode: 'advertiser_provides' | 'publisher_writes';
    title: string;
    body: string;
    brief: string;
    keywords: string;
    tone: string;
    targetWords: string;
    /** The four filters step 2 offers, as the catalog's own query keys. */
    search: string;
    categoryId: string;
    price: string;
    traffic: string;
    dr: string;
}

export interface WizardResults {
    sites: CatalogRow[];
    total: number;
    /** The catalog-wide ranges the quant bars scale against. */
    ranges: CatalogRangeSet;
    query: Record<string, unknown>;
}

export interface PostDraftCard {
    step: number;
    savedAt: string | null;
    summary: string;
}
