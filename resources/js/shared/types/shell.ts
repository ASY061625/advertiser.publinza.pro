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
    /** Published entries this account has not seen. Drives the dot. */
    changelog: number;
    /** How many of those are flagged major. Drives the number. */
    changelogMajor: number;
    notifications: number;
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

