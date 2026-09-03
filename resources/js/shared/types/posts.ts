import type { StatusKey } from '@shared/ui';

export interface PostRow {
    id: number;
    domain: string;
    dr: number | null;
    traffic: number | null;
    project: string | null;
    projectId: number | null;
    folder: string | null;
    anchorText: string | null;
    targetUrl: string | null;
    status: string;
    statusLabel: string;
    badge: StatusKey;
    canCancel: boolean;
    priceCents: number;
    createdAt: string | null;
    publishedAt: string | null;
    deadlineAt: string | null;
    publishedUrl: string | null;
    /** Null until Publinza stores its own site marks; the card falls back to a glyph. */
    favicon: string | null;
    /** Unread by the advertiser — their own messages never count. */
    hasUnread: boolean;
}

export interface NamedOption {
    id: number;
    name: string;
}

export interface ProjectOption extends NamedOption {
    folders: { id: number; project_id: number; name: string }[];
}

export interface PostOptions {
    projects: ProjectOption[];
    statuses: { value: string; label: string; badge: StatusKey }[];
    tabs: { value: string; label: string; badge: string }[];
    categories: NamedOption[];
    countries: NamedOption[];
    languages: NamedOption[];
}

export interface ColumnPreferences {
    order: string[];
    hidden: string[];
    available: { id: string; label: string; lockable: boolean }[];
}

export interface SavedViewRecord {
    id: number;
    name: string;
    query: Record<string, unknown>;
}

/** The filter state, mirrored exactly by the query string. */
export interface PostFilterState {
    tab?: string;
    q?: string;
    projects?: number[];
    statuses?: string[];
    date_field?: 'created' | 'published';
    from?: string;
    to?: string;
    categories?: number[];
    countries?: number[];
    languages?: number[];
    min_price?: number;
    max_price?: number;
    content_mode?: 'advertiser_provides' | 'publisher_writes';
    anchor?: string;
    target?: string;
    min_dr?: number;
    max_dr?: number;
    min_traffic?: number;
    max_traffic?: number;
    folder?: number;
    unread?: number;
    deadline?: '24h' | '3d' | '7d' | 'overdue';
    sort?: string;
    direction?: 'asc' | 'desc';
    per_page?: number;
}

export interface PostDetail {
    id: number;
    status: string;
    statusLabel: string;
    badge: StatusKey;
    canCancel: boolean;
    details: {
        domain: string | null;
        websiteTitle: string | null;
        country: string | null;
        dr: number | null;
        traffic: number | null;
        project: string | null;
        projectUrl: string | null;
        folder: string | null;
        anchorText: string | null;
        targetUrl: string | null;
        contentMode: string;
        priceCents: number;
        createdAt: string | null;
        publishedAt: string | null;
        deadlineAt: string | null;
        publishedUrl: string | null;
        rejectionReason: string | null;
    };
    article: {
        id: number;
        title: string;
        wordCount: number;
        version: number;
        versions: number;
        submittedBy: string | null;
        approvedAt: string | null;
        bodyHtml: string | null;
        hasFile: boolean;
    } | null;
    messages: {
        id: number;
        conversationId: number;
        subject: string;
        senderType: string;
        body: string;
        readAt: string | null;
        createdAt: string | null;
        attachments: { id: number; name: string; sizeBytes: number }[];
    }[];
    history: {
        id: number;
        from: string | null;
        to: string;
        actorType: string;
        note: string | null;
        createdAt: string | null;
    }[];
}
