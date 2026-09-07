import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, BellIcon, Button, Drawer, SkeletonText, Tabs } from '@shared/ui';
import { requestPushPermission } from './useLiveNotifications';
import { NotificationRow } from './NotificationRow';
import { useNotificationCentre } from './useNotificationCentre';

interface Props {
    open: boolean;
    onClose: () => void;
    /** Re-reads the header badge after a local change. */
    onCountsChanged: () => void;
}

/**
 * The 420px notification drawer.
 *
 * Everything in it is grouped and collapsed server-side — see NotificationCentre
 * — because "yesterday" depends on the reader's timezone, which the server knows
 * and this component does not.
 */
export function NotificationsDrawer({ open, onClose, onCountsChanged }: Props) {
    const { filter, setFilter, data, loading, markRead, markAllRead } = useNotificationCentre(
        open,
        onCountsChanged,
    );

    const counts = data?.counts ?? { all: 0, unread: 0 };
    const empty = data !== null && data.groups.length === 0;

    /*
     * Offered here rather than on page load, and only to somebody who has
     * already switched browser push on. A permission prompt that appears before
     * anybody asked for anything is the one everybody denies, and a denial in
     * Chrome cannot be undone from the page.
     */
    const [permission, setPermission] = useState<NotificationPermission | 'unsupported'>(() =>
        typeof Notification === 'undefined' ? 'unsupported' : Notification.permission,
    );

    const askForPush = data?.pushWanted === true && permission === 'default';

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Notifications"
            className="max-w-notifications"
            footer={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <button
                        type="button"
                        onClick={markAllRead}
                        disabled={counts.unread === 0}
                        className="text-base text-brand underline disabled:cursor-not-allowed disabled:text-ink-500 disabled:no-underline"
                    >
                        Mark all read
                    </button>

                    <Link
                        href="/profile?tab=notifications"
                        onClick={onClose}
                        className="text-base text-ink-500 underline hover:text-ink-700"
                    >
                        Settings
                    </Link>
                </div>
            }
        >
            <div className="flex min-w-0 flex-col gap-4">
                {askForPush && (
                    <Alert tone="info" title="Turn on browser notifications">
                        <span className="block">
                            You have browser push switched on for some updates. Your browser needs to allow it
                            too.
                        </span>
                        <span className="mt-2 block">
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => void requestPushPermission().then(setPermission)}
                            >
                                Allow notifications
                            </Button>
                        </span>
                    </Alert>
                )}

                <Tabs
                    items={[
                        { id: 'all', label: 'All', count: counts.all },
                        { id: 'unread', label: 'Unread', count: counts.unread },
                    ]}
                    value={filter}
                    onChange={(next) => setFilter(next === 'unread' ? 'unread' : 'all')}
                />

                {data === null || (loading && empty) ? (
                    <div className="flex flex-col gap-6 pt-2">
                        <SkeletonText lines={3} />
                        <SkeletonText lines={3} />
                    </div>
                ) : empty ? (
                    <div className="flex flex-col items-center gap-3 py-14 text-center">
                        <span className="flex size-12 items-center justify-center rounded-pill bg-sunken text-ink-500">
                            <BellIcon size={20} />
                        </span>
                        <p className="text-base font-medium text-ink-900">You&apos;re all caught up.</p>
                        <p className="max-w-[26ch] text-sm text-ink-500">
                            {filter === 'unread'
                                ? 'Nothing unread. Switch to All for the history.'
                                : 'Anything that happens to your posts, orders or balance shows up here.'}
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-5">
                        {data.groups.map((bucket) => (
                            <section key={bucket.key}>
                                <h3 className="px-3 pb-1 text-xs font-medium uppercase tracking-wide text-ink-500">
                                    {bucket.label}
                                </h3>

                                <div className="flex flex-col">
                                    {bucket.items.map((entry) => (
                                        <NotificationRow
                                            key={entry.id}
                                            entry={entry}
                                            onRead={markRead}
                                            onNavigate={onClose}
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}

                        {data.hasMore && (
                            <Link
                                href="/notifications"
                                onClick={onClose}
                                className="px-3 text-base text-brand underline"
                            >
                                See everything
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </Drawer>
    );
}
