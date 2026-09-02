export interface SitePreview {
    ok: boolean;
    url?: string;
    host?: string;
    title?: string | null;
    description?: string | null;
    favicon?: string | null;
    /** The colour this domain suggests, computed server-side. */
    suggested_color?: string;
    reason?: string;
}

export interface LandingPageRow {
    /** Stable across reorders, so React keys survive a drag. */
    key: string;
    anchor_text: string;
    url: string;
}

export interface WizardState {
    website_url: string;
    name: string;
    category_id: string;
    color: string;
    sensitive_topic_ids: number[];
    country_ids: number[];
    language_ids: number[];
    publisher_task: string;
    landing_pages: LandingPageRow[];
    preview: SitePreview | null;
}

export interface WizardOptions {
    categories: { id: number; name: string }[];
    topics: { id: number; name: string }[];
    countries: { id: number; code: string; name: string }[];
    languages: { id: number; code: string; name: string }[];
    colors: string[];
}
