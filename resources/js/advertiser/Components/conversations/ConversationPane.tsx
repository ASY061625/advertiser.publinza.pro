import { Link, router } from '@inertiajs/react';
import {
    ArchiveIcon,
    Badge,
    BellOffIcon,
    Dropdown,
    ExternalLinkIcon,
    MailIcon,
    MoreIcon,
    PanelLeftIcon,
} from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type {
    Availability,
    CannedResponse,
    ConversationMessage,
    Thread,
} from '@shared/types/conversations';
import { Composer } from './Composer';
import { MessageList } from './MessageList';

interface Props {
    thread: Thread;
    messages: ConversationMessage[];
    availability: Availability;
    canned: CannedResponse[];
    typing: string | null;
    contextOpen: boolean;
    hasContext: boolean;
    onToggleContext: () => void;
    onOpenContextDrawer: () => void;
    onBack: () => void;
    onSend: (body: string, files: File[]) => void;
    onRetry: (message: ConversationMessage) => void;
}

export function ConversationPane({
    thread,
    messages,
    availability,
    canned,
    typing,
    contextOpen,
    hasContext,
    onToggleContext,
    onOpenContextDrawer,
    onBack,
    onSend,
    onRetry,
}: Props) {
    const closed = thread.status === 'closed';

    return (
        <section className="flex h-full min-h-0 min-w-0 flex-1 flex-col bg-canvas">
            <Header
                thread={thread}
                contextOpen={contextOpen}
                hasContext={hasContext}
                onToggleContext={onToggleContext}
                onOpenContextDrawer={onOpenContextDrawer}
                onBack={onBack}
            />

            <MessageList messages={messages} typing={typing} onRetry={onRetry} />

            {closed ? (
                <div className="flex flex-wrap items-center justify-center gap-3 border-t border-subtle bg-card px-4 py-4 text-base text-ink-500">
                    This conversation is closed.
                    <button
                        type="button"
                        onClick={() =>
                            router.post(
                                `/conversations/${thread.id}/status`,
                                { status: 'open' },
                                { preserveScroll: true },
                            )
                        }
                        className="font-medium text-brand hover:underline"
                    >
                        Reopen it
                    </button>
                </div>
            ) : (
                <Composer availability={availability} canned={canned} disabled={false} onSend={onSend} />
            )}
        </section>
    );
}

/**
 * Sticky, and it carries the two links the conversation keeps needing: the
 * site, and the placement it is about.
 *
 * Scrolling back through forty messages to remember which post "the second one"
 * meant is the thing this header exists to prevent.
 */
function Header({
    thread,
    contextOpen,
    hasContext,
    onToggleContext,
    onOpenContextDrawer,
    onBack,
}: {
    thread: Thread;
    contextOpen: boolean;
    hasContext: boolean;
    onToggleContext: () => void;
    onOpenContextDrawer: () => void;
    onBack: () => void;
}) {
    return (
        <header className="sticky top-0 z-20 flex items-start gap-3 border-b border-subtle bg-card px-4 py-3 lg:px-6">
            {/* Below lg the list and the conversation are one column, so the
                conversation needs a way back to the list. */}
            <button
                type="button"
                onClick={onBack}
                aria-label="Back to conversations"
                className="-ml-1 flex size-9 shrink-0 items-center justify-center rounded-button text-ink-500 hover:bg-sunken lg:hidden"
            >
                <PanelLeftIcon size={18} />
            </button>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    {thread.websiteSlug === null ? (
                        <span className="font-sora text-base font-semibold text-ink-900">{thread.domain}</span>
                    ) : (
                        <Link
                            href={`/catalog/website/${thread.websiteSlug}`}
                            className="flex items-center gap-1 font-sora text-base font-semibold text-ink-900 hover:text-brand"
                        >
                            {thread.domain}
                            <ExternalLinkIcon size={13} className="text-ink-500" />
                        </Link>
                    )}

                    {thread.muted && (
                        <span
                            title="Notifications muted"
                            className="flex items-center gap-1 rounded-pill bg-sunken px-2 py-0.5 text-xs text-ink-500"
                        >
                            <BellOffIcon size={12} />
                            Muted
                        </span>
                    )}

                    {thread.status === 'closed' && (
                        <span className="rounded-pill bg-sunken px-2 py-0.5 text-xs text-ink-500">Closed</span>
                    )}
                </div>

                <p className="mt-0.5 truncate text-sm text-ink-500">{thread.subject}</p>

                {thread.post !== null && (
                    <Link
                        href={`/posts?post=${thread.post.id}`}
                        className="mt-1.5 inline-flex items-center gap-2 rounded-pill bg-sunken py-1 pl-2 pr-2.5 text-sm text-ink-700 transition-colors duration-fast hover:bg-row-hover"
                    >
                        <span className="num text-ink-500">Post #{thread.post.id}</span>
                        <Badge status={thread.post.badge} label={thread.post.statusLabel} />
                    </Link>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-1">
                {/* Two buttons, one visible at a time, because the panel has
                    two presentations: a third column on a wide screen, and a
                    drawer on a phone where there is no room for one. Same
                    pattern as the shell's own sidebar. */}
                {hasContext && (
                    <>
                        <button
                            type="button"
                            onClick={onToggleContext}
                            aria-pressed={contextOpen}
                            aria-label={contextOpen ? 'Hide details' : 'Show details'}
                            title={contextOpen ? 'Hide details' : 'Show details'}
                            className={cn(
                                'hidden size-9 items-center justify-center rounded-button transition-colors duration-fast lg:flex',
                                contextOpen ? 'bg-brand-subtle text-brand' : 'text-ink-500 hover:bg-sunken',
                            )}
                        >
                            <PanelLeftIcon size={18} className="rotate-180" />
                        </button>

                        <button
                            type="button"
                            onClick={onOpenContextDrawer}
                            aria-label="Show details"
                            title="Show details"
                            className="flex size-9 items-center justify-center rounded-button text-ink-500 transition-colors duration-fast hover:bg-sunken lg:hidden"
                        >
                            <PanelLeftIcon size={18} className="rotate-180" />
                        </button>
                    </>
                )}

                <Dropdown
                    align="end"
                    trigger={
                        <span
                            role="button"
                            aria-label="Conversation actions"
                            className="flex size-9 items-center justify-center rounded-button text-ink-500 hover:bg-sunken"
                        >
                            <MoreIcon size={18} />
                        </span>
                    }
                    items={[
                        {
                            id: 'unread',
                            label: 'Mark as unread',
                            icon: <MailIcon size={14} />,
                            onSelect: () => router.post(`/conversations/${thread.id}/unread`),
                        },
                        {
                            id: 'mute',
                            label: thread.muted ? 'Unmute notifications' : 'Mute notifications',
                            icon: <BellOffIcon size={14} />,
                            onSelect: () =>
                                router.post(
                                    `/conversations/${thread.id}/mute`,
                                    { muted: !thread.muted },
                                    { preserveScroll: true },
                                ),
                        },
                        {
                            id: 'status',
                            label: thread.status === 'closed' ? 'Reopen conversation' : 'Close conversation',
                            icon: <ArchiveIcon size={14} />,
                            onSelect: () =>
                                router.post(
                                    `/conversations/${thread.id}/status`,
                                    { status: thread.status === 'closed' ? 'open' : 'closed' },
                                    { preserveScroll: true },
                                ),
                        },
                    ]}
                />
            </div>
        </header>
    );
}
