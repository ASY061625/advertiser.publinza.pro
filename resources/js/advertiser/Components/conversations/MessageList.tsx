import { useEffect, useRef } from 'react';
import { Avatar, CheckDoubleIcon, CheckIcon, InfoIcon, WarningIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { dayLabel, time } from '@shared/lib/format';
import type { ConversationMessage } from '@shared/types/conversations';
import { Attachments } from './Attachments';

interface Props {
    messages: ConversationMessage[];
    typing: string | null;
    onRetry: (message: ConversationMessage) => void;
}

/** Two messages this close together from one sender are one utterance. */
const GROUP_WINDOW_MS = 5 * 60_000;

export function MessageList({ messages, typing, onRetry }: Props) {
    const endRef = useRef<HTMLDivElement>(null);
    const count = messages.length;

    // Scroll to the newest on open and on every arrival. `auto` on the first
    // paint and smooth after: animating the initial jump from the top of a
    // hundred messages is a second of scenery nobody asked to watch.
    const first = useRef(true);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: first.current ? 'auto' : 'smooth', block: 'end' });
        first.current = false;
    }, [count, typing]);

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-4 py-4 lg:px-6">
            {/* Each day is its own section, and that is what makes the sticky
                separator work. Sticking is scoped to an element's parent, so
                sibling separators sharing one parent all pin at top-0 and pile
                up on each other — three days' headings stacked in one place
                while the messages under them scroll past. Wrapped per day, each
                heading is pushed out by the end of its own group, which is what
                a date separator is supposed to do. */}
            {groupByDay(messages).map(([day, dayMessages]) => (
                <section key={day} className="flex flex-col gap-1">
                    <div className="sticky top-0 z-10 -mx-4 mb-1 mt-3 px-4 py-1 lg:-mx-6 lg:px-6">
                        <p className="mx-auto w-fit rounded-pill bg-sunken px-3 py-1 text-xs font-medium text-ink-500 shadow-card">
                            {dayLabel(day)}
                        </p>
                    </div>

                    {dayMessages.map((message, index) => (
                        <Row
                            key={message.clientToken ?? message.id}
                            message={message}
                            grouped={groupsWith(dayMessages[index - 1], message)}
                            onRetry={onRetry}
                        />
                    ))}
                </section>
            ))}

            {typing !== null && <TypingIndicator name={typing} />}

            <div ref={endRef} />
        </div>
    );
}

function Row({
    message,
    grouped,
    onRetry,
}: {
    message: ConversationMessage;
    grouped: boolean;
    onRetry: (message: ConversationMessage) => void;
}) {
    if (message.senderType === 'system') {
        return <SystemLine message={message} />;
    }

    const mine = message.senderType === 'user';

    return (
        <div className={cn('flex w-full gap-2', grouped ? 'mt-0.5' : 'mt-3', mine ? 'justify-end' : 'justify-start')}>
            {!mine &&
                // The avatar is drawn once per group and reserved as blank space
                // after that, so the bubbles below it stay on the same left edge
                // instead of stepping sideways.
                (grouped ? (
                    <span className="size-8 shrink-0" aria-hidden="true" />
                ) : (
                    <Avatar name="Publinza" size="md" className="mt-5 shrink-0" />
                ))}

            <div className={cn('flex min-w-0 max-w-[min(560px,80%)] flex-col', mine ? 'items-end' : 'items-start')}>
                {!grouped && (
                    <p className="mb-1 flex items-baseline gap-2 px-1 text-xs text-ink-500">
                        <span className="font-medium text-ink-700">{message.senderName}</span>
                        {message.createdAt !== null && <span className="num">{time(message.createdAt)}</span>}
                    </p>
                )}

                <div
                    className={cn(
                        'w-full rounded-card px-3.5 py-2.5 text-base',
                        mine
                            ? 'bg-brand-subtle text-ink-900'
                            : 'border border-subtle bg-card text-ink-900 shadow-card',
                        message.failed === true && 'opacity-60',
                    )}
                >
                    {message.body.trim() !== '' && (
                        <p className="whitespace-pre-wrap break-words">{message.body}</p>
                    )}

                    <Attachments files={message.attachments} mine={mine} />
                </div>

                {mine && <Receipt message={message} onRetry={onRetry} />}
            </div>
        </div>
    );
}

/**
 * Sending → delivered → read, and the one case that needs a button.
 *
 * A failed send that only greys out is a message the writer believes they sent.
 * The retry is the affordance that makes optimistic sending honest.
 */
function Receipt({
    message,
    onRetry,
}: {
    message: ConversationMessage;
    onRetry: (message: ConversationMessage) => void;
}) {
    if (message.failed === true) {
        return (
            <p className="mt-1 flex items-center gap-1.5 px-1 text-xs text-danger">
                <WarningIcon size={12} />
                Not sent.
                <button
                    type="button"
                    onClick={() => onRetry(message)}
                    className="font-medium underline underline-offset-2 hover:text-danger-pressed"
                >
                    Retry
                </button>
            </p>
        );
    }

    if (!message.delivered) {
        return <p className="mt-1 px-1 text-xs text-ink-500">Sending…</p>;
    }

    const read = message.readAt !== null;

    return (
        <p className="mt-1 flex items-center gap-1 px-1 text-xs text-ink-500">
            {read ? (
                <CheckDoubleIcon size={13} className="text-brand" />
            ) : (
                <CheckIcon size={13} />
            )}
            {read ? 'Read' : 'Delivered'}
        </p>
    );
}

/** An automated notice. Centred, quiet, and never mistaken for a person. */
function SystemLine({ message }: { message: ConversationMessage }) {
    return (
        <p className="my-2 flex items-center justify-center gap-2 text-center text-sm text-ink-500">
            <InfoIcon size={14} className="shrink-0" />
            <span>{message.body}</span>
            {message.createdAt !== null && <span className="num text-xs">{time(message.createdAt)}</span>}
        </p>
    );
}

function TypingIndicator({ name }: { name: string }) {
    return (
        <div className="mt-3 flex items-end gap-2">
            <Avatar name={name} size="md" className="shrink-0" />

            <p
                aria-live="polite"
                className="flex items-center gap-1 rounded-card border border-subtle bg-card px-3.5 py-3 shadow-card"
            >
                <span className="sr-only">{name} is typing</span>
                {[0, 1, 2].map((dot) => (
                    <span
                        key={dot}
                        aria-hidden="true"
                        className="size-1.5 animate-pulse rounded-pill bg-ink-300"
                        style={{ animationDelay: `${dot * 150}ms` }}
                    />
                ))}
            </p>
        </div>
    );
}

/**
 * @returns the messages bucketed by calendar day, in order.
 */
function groupByDay(messages: ConversationMessage[]): [string, ConversationMessage[]][] {
    const days = new Map<string, ConversationMessage[]>();

    for (const message of messages) {
        // A message still in flight has no server timestamp yet; it belongs
        // under today's heading, which is where the writer is looking.
        const iso = message.createdAt ?? new Date().toISOString();
        const key = iso.slice(0, 10);

        days.set(key, [...(days.get(key) ?? []), message]);
    }

    return [...days.entries()];
}

function groupsWith(previous: ConversationMessage | undefined, message: ConversationMessage): boolean {
    if (previous === undefined) return false;
    if (previous.senderType !== message.senderType) return false;
    if (previous.senderType === 'system' || message.senderType === 'system') return false;
    if (previous.createdAt === null || message.createdAt === null) return true;

    return new Date(message.createdAt).getTime() - new Date(previous.createdAt).getTime() < GROUP_WINDOW_MS;
}
