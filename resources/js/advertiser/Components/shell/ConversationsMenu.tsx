import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { ChatIcon, GlobeIcon, useDismiss } from '@shared/ui';
import { relativeTime } from '@shared/lib/format';
import type { ShellConversation } from '@shared/types/shell';
import { HeaderButton } from './HeaderButton';

export function ConversationsMenu({ conversations, unread }: { conversations: ShellConversation[]; unread: number }) {
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    return (
        <div ref={ref} className="relative">
            <HeaderButton
                label="Conversations"
                count={unread}
                expanded={open}
                onClick={() => setOpen((v) => !v)}
                icon={<ChatIcon size={18} />}
            />

            {open && (
                <div className="absolute right-0 z-50 mt-1 w-80 animate-scale-in overflow-hidden rounded-card border border-subtle bg-card shadow-card">
                    {conversations.length === 0 ? (
                        <p className="px-4 py-8 text-center text-base text-ink-500">
                            No conversations yet. Ask a question from any site in the catalog.
                        </p>
                    ) : (
                        <ul>
                            {conversations.map((conversation) => (
                                <li key={conversation.id}>
                                    <Link
                                        href={`/conversations?thread=${conversation.id}`}
                                        onClick={() => setOpen(false)}
                                        className="flex items-start gap-3 border-b border-subtle px-4 py-3 transition-colors duration-fast hover:bg-sunken"
                                    >
                                        {/* Publinza stores no site marks, so the
                                            fallback is the common case: a glyph
                                            of the favicon's size, so nothing
                                            shifts if one ever lands. */}
                                        {conversation.favicon ? (
                                            <img
                                                src={conversation.favicon}
                                                alt=""
                                                width={16}
                                                height={16}
                                                loading="lazy"
                                                className="mt-0.5 size-4 shrink-0 rounded-[3px] bg-sunken"
                                            />
                                        ) : (
                                            <span className="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-[3px] bg-sunken text-ink-500">
                                                <GlobeIcon size={11} />
                                            </span>
                                        )}

                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center gap-2">
                                                <span className="truncate text-base font-medium text-ink-900">
                                                    {conversation.domain}
                                                </span>
                                                <span className="shrink-0 text-xs text-ink-500">
                                                    {relativeTime(conversation.at)}
                                                </span>
                                            </span>
                                            {/* One line, always — a wrapping excerpt turns the
                                                menu into a wall of text. */}
                                            <span className="mt-0.5 block truncate text-sm text-ink-500">
                                                {conversation.excerpt}
                                            </span>
                                        </span>

                                        {conversation.unread && (
                                            <span
                                                aria-label="Unread"
                                                className="mt-1.5 size-1.5 shrink-0 rounded-pill bg-brand"
                                            />
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="p-2">
                        <Link
                            href="/conversations"
                            onClick={() => setOpen(false)}
                            className="block rounded-button py-2 text-center text-base text-brand transition-colors duration-fast hover:bg-sunken"
                        >
                            Open all conversations
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
