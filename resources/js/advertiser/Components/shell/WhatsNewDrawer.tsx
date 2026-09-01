import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Drawer, SkeletonText } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type { ChangelogEntry } from '@shared/types/shell';

const CHIPS: Record<string, string> = {
    new: 'bg-status-new-bg text-status-new-fg',
    improvement: 'bg-status-posted-bg text-status-posted-fg',
    improved: 'bg-status-posted-bg text-status-posted-fg',
    fix: 'bg-status-progress-bg text-status-progress-fg',
    fixed: 'bg-status-progress-bg text-status-progress-fg',
};

const LABELS: Record<string, string> = {
    new: 'New',
    improvement: 'Improved',
    improved: 'Improved',
    fix: 'Fixed',
    fixed: 'Fixed',
};

/**
 * The 400px What's new drawer.
 *
 * Entries are fetched on open rather than shipped with every page: the shell
 * only needs the unread count to render, and the bodies are long.
 */
export function WhatsNewDrawer({ open, onClose, onRead }: { open: boolean; onClose: () => void; onRead: () => void }) {
    const [entries, setEntries] = useState<ChangelogEntry[] | null>(null);

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

                // The request marked everything read server-side; clear the dot.
                onRead();
            } catch {
                if (!cancelled) setEntries([]);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [open, onRead]);

    return (
        <Drawer open={open} onClose={onClose} title="What's new" description="Changes to Publinza, newest first">
            {entries === null ? (
                <div className="flex flex-col gap-6">
                    <SkeletonText lines={4} />
                    <SkeletonText lines={4} />
                </div>
            ) : entries.length === 0 ? (
                <p className="text-base text-ink-500">Nothing published yet. We will note changes here.</p>
            ) : (
                <ol className="flex flex-col gap-7">
                    {entries.map((entry) => (
                        <li key={entry.id}>
                            <div className="flex items-center gap-2.5">
                                <span
                                    className={cn(
                                        'rounded-pill px-2.5 py-1 text-xs font-medium',
                                        CHIPS[entry.category] ?? 'bg-sunken text-ink-500',
                                    )}
                                >
                                    {LABELS[entry.category] ?? 'Update'}
                                </span>
                                {entry.publishedAt && (
                                    <time dateTime={entry.publishedAt} className="text-sm text-ink-500">
                                        {new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(
                                            new Date(entry.publishedAt),
                                        )}
                                    </time>
                                )}
                                {entry.unread && (
                                    <span aria-label="Unread" className="size-1.5 rounded-pill bg-brand" />
                                )}
                            </div>

                            <h3 className="mt-2 font-sora text-md font-semibold text-ink-900">{entry.title}</h3>
                            <p className="mt-1.5 text-base leading-relaxed text-ink-700">{entry.body}</p>
                        </li>
                    ))}
                </ol>
            )}

            <div className="mt-8 border-t border-subtle pt-4">
                <Link href="/whats-new" className="text-base text-brand underline" onClick={onClose}>
                    See the full changelog
                </Link>
            </div>
        </Drawer>
    );
}
