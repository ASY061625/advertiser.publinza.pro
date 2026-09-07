import { useCallback, useEffect, useRef, useState } from 'react';
import { jsonHeaders } from '@shared/lib/csrf';
import type { NotificationCentreData } from '@shared/types/notifications';

async function post(url: string, body?: unknown): Promise<boolean> {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: body === undefined ? undefined : JSON.stringify(body),
        });

        return response.ok;
    } catch {
        return false;
    }
}

/**
 * The drawer's data.
 *
 * Fetched on open rather than shipped with every page: the shell only needs the
 * unread count to render the bell, and thirty notification bodies on every
 * navigation is thirty bodies nobody asked for.
 *
 * Reads are optimistic. Marking something read moves the dot immediately and
 * posts afterwards; a failed write means the dot comes back on the next open,
 * which is a better failure than a row that will not respond to a click.
 */
export function useNotificationCentre(open: boolean, onCountsChanged: () => void) {
    const [filter, setFilter] = useState<'all' | 'unread'>('all');
    const [data, setData] = useState<NotificationCentreData | null>(null);
    const [loading, setLoading] = useState(false);
    const request = useRef(0);

    const load = useCallback(
        async (next: 'all' | 'unread') => {
            // Every load carries a sequence number and only the newest one is
            // allowed to write. Switching tabs twice quickly would otherwise
            // let the slower first response land last and paint the wrong tab.
            const ticket = ++request.current;

            setLoading(true);

            try {
                const response = await fetch(`/notifications/list?filter=${next}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok || ticket !== request.current) return;

                setData((await response.json()) as NotificationCentreData);
            } catch {
                if (ticket === request.current) {
                    setData({ groups: [], counts: { all: 0, unread: 0 }, hasMore: false, pushWanted: false });
                }
            } finally {
                if (ticket === request.current) setLoading(false);
            }
        },
        [],
    );

    useEffect(() => {
        if (!open) return;

        void load(filter);
    }, [open, filter, load]);

    /** Paints the given ids as read, everywhere they appear. */
    const applyRead = useCallback((ids: string[]) => {
        setData((current) => {
            if (current === null) return current;

            const set = new Set(ids);
            let cleared = 0;

            const groups = current.groups.map((bucket) => ({
                ...bucket,
                items: bucket.items.map((entry) => {
                    if (entry.kind === 'item') {
                        if (!set.has(entry.id) || !entry.unread) return entry;
                        cleared++;

                        return { ...entry, unread: false };
                    }

                    const items = entry.items.map((item) => {
                        if (!set.has(item.id) || !item.unread) return item;
                        cleared++;

                        return { ...item, unread: false };
                    });

                    const unreadCount = items.filter((item) => item.unread).length;

                    return { ...entry, items, unreadCount, unread: unreadCount > 0 };
                }),
            }));

            return {
                ...current,
                groups,
                counts: { ...current.counts, unread: Math.max(0, current.counts.unread - cleared) },
            };
        });
    }, []);

    const markRead = useCallback(
        (ids: string[]) => {
            if (ids.length === 0) return;

            // The drawer repaints immediately; the header waits for the write.
            // Re-reading /shell/counts before the POST lands returns the count
            // from before it, and the badge is left one behind.
            applyRead(ids);

            void (ids.length === 1
                ? post(`/notifications/${ids[0]}/read`)
                : post('/notifications/read-many', { ids })
            ).then(onCountsChanged);
        },
        [applyRead, onCountsChanged],
    );

    const markAllRead = useCallback(() => {
        setData((current) =>
            current === null
                ? current
                : {
                      ...current,
                      groups: current.groups.map((bucket) => ({
                          ...bucket,
                          items: bucket.items.map((entry) =>
                              entry.kind === 'item'
                                  ? { ...entry, unread: false }
                                  : {
                                        ...entry,
                                        unread: false,
                                        unreadCount: 0,
                                        items: entry.items.map((item) => ({ ...item, unread: false })),
                                    },
                          ),
                      })),
                      counts: { ...current.counts, unread: 0 },
                  },
        );

        void post('/notifications/read-all').then((ok) => {
            // After the write, for the same reason as above.
            onCountsChanged();

            // The Unread tab is now empty by definition. Re-reading rather than
            // trusting the local edit means the empty state is the server's
            // answer, not a guess.
            if (ok) void load(filter);
        });
    }, [filter, load, onCountsChanged]);

    return { filter, setFilter, data, loading, markRead, markAllRead, reload: () => void load(filter) };
}
