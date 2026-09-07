/** Which template a group renders its rows with. */
export type SearchGroupKey = 'websites' | 'projects' | 'posts' | 'conversations' | 'actions';

export interface SearchItem {
    id: string;
    title: string;
    subtitle: string | null;
    href: string;
    /** Websites: price in cents. Projects: post count. Unused elsewhere. */
    meta?: number | null;
    /** Projects only — the dot beside the name. */
    color?: string | null;
    /** Posts only. */
    status?: string;
    statusLabel?: string;
    /** Conversations only. */
    excerpt?: string | null;
    /** Actions only: run instead of navigating. */
    run?: () => void;
    /** Actions only: the icon name the palette resolves. */
    icon?: string;
}

export interface SearchGroup {
    key: SearchGroupKey;
    label: string;
    items: SearchItem[];
    /** Null on the recent-views list, which has nowhere honest to point. */
    seeAll: string | null;
}

export interface SearchResponse {
    query: string;
    groups: SearchGroup[];
    /** True when the server answered with recent views rather than matches. */
    recent: boolean;
}
