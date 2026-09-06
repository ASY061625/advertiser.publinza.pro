import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button, ChatIcon, Drawer, EmptyState } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type { AdvertiserSharedProps } from '@shared/types';
import type { ConversationsPageProps } from '@shared/types/conversations';
import { AppShell } from '../../Layouts/AppShell';
import { ContextBody, ContextPanel, contextTitle } from '../../Components/conversations/ContextPanel';
import { ConversationPane } from '../../Components/conversations/ConversationPane';
import { NewConversationDialog } from '../../Components/conversations/NewConversationDialog';
import { ThreadList } from '../../Components/conversations/ThreadList';
import { useConversationRealtime } from '../../Components/conversations/useConversationRealtime';
import { useSendMessage } from '../../Components/conversations/useSendMessage';

const CONTEXT_KEY = 'publinza.conversations.context';

/**
 * Messaging with the Publinza team.
 *
 * Three panes over one URL: the thread list, the conversation, and an optional
 * context panel. One URL because a conversation is a place people link to —
 * from an email, from a post — and because switching threads is a partial
 * reload of two props rather than a page load that would lose the composer's
 * contents and the scroll position of both panes.
 */
export default function ConversationsIndex() {
    const page = usePage<AdvertiserSharedProps & ConversationsPageProps>();
    const { threads, thread, context, filters, counts, availability, cannedResponses, notifyReplies } =
        page.props;

    const [composing, setComposing] = useState(false);
    // Separate from contextOpen: below lg the panel is a drawer, and a drawer's
    // open state is transient rather than a remembered layout preference.
    const [contextDrawer, setContextDrawer] = useState(false);
    const [contextOpen, setContextOpen] = useState<boolean>(() => {
        try {
            return window.localStorage.getItem(CONTEXT_KEY) !== 'false';
        } catch {
            // Private mode or blocked storage. Open is the better default: the
            // panel is the reason the thread is filed against something.
            return true;
        }
    });

    const { typing } = useConversationRealtime(
        page.props.shell?.echo ?? null,
        page.props.auth.user?.id ?? null,
        thread?.id ?? null,
    );

    const { pending, send, retry, reconcile, reset } = useSendMessage(thread?.id ?? null);

    // The server's copy of a message replaces the optimistic one the moment it
    // arrives, matched on the client token.
    useEffect(() => reconcile(thread?.messages ?? []), [thread?.messages, reconcile]);

    // Switching threads drops anything still pending in the old one. It belongs
    // to that conversation, and showing it under this one would be a lie.
    useEffect(() => reset(), [thread?.id, reset]);

    const messages = useMemo(
        () => [...(thread?.messages ?? []), ...pending],
        [thread?.messages, pending],
    );

    const apply = useCallback(
        (changes: Record<string, unknown>) => {
            const next: Record<string, string | number | null> = {
                tab: filters.tab,
                q: filters.q,
                thread: filters.thread,
                ...(changes as Record<string, string | number | null>),
            };

            router.get('/conversations', clean(next), {
                preserveScroll: true,
                // The composer's draft and both panes' scroll positions survive
                // a thread switch only because the component is not remounted.
                preserveState: true,
                replace: true,
                // `shell` is in the list because a partial reload filters
                // shared props too: without it, opening a thread clears its
                // unread pill while the header badge above still counts it.
                only: ['threads', 'thread', 'context', 'filters', 'counts', 'shell'],
            });
        },
        [filters],
    );

    const toggleContext = useCallback(() => {
        setContextOpen((current) => {
            const next = !current;

            try {
                window.localStorage.setItem(CONTEXT_KEY, String(next));
            } catch {
                // Not fatal — the panel simply opens again next session.
            }

            return next;
        });
    }, []);

    const empty = threads.length === 0 && filters.q === null && filters.tab === 'all';

    return (
        <AppShell title="Conversations" crumbs={[{ label: 'Conversations' }]}>
            <Head title="Conversations" />

            {empty ? (
                <EmptyState
                    illustration={<ChatIcon size={26} />}
                    direction="No conversations yet"
                    body="Ask a question from any site in the catalog."
                    action={
                        <span className="flex flex-wrap items-center justify-center gap-2">
                            <a href="/catalog">
                                <Button size="lg">Browse the catalog</Button>
                            </a>
                            <Button size="lg" variant="secondary" onClick={() => setComposing(true)}>
                                New conversation
                            </Button>
                        </span>
                    }
                />
            ) : (
                /* Fills the viewport below the header, and scrolls inside its
                   own panes rather than scrolling the page: an inbox whose list
                   scrolls the whole document loses the composer off the bottom.
                   min-w-0 because these are flex children holding wide content. */
                <div className="flex h-[calc(100dvh-theme(spacing.header)-3rem)] min-w-0 overflow-hidden rounded-card border border-subtle bg-card shadow-card">
                    {/* The widths live on the flex children, not inside the
                        components: a `w-full` wrapper around a `w-[360px]`
                        child is still a flex item asking for all the space,
                        and it squeezes the conversation to nothing. */}
                    <div
                        className={cn(
                            'h-full min-h-0 w-full shrink-0 lg:w-[360px]',
                            thread !== null && 'hidden lg:block',
                        )}
                    >
                        <ThreadList
                            threads={threads}
                            filters={filters}
                            counts={counts}
                            activeId={thread?.id ?? null}
                            onSelect={(id) => apply({ thread: id })}
                            onFilter={apply}
                            onCompose={() => setComposing(true)}
                            notifyReplies={notifyReplies}
                        />
                    </div>

                    {thread === null ? (
                        <div className="hidden min-h-0 flex-1 items-center justify-center px-6 lg:flex">
                            <p className="max-w-[280px] text-center text-base text-ink-500">
                                Pick a conversation on the left to read it.
                            </p>
                        </div>
                    ) : (
                        <>
                            <ConversationPane
                                thread={thread}
                                messages={messages}
                                availability={availability}
                                canned={cannedResponses}
                                typing={typing}
                                contextOpen={contextOpen}
                                hasContext={context !== null}
                                onToggleContext={toggleContext}
                                onOpenContextDrawer={() => setContextDrawer(true)}
                                onBack={() => apply({ thread: null })}
                                onSend={send}
                                onRetry={retry}
                            />

                            {context !== null && contextOpen && (
                                <div className="hidden h-full min-h-0 w-[300px] shrink-0 lg:block">
                                    <ContextPanel context={context} />
                                </div>
                            )}
                        </>
                    )}
                </div>
            )}

            {/* The same facts as the column, for a screen with no room for a
                third one. Drawer already traps focus, locks body scroll and
                answers Escape. */}
            <Drawer
                open={contextDrawer && context !== null}
                onClose={() => setContextDrawer(false)}
                title={context === null ? '' : contextTitle(context)}
                className="max-w-[340px]"
            >
                {context !== null && <ContextBody context={context} />}
            </Drawer>

            <NewConversationDialog open={composing} onClose={() => setComposing(false)} />
        </AppShell>
    );
}

/** Drops nulls and empties, so the URL carries only what is actually set. */
function clean(query: Record<string, string | number | null>): Record<string, string | number> {
    return Object.fromEntries(
        Object.entries(query).filter((entry): entry is [string, string | number] => {
            const value = entry[1];

            return value !== null && value !== '';
        }),
    );
}
