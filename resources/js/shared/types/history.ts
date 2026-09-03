export type HistoryFamily = 'project' | 'folder' | 'post' | 'money' | 'message';

export interface HistoryFieldRow {
    field: string;
    from: string | null;
    to: string | null;
}

export type HistoryDetail =
    | {
          kind: 'post';
          postId: number;
          domain: string | null;
          anchorText: string | null;
          targetUrl: string | null;
          priceCents: number;
          note: string | null;
      }
    | { kind: 'fields'; rows: HistoryFieldRow[] }
    | { kind: 'text-diff'; rows: HistoryFieldRow[] };

export interface HistoryEvent {
    /** Unique across sources: the source name and its row id. */
    id: string;
    family: HistoryFamily;
    /** The raw status or action, used for the icon and the post family's colour. */
    eventKey: string;
    occurredAt: string;
    actor: string;
    description: string;
    detail: HistoryDetail | null;
}

export interface HistoryFilterState {
    families?: HistoryFamily[];
    actor?: 'user' | 'admin' | 'system';
    from?: string;
    to?: string;
    q?: string;
}

export interface HistoryPayload {
    events: HistoryEvent[];
    total: number;
    hasMore: boolean;
    filters: HistoryFilterState;
    isFiltering: boolean;
    perPage: number;
    /** Where this page started reading, echoed back so a reload is idempotent. */
    cursor: string | null;
    /** The position the next page continues from; null at the end of the log. */
    nextCursor: string | null;
    /** False only when the project has no history at all, filters aside. */
    hasAnyHistory: boolean;
}
