import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronDownIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { relativeTime } from '@shared/lib/format';
import type { NotificationEntry, NotificationItem } from '@shared/types/notifications';
import { NotificationIcon } from './NotificationIcon';

interface Props {
    entry: NotificationEntry;
    /** Marks ids read and moves the badge. Resolves before navigation. */
    onRead: (ids: string[]) => void;
    onNavigate?: () => void;
}

export function NotificationRow({ entry, onRead, onNavigate }: Props) {
    if (entry.kind === 'group') {
        return <GroupRow group={entry} onRead={onRead} onNavigate={onNavigate} />;
    }

    return <ItemRow item={entry} onRead={onRead} onNavigate={onNavigate} />;
}

function ItemRow({
    item,
    onRead,
    onNavigate,
    nested = false,
}: {
    item: NotificationItem;
    onRead: (ids: string[]) => void;
    onNavigate?: () => void;
    nested?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={() => {
                if (item.unread) onRead([item.id]);
                onNavigate?.();
                router.visit(item.href);
            }}
            className={cn(
                'flex w-full items-start gap-3 rounded-card px-3 py-2.5 text-left transition-colors duration-fast',
                'hover:bg-sunken focus-visible:bg-sunken',
                nested && 'py-2',
            )}
        >
            <NotificationIcon icon={item.icon} tone={item.tone} size={nested ? 'sm' : 'md'} />

            <span className="min-w-0 flex-1">
                <span className="flex items-start gap-2">
                    <span
                        className={cn(
                            'min-w-0 flex-1 text-base',
                            item.unread ? 'font-medium text-ink-900' : 'text-ink-700',
                        )}
                    >
                        {item.title}
                    </span>

                    {/* The dot sits beside the time rather than on the icon:
                        the icon already carries the type's colour, and two
                        meanings on one mark is one nobody reads. */}
                    {item.unread && (
                        <span aria-label="Unread" className="mt-1.5 size-2 shrink-0 rounded-pill bg-brand" />
                    )}
                </span>

                <span className="mt-0.5 block text-sm text-ink-500">{item.body}</span>

                {item.at !== null && (
                    <time dateTime={item.at} className="mt-1 block text-xs text-ink-500">
                        {relativeTime(item.at)}
                    </time>
                )}
            </span>
        </button>
    );
}

/**
 * A collapsed run: "3 posts published", opening into the three.
 *
 * The summary is a disclosure, not a link. Clicking it cannot navigate — there
 * are three different destinations underneath — so it opens, and the items go
 * somewhere.
 */
function GroupRow({
    group,
    onRead,
    onNavigate,
}: {
    group: Extract<NotificationEntry, { kind: 'group' }>;
    onRead: (ids: string[]) => void;
    onNavigate?: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-card">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                className="flex w-full items-start gap-3 rounded-card px-3 py-2.5 text-left transition-colors duration-fast hover:bg-sunken"
            >
                <NotificationIcon icon={group.icon} tone={group.tone} />

                <span className="min-w-0 flex-1">
                    <span className="flex items-start gap-2">
                        <span
                            className={cn(
                                'min-w-0 flex-1 text-base',
                                group.unread ? 'font-medium text-ink-900' : 'text-ink-700',
                            )}
                        >
                            {group.title}
                        </span>

                        {group.unreadCount > 0 && (
                            <span
                                aria-label={`${group.unreadCount} unread`}
                                className="num mt-0.5 shrink-0 rounded-pill bg-brand-subtle px-1.5 text-xs font-medium text-brand"
                            >
                                {group.unreadCount}
                            </span>
                        )}
                    </span>

                    <span className="mt-0.5 flex items-center gap-1.5 text-sm text-ink-500">
                        {open ? 'Hide them' : 'Show them'}
                        <ChevronDownIcon
                            size={13}
                            className={cn('transition-transform duration-fast', open && 'rotate-180')}
                        />
                    </span>
                </span>
            </button>

            {open && (
                <div className="ml-6 flex flex-col border-l border-subtle pl-2">
                    {group.items.map((item) => (
                        <ItemRow key={item.id} item={item} onRead={onRead} onNavigate={onNavigate} nested />
                    ))}
                </div>
            )}
        </div>
    );
}
