import type { StatusKey } from './index';

export type ConversationTab = 'all' | 'unread' | 'open' | 'closed';

export type SenderType = 'user' | 'admin' | 'system';

export interface MessageAttachment {
    id: number;
    name: string;
    sizeBytes: number;
    mimeType: string;
    /** Images render as thumbnails; everything else renders as a chip. */
    isImage: boolean;
    url: string;
}

export interface ConversationMessage {
    /** Negative while the message is optimistic — see useSendMessage. */
    id: number;
    senderType: SenderType;
    senderName: string;
    body: string;
    createdAt: string | null;
    /** True once the server has it. False only on a message still in flight. */
    delivered: boolean;
    /** Set when the team has opened it. Null on their own messages. */
    readAt: string | null;
    clientToken: string | null;
    attachments: MessageAttachment[];
    /** Client-only: an optimistic message whose request came back a failure. */
    failed?: boolean;
}

export interface ThreadPost {
    id: number;
    status: string;
    statusLabel: string;
    badge: StatusKey;
}

export interface ThreadRow {
    id: number;
    subject: string;
    domain: string;
    websiteSlug: string | null;
    excerpt: string;
    lastMessageAt: string | null;
    unreadCount: number;
    status: 'open' | 'closed';
    muted: boolean;
    post: ThreadPost | null;
}

export interface Thread extends ThreadRow {
    websiteTitle: string | null;
    messages: ConversationMessage[];
}

export interface ContextMetric {
    label: string;
    /** Null where the measure has never been recorded. Not the same as zero. */
    value: number | null;
    format: 'compact' | 'plain';
}

export interface WebsiteContext {
    kind: 'website';
    domain: string;
    title: string | null;
    slug: string;
    category: string | null;
    country: string | null;
    language: string | null;
    metrics: ContextMetric[];
    terms: {
        publicationLabel: string;
        linkType: 'dofollow' | 'nofollow';
        maxLinks: number;
        minWords: number;
        marksSponsored: boolean;
        /** Zero means no guarantee — a real answer, not a missing one. */
        linkGuaranteeMonths: number;
    };
}

export interface PostContext {
    kind: 'post';
    id: number;
    domain: string | null;
    status: string;
    statusLabel: string;
    badge: StatusKey;
    anchorText: string | null;
    targetUrl: string | null;
    priceCents: number;
    publishedUrl: string | null;
    hasArticle: boolean;
    timeline: { id: number; from: string | null; to: string; at: string | null }[];
}

export type ThreadContext = WebsiteContext | PostContext;

export interface CannedResponse {
    id: string;
    label: string;
    body: string;
}

export interface ConversationFilters {
    tab: ConversationTab;
    q: string | null;
    thread: number | null;
}

export interface ConversationCounts {
    all: number;
    unread: number;
    open: number;
    closed: number;
}

export interface Availability {
    online: boolean;
    note: string;
}

export interface ComposerOptions {
    posts: { id: number; domain: string; label: string }[];
    websites: { id: number; domain: string }[];
}

export interface ConversationsPageProps {
    threads: ThreadRow[];
    thread: Thread | null;
    context: ThreadContext | null;
    filters: ConversationFilters;
    counts: ConversationCounts;
    availability: Availability;
    cannedResponses: CannedResponse[];
    /** Account-wide: does a team reply email this person at all. */
    notifyReplies: boolean;
    [key: string]: unknown;
}
