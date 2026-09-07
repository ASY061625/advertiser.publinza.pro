import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { AppShell } from '../../Layouts/AppShell';
import { BellIcon, Button, EmptyState, Tabs } from '@shared/ui';
import type { AdvertiserSharedProps } from '@shared/types';
import type { NotificationCentreData } from '@shared/types/notifications';
import { NotificationRow } from '../../Components/notifications/NotificationRow';

interface Props {
    filter: 'all' | 'unread';
    centre: NotificationCentreData;
}

/**
 * The whole list, as a page.
 *
 * Every notification email links here, because a link from an email has to work
 * for somebody who is not signed in yet: after the login redirect they land on
 * a page they can read, not on a drawer that needs a click to open.
 *
 * Reads here go through Inertia rather than the drawer's fetch layer — the page
 * is server-rendered and a partial reload brings the counts, the shell badge and
 * the list back in step in one round trip.
 */
export default function NotificationsIndex({ filter, centre }: Props) {
    const page = usePage<AdvertiserSharedProps>();
    const [readLocally, setReadLocally] = useState<Set<string>>(new Set());

    const markRead = useCallback((ids: string[]) => {
        setReadLocally((current) => new Set([...current, ...ids]));

        router.post(
            ids.length === 1 ? `/notifications/${ids[0]}/read` : '/notifications/read-many',
            ids.length === 1 ? {} : { ids },
            {
                preserveScroll: true,
                preserveState: true,
                // The shell badge lives on a shared prop, and a partial reload
                // filters those too — naming it is what stops the bell going
                // stale while the row underneath it goes grey.
                only: ['centre', 'shell'],
            },
        );
    }, []);

    // Painted over the server's answer, so a row greys out on click rather than
    // waiting for the round trip.
    const groups = centre.groups.map((bucket) => ({
        ...bucket,
        items: bucket.items.map((entry) =>
            entry.kind === 'item'
                ? { ...entry, unread: entry.unread && !readLocally.has(entry.id) }
                : {
                      ...entry,
                      items: entry.items.map((item) => ({
                          ...item,
                          unread: item.unread && !readLocally.has(item.id),
                      })),
                      unreadCount: entry.items.filter(
                          (item) => item.unread && !readLocally.has(item.id),
                      ).length,
                  },
        ),
    }));

    const unread = Math.max(0, centre.counts.unread - readLocally.size);

    return (
        <AppShell title="Notifications" crumbs={[{ label: 'Notifications' }]}>
            <Head title="Notifications" />

            <div className="flex min-w-0 max-w-2xl flex-col gap-5">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-sora text-xl font-semibold text-ink-900">Notifications</h1>
                        <p className="mt-1 text-sm text-ink-500">
                            {page.props.auth.user?.displayName ?? 'You'} · everything we have told you.
                        </p>
                    </div>

                    <Button
                        variant="secondary"
                        disabled={unread === 0}
                        onClick={() =>
                            router.post('/notifications/mark-all', {}, { preserveScroll: true })
                        }
                    >
                        Mark all read
                    </Button>
                </header>

                <Tabs
                    items={[
                        { id: 'all', label: 'All', count: centre.counts.all },
                        { id: 'unread', label: 'Unread', count: unread },
                    ]}
                    value={filter}
                    onChange={(next) =>
                        router.get(
                            '/notifications',
                            next === 'unread' ? { filter: 'unread' } : {},
                            { preserveScroll: true, preserveState: false, replace: true },
                        )
                    }
                />

                {groups.length === 0 ? (
                    <EmptyState
                        illustration={<BellIcon size={22} />}
                        direction="You're all caught up."
                        body={
                            filter === 'unread'
                                ? 'Nothing unread. Switch to All for the history.'
                                : 'Anything that happens to your posts, orders or balance shows up here.'
                        }
                    />
                ) : (
                    <div className="flex flex-col gap-6">
                        {groups.map((bucket) => (
                            <section key={bucket.key} className="card p-2">
                                <h2 className="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-ink-500">
                                    {bucket.label}
                                </h2>

                                <div className="flex flex-col">
                                    {bucket.items.map((entry) => (
                                        <NotificationRow key={entry.id} entry={entry} onRead={markRead} />
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AppShell>
    );
}
