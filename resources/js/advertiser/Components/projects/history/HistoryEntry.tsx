import { Link } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { money } from '@shared/lib/format';
import { ChevronDownIcon } from '@shared/ui';
import type { HistoryEvent, HistoryFamily } from '@shared/types/history';
import { diffWords } from './textDiff';

/**
 * The colour of an entry's icon says which family it belongs to.
 *
 * Never the only signal: every entry is a full sentence naming what happened,
 * and the family filter above names the families in words. The icon is a way
 * to skim a long log, not the way to read it.
 */
const FAMILY_COLOR: Record<HistoryFamily, string> = {
    project: 'var(--brand-blue)',
    folder: 'var(--ink-500)',
    money: 'var(--gold)',
    message: 'var(--teal)',
    // Overridden per status below — a post entry wears its own phase's colour.
    post: 'var(--ink-500)',
};

/** A post entry takes the colour of the status it moved to. */
const POST_COLOR: Record<string, string> = {
    draft: 'var(--ink-500)',
    new: 'var(--status-new-fg)',
    in_progress: 'var(--status-progress-fg)',
    content_review: 'var(--status-review-fg)',
    posted: 'var(--status-posted-fg)',
    completed: 'var(--status-posted-fg)',
    rejected: 'var(--danger)',
    cancelled: 'var(--ink-500)',
    refunded: 'var(--status-refunded-fg)',
};

export function familyColor(event: HistoryEvent): string {
    if (event.family === 'post') return POST_COLOR[event.eventKey] ?? FAMILY_COLOR.post;

    return FAMILY_COLOR[event.family];
}

export function HistoryEntry({ projectId, event }: { projectId: number; event: HistoryEvent }) {
    const [open, setOpen] = useState(false);
    const color = familyColor(event);

    return (
        <li className="relative flex gap-3 pb-5 last:pb-0">
            {/* The rail, drawn behind the icons rather than between them, so a
                variable-height entry cannot leave a gap in the line. */}
            <span aria-hidden="true" className="bg-ink-300/60 absolute bottom-0 left-4 top-8 w-px last:hidden" />

            <span
                aria-hidden="true"
                className="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-pill"
                style={{ backgroundColor: `color-mix(in oklab, ${color} 14%, white)`, color }}
            >
                <FamilyIcon family={event.family} />
            </span>

            <div className="min-w-0 flex-1 pt-1">
                <p className="text-sm text-ink-900">{event.description}</p>

                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-ink-500">
                    <span>{event.actor}</span>
                    <span aria-hidden="true">·</span>
                    {/* The relative time is what you read; the absolute time is
                        what you quote, so it is the title and the datetime. */}
                    <time dateTime={event.occurredAt} title={absolute(event.occurredAt)}>
                        {relative(event.occurredAt)}
                    </time>

                    {event.detail && (
                        <>
                            <span aria-hidden="true">·</span>
                            <button
                                type="button"
                                aria-expanded={open}
                                onClick={() => setOpen((value) => !value)}
                                className="inline-flex items-center gap-0.5 font-medium text-brand hover:underline"
                            >
                                {open ? 'Hide detail' : 'Detail'}
                                <ChevronDownIcon
                                    size={12}
                                    className={cn('transition-transform duration-fast', open && 'rotate-180')}
                                />
                            </button>
                        </>
                    )}
                </p>

                {open && event.detail && (
                    <div className="mt-2 rounded-card border border-subtle bg-sunken p-3">
                        <Detail projectId={projectId} detail={event.detail} />
                    </div>
                )}
            </div>
        </li>
    );
}

function Detail({ projectId, detail }: { projectId: number; detail: NonNullable<HistoryEvent['detail']> }) {
    if (detail.kind === 'post') {
        return (
            <div className="flex flex-col gap-2">
                <dl className="grid grid-cols-[minmax(0,7rem)_1fr] gap-x-3 gap-y-1 text-sm">
                    {(
                        [
                            ['Website', detail.domain],
                            ['Anchor', detail.anchorText],
                            ['Target URL', detail.targetUrl],
                            ['Price', money(detail.priceCents)],
                            ['Note', detail.note],
                        ] as [string, string | null][]
                    )
                        .filter(([, value]) => value !== null && value !== '')
                        .map(([label, value]) => (
                            <div key={label} className="contents">
                                <dt className="text-ink-500">{label}</dt>
                                <dd className="min-w-0 break-words text-ink-900">{value}</dd>
                            </div>
                        ))}
                </dl>

                <Link
                    href={`/projects/${projectId}?tab=posts&post=${detail.postId}`}
                    className="text-sm font-medium text-brand hover:underline"
                >
                    Open this post
                </Link>
            </div>
        );
    }

    if (detail.kind === 'text-diff') {
        return (
            <div className="flex flex-col gap-2">
                {detail.rows.map((row) => (
                    <div key={row.field}>
                        <p className="text-xs uppercase tracking-wide text-ink-500">{row.field}</p>
                        <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed">
                            {diffWords(row.from ?? '', row.to ?? '').map((part, index) => (
                                <span
                                    key={index}
                                    className={cn(
                                        part.kind === 'added' && 'bg-teal/10 text-teal',
                                        part.kind === 'removed' && 'bg-danger-bg text-danger line-through',
                                        part.kind === 'same' && 'text-ink-700',
                                    )}
                                >
                                    {part.value}
                                </span>
                            ))}
                        </p>
                    </div>
                ))}
            </div>
        );
    }

    return (
        <table className="w-full text-left text-sm">
            <thead>
                <tr>
                    {['Field', 'Was', 'Now'].map((header) => (
                        <th key={header} scope="col" className="pb-1 font-medium text-ink-500">
                            {header}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {detail.rows.map((row) => (
                    <tr key={row.field}>
                        <td className="py-0.5 pr-3 align-top text-ink-700">{row.field}</td>
                        <td className="py-0.5 pr-3 align-top text-ink-500 line-through">{row.from ?? '—'}</td>
                        <td className="py-0.5 align-top text-ink-900">{row.to ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function FamilyIcon({ family }: { family: HistoryFamily }) {
    const paths: Record<HistoryFamily, ReactNode> = {
        project: <path d="M3 7h18M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />,
        folder: <path d="M4 6h5l2 2h9a1 1 0 0 1 1 1v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1Z" />,
        post: (
            <>
                <path d="M12 20h9" />
                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
            </>
        ),
        money: (
            <>
                <path d="M12 2v20" />
                <path d="M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
            </>
        ),
        message: <path d="M21 12a8 8 0 0 1-8 8H7l-4 3V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8Z" />,
    };

    return (
        <svg
            width={15}
            height={15}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {paths[family]}
        </svg>
    );
}

/** "4 minutes ago". Intl does the wording; this only picks the unit. */
export function relative(iso: string): string {
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;

    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return formatter.format(Math.round(seconds / size), unit);
    }

    return 'just now';
}

export function absolute(iso: string): string {
    return new Intl.DateTimeFormat('en-US', { dateStyle: 'full', timeStyle: 'short' }).format(new Date(iso));
}
