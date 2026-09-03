import { cn } from '@shared/lib/cn';
import { date, money, number } from '@shared/lib/format';
import { GlobeIcon } from '@shared/ui';
import type { PostRow } from '@shared/types/posts';

interface Props {
    rows: PostRow[];
    onCardClick: (row: PostRow) => void;
    activeId: number | null;
    /** More posts than this page holds, so the columns are partial. */
    paged: boolean;
}

/**
 * The same posts as columns of cards, grouped by status.
 *
 * Five columns, not nine: Draft, Completed, Cancelled and Refunded are not
 * phases anyone is watching move — they are before the work and after it. The
 * board is for the middle, and the table is still there for everything else.
 *
 * Nothing drags. An advertiser cannot move a post between statuses: a post is
 * `in_progress` because a writer is writing it and `posted` because a link is
 * live, and no amount of dragging makes either true. A board that accepted the
 * gesture and then snapped back would be worse than one that never offered it,
 * so the note above the board says so in a line and the cards open the drawer
 * instead.
 */
const COLUMNS: { key: string; label: string; statuses: string[]; accent: string }[] = [
    { key: 'new', label: 'New', statuses: ['new'], accent: 'var(--status-new-fg)' },
    { key: 'in_progress', label: 'In progress', statuses: ['in_progress'], accent: 'var(--status-progress-fg)' },
    {
        key: 'content_review',
        label: 'Content review',
        statuses: ['content_review'],
        accent: 'var(--status-review-fg)',
    },
    {
        // Two statuses, exactly as the Posted tab above the board defines it:
        // a link is live either way, and `completed` only means its
        // verification window has closed. A column named Posted that showed
        // fewer posts than the tab named Posted would be a bug on screen.
        key: 'posted',
        label: 'Posted',
        statuses: ['posted', 'completed'],
        accent: 'var(--status-posted-fg)',
    },
    { key: 'rejected', label: 'Rejected', statuses: ['rejected'], accent: 'var(--danger)' },
];

/** Below this, the deadline is the most urgent thing on the card. */
const URGENT_HOURS = 48;

export function PostsBoard({ rows, onCardClick, activeId, paged }: Props) {
    return (
        <div>
            <p className="mb-3 text-sm text-ink-500">
                Cards do not drag between columns — a post moves when the writer or the publisher moves it, not when you
                do. Open a card to see where it stands.
                {paged && ' This is one page of posts; the rest are on the pages below.'}
            </p>

            <div className="flex gap-3 overflow-x-auto pb-2">
                {COLUMNS.map((column) => {
                    const cards = rows.filter((row) => column.statuses.includes(row.status));

                    return (
                        <section
                            key={column.key}
                            aria-label={`${column.label}, ${cards.length} posts`}
                            className="flex w-72 shrink-0 flex-col rounded-card bg-sunken"
                        >
                            <header className="flex items-center justify-between gap-2 px-3 pb-2 pt-3">
                                <span className="flex items-center gap-2">
                                    <span
                                        aria-hidden="true"
                                        className="size-2 rounded-pill"
                                        style={{ backgroundColor: column.accent }}
                                    />
                                    <span className="text-sm font-medium text-ink-900">{column.label}</span>
                                </span>
                                <span className="num text-xs text-ink-500">{number(cards.length)}</span>
                            </header>

                            <div className="flex flex-col gap-2 px-2 pb-2">
                                {cards.length === 0 ? (
                                    <p className="px-1 py-3 text-center text-xs text-ink-500">Nothing here.</p>
                                ) : (
                                    cards.map((row) => (
                                        <Card
                                            key={row.id}
                                            row={row}
                                            active={row.id === activeId}
                                            onClick={() => onCardClick(row)}
                                        />
                                    ))
                                )}
                            </div>
                        </section>
                    );
                })}
            </div>
        </div>
    );
}

function Card({ row, active, onClick }: { row: PostRow; active: boolean; onClick: () => void }) {
    const urgency = deadlineUrgency(row.deadlineAt);

    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={active ? 'true' : undefined}
            className={cn(
                'flex w-full flex-col gap-2 rounded-card border bg-card p-3 text-left shadow-card',
                'transition-colors duration-fast ease-standard hover:border-brand',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                active ? 'border-brand' : 'border-subtle',
            )}
        >
            <div className="flex items-center gap-2">
                {/* Publinza does not store its own site marks yet, so this is a
                    glyph of the favicon's size — nothing shifts if one lands. */}
                <span className="flex size-4 shrink-0 items-center justify-center text-ink-300">
                    <GlobeIcon size={14} />
                </span>

                <span className="min-w-0 flex-1 truncate text-sm font-medium text-ink-900">{row.domain}</span>

                {row.hasUnread && (
                    <span
                        className="size-2 shrink-0 rounded-pill bg-brand"
                        role="img"
                        aria-label="Unread messages"
                        title="Unread messages"
                    />
                )}
            </div>

            <p className="line-clamp-2 text-sm text-ink-700">{row.anchorText ?? <em>No anchor yet</em>}</p>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="num text-sm font-medium text-ink-900">{money(row.priceCents)}</span>

                {row.deadlineAt !== null && (
                    <span
                        className={cn(
                            'num rounded-pill px-2 py-0.5 text-xs',
                            urgency === 'urgent'
                                ? 'bg-status-progress-bg font-medium text-status-progress-fg'
                                : 'text-ink-500',
                        )}
                        // The tint is never the only signal: overdue and due-soon
                        // both say so in words a screen reader reaches.
                        title={urgency === 'urgent' ? 'Due within 48 hours' : undefined}
                    >
                        {urgency === 'urgent' && <span className="sr-only">Due soon: </span>}
                        {date(row.deadlineAt)}
                    </span>
                )}
            </div>
        </button>
    );
}

function deadlineUrgency(deadline: string | null): 'urgent' | 'normal' {
    if (deadline === null) return 'normal';

    const hours = (new Date(deadline).getTime() - Date.now()) / 3_600_000;

    return hours < URGENT_HOURS ? 'urgent' : 'normal';
}
