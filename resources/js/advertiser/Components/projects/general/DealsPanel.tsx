import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { number } from '@shared/lib/format';
import type { ProjectPostMix } from '@shared/types/projects';

interface Props {
    projectId: number;
    mix: ProjectPostMix;
}

/**
 * How much work this project has, and where it is.
 *
 * Four tiles and a bar showing the same four numbers two ways: the tiles for
 * reading a figure, the bar for seeing the shape at a glance. Both are drawn
 * from one mix, so they cannot disagree.
 *
 * The segments are disjoint by status and a fifth carries the remainder, so the
 * widths sum to the total on the Tasks tile. A stacked bar that adds up to
 * something other than the number printed beside it is describing a population
 * that does not exist.
 *
 * Colour is never the only encoding: every segment is named and counted in the
 * legend below it, and in the tiles above it.
 */
const SEGMENTS: { key: Exclude<keyof ProjectPostMix, 'total'>; label: string; color: string }[] = [
    { key: 'new', label: 'New', color: 'var(--status-new-fg)' },
    { key: 'progress', label: 'In progress', color: 'var(--status-progress-fg)' },
    { key: 'posted', label: 'Posted', color: 'var(--status-posted-fg)' },
    { key: 'frozen', label: 'Frozen', color: 'var(--status-frozen-fg)' },
    { key: 'other', label: 'Other', color: 'var(--ink-300)' },
];

/**
 * Each tile is a link into the post grid, scoped to this project and filtered
 * to the status it counts — a number you can read is worth less than a number
 * you can open.
 *
 * The status values are the enum's, not the tile's label: "In progress" is two
 * statuses, and the filter has to carry both or the grid will show fewer posts
 * than the tile promised.
 */
const TILES: { key: keyof ProjectPostMix; label: string; statuses: string[]; icon: ReactNode }[] = [
    { key: 'total', label: 'Tasks', statuses: [], icon: <StackIcon /> },
    { key: 'new', label: 'New', statuses: ['new'], icon: <InboxIcon /> },
    { key: 'progress', label: 'In progress', statuses: ['in_progress', 'content_review'], icon: <PenIcon /> },
    { key: 'posted', label: 'Posted', statuses: ['posted', 'completed'], icon: <LinkIcon /> },
];

export function DealsPanel({ projectId, mix }: Props) {
    const visible = SEGMENTS.filter((segment) => mix[segment.key] > 0);

    return (
        <section aria-labelledby="deals-heading" className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <h2 id="deals-heading" className="font-sora text-md font-semibold text-ink-900">
                Deals
            </h2>

            <div className="mt-4 grid grid-cols-2 gap-3">
                {TILES.map((tile) => (
                    <Link
                        key={tile.key}
                        href={postsHref(projectId, tile.statuses)}
                        className={cn(
                            'group flex flex-col gap-2 rounded-card border border-subtle bg-canvas p-4',
                            'transition-colors duration-fast ease-standard',
                            'hover:border-brand hover:bg-brand-subtle',
                            'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                        )}
                    >
                        <span className="flex items-center gap-2 text-ink-500 group-hover:text-brand">
                            {tile.icon}
                            <span className="text-sm">{tile.label}</span>
                        </span>

                        <span className="num text-xl font-semibold text-ink-900">{number(mix[tile.key])}</span>
                    </Link>
                ))}
            </div>

            {mix.total === 0 ? (
                <div role="img" aria-label="No posts yet" className="mt-5 h-2 w-full rounded-pill bg-sunken" />
            ) : (
                <>
                    <div
                        role="img"
                        aria-label={visible.map((s) => `${s.label}: ${mix[s.key]}`).join(', ')}
                        className="mt-5 flex h-2 w-full gap-px overflow-hidden rounded-pill"
                    >
                        {visible.map((segment) => (
                            <span
                                key={segment.key}
                                aria-hidden="true"
                                style={{
                                    width: `${(mix[segment.key] / mix.total) * 100}%`,
                                    backgroundColor: segment.color,
                                }}
                                className="h-full first:rounded-l-pill last:rounded-r-pill"
                            />
                        ))}
                    </div>

                    <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5">
                        {visible.map((segment) => (
                            <li key={segment.key} className="flex items-center gap-1.5 text-sm text-ink-500">
                                <span
                                    aria-hidden="true"
                                    className="size-2 rounded-pill"
                                    style={{ backgroundColor: segment.color }}
                                />
                                {segment.label}
                                <span className="num font-medium text-ink-900">{number(mix[segment.key])}</span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}

function postsHref(projectId: number, statuses: string[]): string {
    const params = new URLSearchParams();
    params.append('projects[]', String(projectId));
    statuses.forEach((status) => params.append('statuses[]', status));

    return `/posts?${params.toString()}`;
}

function Icon({ children }: { children: ReactNode }) {
    return (
        <svg
            width={16}
            height={16}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {children}
        </svg>
    );
}

function StackIcon() {
    return (
        <Icon>
            <path d="m12 2 9 5-9 5-9-5 9-5Z" />
            <path d="m3 12 9 5 9-5" />
            <path d="m3 17 9 5 9-5" />
        </Icon>
    );
}

/** Deliberately not a snowflake: Finance already uses one for frozen funds. */
function InboxIcon() {
    return (
        <Icon>
            <path d="M22 12h-6l-2 3h-4l-2-3H2" />
            <path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.1Z" />
        </Icon>
    );
}

function PenIcon() {
    return (
        <Icon>
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </Icon>
    );
}

function LinkIcon() {
    return (
        <Icon>
            <path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7" />
            <path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7" />
        </Icon>
    );
}
