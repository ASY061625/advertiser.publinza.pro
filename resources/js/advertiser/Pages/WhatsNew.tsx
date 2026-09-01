import { Head } from '@inertiajs/react';
import { AppShell } from '../Layouts/AppShell';
import { cn } from '@shared/lib/cn';
import type { Paginated } from '@shared/types';

interface Entry {
    id: number;
    title: string;
    body: string;
    category: string;
    published_at: string | null;
}

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

export default function WhatsNew({ entries }: { entries: Paginated<Entry> }) {
    return (
        <AppShell title="What's new" crumbs={[{ label: "What's new" }]}>
            <Head title="What's new" />

            {entries.data.length === 0 ? (
                <p className="text-base text-ink-500">Nothing published yet. We will note changes here.</p>
            ) : (
                <ol className="flex max-w-2xl flex-col gap-8">
                    {entries.data.map((entry) => (
                        <li key={entry.id} className="card p-6">
                            <div className="flex items-center gap-2.5">
                                <span
                                    className={cn(
                                        'rounded-pill px-2.5 py-1 text-xs font-medium',
                                        CHIPS[entry.category] ?? 'bg-sunken text-ink-500',
                                    )}
                                >
                                    {LABELS[entry.category] ?? 'Update'}
                                </span>
                                {entry.published_at && (
                                    <time dateTime={entry.published_at} className="text-sm text-ink-500">
                                        {new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(
                                            new Date(entry.published_at),
                                        )}
                                    </time>
                                )}
                            </div>

                            <h2 className="mt-3 font-sora text-md font-semibold text-ink-900">{entry.title}</h2>
                            <p className="mt-2 text-base leading-relaxed text-ink-700">{entry.body}</p>
                        </li>
                    ))}
                </ol>
            )}
        </AppShell>
    );
}
