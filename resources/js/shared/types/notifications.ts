/** The icon names the server sends; each maps to one component. */
export type NotificationIcon =
    | 'success'
    | 'danger'
    | 'document'
    | 'chat'
    | 'clock'
    | 'wallet'
    | 'receipt'
    | 'tag'
    | 'chart'
    | 'download'
    | 'info';

/** The semantic colour the icon wears, drawn from the status palette. */
export type NotificationTone = 'success' | 'danger' | 'warning' | 'gold' | 'review' | 'info';

export interface NotificationItem {
    kind: 'item';
    id: string;
    type: string;
    title: string;
    body: string;
    href: string;
    icon: NotificationIcon;
    tone: NotificationTone;
    at: string | null;
    unread: boolean;
}

/** A run of the same type, folded into one line the reader can open. */
export interface NotificationGroup {
    kind: 'group';
    id: string;
    type: string;
    title: string;
    icon: NotificationIcon;
    tone: NotificationTone;
    at: string | null;
    unread: boolean;
    unreadCount: number;
    items: NotificationItem[];
}

export type NotificationEntry = NotificationItem | NotificationGroup;

/** Today / Yesterday / Earlier, bucketed server-side in the reader's timezone. */
export interface NotificationBucket {
    key: 'today' | 'yesterday' | 'earlier';
    label: string;
    items: NotificationEntry[];
}

export interface NotificationCentreData {
    groups: NotificationBucket[];
    counts: { all: number; unread: number };
    hasMore: boolean;
    /** True when any type has browser push switched on for this account. */
    pushWanted: boolean;
}

/** What a broadcast carries. `push` is the server's answer, not the browser's. */
export interface BroadcastNotification {
    id: string;
    type: string;
    title: string;
    body: string;
    href: string;
    icon: NotificationIcon;
    tone: NotificationTone;
    push?: boolean;
}

export type ChangelogTypeName = 'new' | 'improved' | 'fixed';

export interface ChangelogEntry {
    id: number;
    slug: string;
    anchor: string;
    title: string;
    body: string;
    type: ChangelogTypeName;
    typeLabel: string;
    isMajor: boolean;
    imageUrl: string | null;
    publishedAt: string | null;
    unread: boolean;
}

export interface ChangelogMonth {
    key: string;
    label: string;
    entries: ChangelogEntry[];
}

/** The one major release still owed a modal, shared on every page. */
export interface Announcement {
    id: number;
    title: string;
    body: string;
    type: ChangelogTypeName;
    typeLabel: string;
    imageUrl: string | null;
    publishedAt: string | null;
}
