import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, GlobeIcon, Input, MailIcon, PlusIcon, SearchIcon, Switch, Tabs } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { relativeTime } from '@shared/lib/format';
import type {
    ConversationCounts,
    ConversationFilters,
    ConversationTab,
    ThreadRow,
} from '@shared/types/conversations';

interface Props {
    threads: ThreadRow[];
    filters: ConversationFilters;
    counts: ConversationCounts;
    activeId: number | null;
    onSelect: (id: number) => void;
    onFilter: (changes: Partial<Record<string, unknown>>) => void;
    onCompose: () => void;
    notifyReplies: boolean;
}

const TABS: { id: ConversationTab; label: string }[] = [
    { id: 'all', label: 'All' },
    { id: 'unread', label: 'Unread' },
    { id: 'open', label: 'Open' },
    { id: 'closed', label: 'Closed' },
];

/**
 * The left column: every thread, newest activity first.
 *
 * Sorted by last activity and never by anything else. An inbox that can be
 * re-sorted is an inbox where the thing you were just reading moves, and the
 * only ordering that matches how people use one of these is "what happened
 * most recently".
 */
export function ThreadList({
    threads,
    filters,
    counts,
    activeId,
    onSelect,
    onFilter,
    onCompose,
    notifyReplies,
}: Props) {
    return (
        <div className="flex h-full min-h-0 w-full flex-col border-subtle bg-card lg:border-r">
            <div className="flex flex-col gap-3 border-b border-subtle p-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="truncate font-sora text-base font-semibold text-ink-900">Conversations</h1>

                    <button
                        type="button"
                        onClick={onCompose}
                        className="flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-button bg-brand px-3 py-1.5 text-sm font-medium text-white transition-colors duration-fast hover:bg-brand-hover"
                    >
                        <PlusIcon size={14} />
                        New conversation
                    </button>
                </div>

                <SearchBox value={filters.q ?? ''} onSearch={(q) => onFilter({ q: q === '' ? null : q })} />

                <Tabs
                    scrollable
                    items={TABS.map((tab) => ({ id: tab.id, label: tab.label, count: counts[tab.id] }))}
                    value={filters.tab}
                    onChange={(tab) => onFilter({ tab })}
                />
            </div>

            <ul aria-label="Conversations" className="min-h-0 flex-1 overflow-y-auto">
                {threads.length === 0 ? (
                    <li className="px-4 py-10 text-center text-base text-ink-500">
                        {filters.q === null && filters.tab === 'all'
                            ? 'No conversations yet.'
                            : 'No conversation matches that.'}
                    </li>
                ) : (
                    threads.map((thread) => (
                        <li key={thread.id}>
                            <ThreadItem
                                thread={thread}
                                active={thread.id === activeId}
                                onSelect={() => onSelect(thread.id)}
                            />
                        </li>
                    ))
                )}
            </ul>

            <EmailToggle enabled={notifyReplies} />
        </div>
    );
}

/**
 * The account-wide switch, at the foot of the inbox it governs.
 *
 * Here rather than on a settings page three screens away, because this is the
 * only surface where somebody thinks about it — and because the reply email
 * itself promises they can turn it off, which has to be true somewhere they can
 * find.
 */
function EmailToggle({ enabled }: { enabled: boolean }) {
    return (
        <div className="flex items-center gap-2 border-t border-subtle px-4 py-2.5">
            <MailIcon size={14} className="shrink-0 text-ink-500" />

            <Switch
                label="Email me when Publinza replies"
                checked={enabled}
                onCheckedChange={(next) =>
                    router.patch(
                        '/settings/notifications',
                        { notify_replies: next },
                        { preserveScroll: true, preserveState: true },
                    )
                }
                className="flex-1 text-sm"
            />
        </div>
    );
}

/**
 * Debounced, and it holds its own text.
 *
 * A controlled input driven by a server round trip loses characters typed
 * during the request — the value snaps back to what the server last knew every
 * time a response lands mid-word.
 */
function SearchBox({ value, onSearch }: { value: string; onSearch: (value: string) => void }) {
    const [text, setText] = useState(value);
    const timer = useRef<number>();

    // Reset when the URL changes underneath — a cleared filter chip elsewhere
    // has to empty this box too.
    useEffect(() => setText(value), [value]);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    return (
        <Input
            label="Search conversations"
            hideLabel
            type="search"
            placeholder="Search subject, message or site"
            value={text}
            leadingIcon={<SearchIcon size={16} />}
            onChange={(event) => {
                const next = event.target.value;
                setText(next);
                window.clearTimeout(timer.current);
                timer.current = window.setTimeout(() => onSearch(next.trim()), 300);
            }}
        />
    );
}

function ThreadItem({
    thread,
    active,
    onSelect,
}: {
    thread: ThreadRow;
    active: boolean;
    onSelect: () => void;
}) {
    const unread = thread.unreadCount > 0;

    return (
        <button
            type="button"
            onClick={onSelect}
            aria-current={active ? 'true' : undefined}
            className={cn(
                'flex w-full items-start gap-3 border-b border-subtle px-4 py-3 text-left transition-colors duration-fast',
                // Unread wins over hover but loses to selected: the row you are
                // reading should look selected even while it is still unread.
                active ? 'bg-brand-subtle' : unread ? 'bg-brand-subtle/60 hover:bg-brand-subtle' : 'hover:bg-row-hover',
            )}
        >
            {/* Publinza does not store its own site marks, so this is a glyph of
                the favicon's size — nothing shifts if one ever lands. Pointing
                it at a third-party favicon service would ship every domain the
                advertiser buys on to that service on each page load. */}
            <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-[3px] bg-sunken text-ink-500">
                <GlobeIcon size={13} />
            </span>

            <span className="min-w-0 flex-1">
                <span className="flex items-baseline gap-2">
                    <span className="min-w-0 flex-1 truncate font-sora text-base font-medium text-ink-900">
                        {thread.domain}
                    </span>
                    <span className="num shrink-0 text-xs text-ink-500">{relativeTime(thread.lastMessageAt)}</span>
                </span>

                <span className="mt-0.5 flex items-center gap-2">
                    <span className="min-w-0 flex-1 truncate text-sm text-ink-700">{thread.subject}</span>

                    {thread.post !== null && (
                        <Badge
                            status={thread.post.badge}
                            label={thread.post.statusLabel}
                            className="shrink-0"
                        />
                    )}
                </span>

                <span className="mt-1 flex items-center gap-2">
                    <span className="min-w-0 flex-1 truncate text-sm text-ink-500">{thread.excerpt}</span>

                    {unread ? (
                        <span className="num shrink-0 rounded-pill bg-brand px-1.5 text-xs font-medium text-white">
                            {thread.unreadCount > 99 ? '99+' : thread.unreadCount}
                        </span>
                    ) : (
                        <StatusDot status={thread.status} />
                    )}
                </span>
            </span>
        </button>
    );
}

/** Open or closed, as a dot. Labelled, because colour alone is not a state. */
function StatusDot({ status }: { status: 'open' | 'closed' }) {
    const open = status === 'open';

    return (
        <span
            role="img"
            aria-label={open ? 'Open' : 'Closed'}
            title={open ? 'Open' : 'Closed'}
            className={cn('size-2 shrink-0 rounded-pill', open ? 'bg-success' : 'bg-ink-300')}
        />
    );
}
