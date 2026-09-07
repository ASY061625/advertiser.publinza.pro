import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Drawer, SkeletonText, SparkleIcon } from '@shared/ui';
import type { ChangelogEntry } from '@shared/types/notifications';
import { date } from '@shared/lib/format';
import { ChangelogChip } from './ChangelogChip';

/**
 * The 480px What's new drawer, newest ten.
 *
 * Entries are fetched on open rather than shipped with every page: the shell
 * only needs the unseen count to render the icon, and the bodies are long.
 */
export function WhatsNewDrawer({ open, onClose, onRead }: { open: boolean; onClose: () => void; onRead: () => void }) {
    const [entries, setEntries] = useState<ChangelogEntry[] | null>(null);

    /*
     * Held in a ref, and kept out of the effect's dependencies.
     *
     * The caller passes an inline arrow, so `onRead` is a new function on every
     * render — and calling it sets state up in the shell, which re-renders,
     * which produces another new function, which re-runs this effect. The
     * second fetch is the bug: opening the drawer marks everything seen, so it
     * comes back with every entry already read and paints over the dots the
     * first response was carrying.
     */
    const read = useRef(onRead);
    read.current = onRead;

    useEffect(() => {
        if (!open) return;

        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch('/shell/changelog', {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok || cancelled) return;

                const data = (await response.json()) as { entries: ChangelogEntry[] };
                setEntries(data.entries);

                // The request marked everything seen server-side; clear the
                // header's dot for this session.
                read.current();
            } catch {
                if (!cancelled) setEntries([]);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [open]);

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="What's new"
            description="Changes to Publinza, newest first"
            footer={
                <Link href="/whats-new" className="text-base text-brand underline" onClick={onClose}>
                    See the full changelog
                </Link>
            }
        >
            {entries === null ? (
                <div className="flex flex-col gap-6">
                    <SkeletonText lines={4} />
                    <SkeletonText lines={4} />
                </div>
            ) : entries.length === 0 ? (
                <div className="flex flex-col items-center gap-3 py-14 text-center">
                    <span className="flex size-12 items-center justify-center rounded-pill bg-sunken text-ink-500">
                        <SparkleIcon size={20} />
                    </span>
                    <p className="text-base text-ink-500">Nothing published yet. We will note changes here.</p>
                </div>
            ) : (
                <ol className="flex flex-col gap-7">
                    {entries.map((entry) => (
                        <li key={entry.id}>
                            <div className="flex flex-wrap items-center gap-2.5">
                                <ChangelogChip type={entry.type} label={entry.typeLabel} />

                                {entry.publishedAt && (
                                    <time dateTime={entry.publishedAt} className="text-sm text-ink-500">
                                        {date(entry.publishedAt)}
                                    </time>
                                )}

                                {entry.unread && (
                                    <span aria-label="Unread" className="size-1.5 rounded-pill bg-brand" />
                                )}
                            </div>

                            <h3 className="mt-2 font-sora text-md font-semibold text-ink-900">{entry.title}</h3>

                            {entry.imageUrl !== null && (
                                <img
                                    src={entry.imageUrl}
                                    alt=""
                                    loading="lazy"
                                    className="mt-3 w-full rounded-card border border-subtle"
                                />
                            )}

                            {/* Sanitised server-side against a fixed tag set —
                                see ChangelogHtml. */}
                            <div
                                className="prose-changelog mt-1.5"
                                dangerouslySetInnerHTML={{ __html: entry.body }}
                            />
                        </li>
                    ))}
                </ol>
            )}
        </Drawer>
    );
}
