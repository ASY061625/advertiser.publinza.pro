export interface ShellProject {
    id: number;
    name: string;
    /** Deterministic per project, so the dot never changes between sessions. */
    color: string;
}

export interface ShellCartItem {
    id: number;
    domain: string;
    project: string | null;
    priceCents: number;
}

export interface ShellConversation {
    id: number;
    domain: string;
    favicon: string | null;
    excerpt: string;
    at: string | null;
    unread: boolean;
}

export interface ShellCounts {
    cart: number;
    conversations: number;
    changelog: number;
    favorites: number;
}

export interface EchoConfig {
    key: string;
    host: string;
    port: number;
    scheme: string;
}

export interface Shell {
    version: string;
    sidebarCollapsed: boolean;
    projects: ShellProject[];
    balance: { availableCents: number; frozenCents: number };
    cart: { items: ShellCartItem[]; subtotalCents: number; moreCount: number };
    conversations: ShellConversation[];
    counts: ShellCounts;
    /** Null when no broadcaster is configured; the shell then polls instead. */
    echo: EchoConfig | null;
}

export interface ChangelogEntry {
    id: number;
    title: string;
    body: string;
    category: string;
    publishedAt: string | null;
    unread: boolean;
}

export interface SearchGroup {
    label: string;
    items: { id: string; title: string; subtitle: string | null; href: string }[];
}
