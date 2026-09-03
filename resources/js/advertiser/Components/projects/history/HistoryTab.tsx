import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button, Checkbox, DownloadIcon, EmptyState, Input, ListIcon, SearchIcon, Select } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { HistoryEvent, HistoryFamily, HistoryPayload } from '@shared/types/history';
import type { ProjectDetail } from '@shared/types/projects';
import { HistoryEntry } from './HistoryEntry';

interface Props {
    project: ProjectDetail;
    history: HistoryPayload;
}

const FAMILIES: { id: HistoryFamily; label: string }[] = [
    { id: 'project', label: 'Project' },
    { id: 'folder', label: 'Folders' },
    { id: 'post', label: 'Posts' },
    { id: 'money', label: 'Money' },
    { id: 'message', label: 'Messages' },
];

const ACTORS = [
    { value: '', label: 'Anyone' },
    { value: 'user', label: 'You' },
    { value: 'admin', label: 'Publinza team' },
    { value: 'system', label: 'System' },
];

/**
 * Everything that has happened to this project, newest first.
 *
 * Pages accumulate in component state rather than replacing each other: an
 * infinite scroll that re-renders from the server's latest page would lose
 * everything above it on every load.
 *
 * Which means the accumulation must not be reset just because the `history`
 * prop changed — every load-more changes it. It is reset when the *filters*
 * change, because that is a different log, and left alone otherwise.
 *
 * Each page is asked for by cursor rather than by number. The log is
 * append-only and read newest-first, so an event arriving mid-scroll would
 * shift every offset below it and repeat a row; a cursor names the row the
 * next page continues from, which nothing written above can move.
 */
export function HistoryTab({ project, history }: Props) {
    const [events, setEvents] = useState<HistoryEvent[]>(history.events);
    const [cursor, setCursor] = useState(history.nextCursor);
    const [hasMore, setHasMore] = useState(history.hasMore);
    const [loading, setLoading] = useState(false);
    const [jump, setJump] = useState('');
    const sentinel = useRef<HTMLDivElement>(null);

    const filters = history.filters;

    // A filter change is a new log, not more of the old one. Comparing the
    // filters themselves rather than the events is the whole point: the events
    // change on every page, and resetting on those would throw away the scroll
    // the reader just paid for.
    const filterKey = JSON.stringify(filters);
    const appliedKey = useRef(filterKey);

    useEffect(() => {
        if (appliedKey.current === filterKey) return;

        appliedKey.current = filterKey;
        setEvents(history.events);
        setCursor(history.nextCursor);
        setHasMore(history.hasMore);
        setJump('');
    }, [filterKey, history.events, history.nextCursor, history.hasMore]);

    const query = useCallback(
        (patch: Record<string, unknown>): RequestPayload => {
            const next: Record<string, unknown> = { tab: 'history', ...filters, ...patch };

            for (const [key, value] of Object.entries(next)) {
                const empty =
                    value === undefined ||
                    value === null ||
                    value === '' ||
                    (Array.isArray(value) && value.length === 0);

                if (empty) delete next[key];
            }

            return next as RequestPayload;
        },
        [filters],
    );

    const applyFilters = useCallback(
        (patch: Record<string, unknown>) => {
            // Without the cursor: changing a filter is a fresh read of the
            // log, and resuming it halfway down would start the reader in the
            // middle of a list they have not seen the top of.
            router.get(`/projects/${project.id}`, query({ ...patch, cursor: undefined }), {
                preserveState: true,
                preserveScroll: true,
                only: ['history'],
            });
        },
        [project.id, query],
    );

    const loadMore = useCallback(() => {
        if (loading || !hasMore || cursor === null) return;

        setLoading(true);

        router.get(`/projects/${project.id}`, query({ cursor }), {
            preserveState: true,
            preserveScroll: true,
            only: ['history'],
            onSuccess: (visit) => {
                const next = (visit.props as unknown as { history: HistoryPayload }).history;

                // Appended, and de-duplicated by id anyway: the cursor is what
                // stops a repeat, and this is the cheap check that says so if
                // it ever stops being true.
                setEvents((current) => {
                    const seen = new Set(current.map((event) => event.id));

                    return [...current, ...next.events.filter((event) => !seen.has(event.id))];
                });

                setCursor(next.nextCursor);
                setHasMore(next.hasMore);
            },
            onFinish: () => setLoading(false),
        });
    }, [cursor, hasMore, loading, project.id, query]);

    /**
     * Moves where the reading starts, without narrowing what is being read.
     *
     * A day already on screen is a scroll, not a request — and a day above the
     * loaded window is a jump backwards, so the list restarts from there
     * rather than appending an older page under a newer one.
     */
    const jumpToDate = useCallback(
        (date: string) => {
            setJump(date);

            if (date === '') return;

            const loaded = document.getElementById(dayId(date));

            if (loaded) {
                loaded.scrollIntoView({ block: 'start', behavior: 'smooth' });

                return;
            }

            setLoading(true);

            router.get(`/projects/${project.id}`, query({ cursor: date }), {
                preserveState: true,
                preserveScroll: true,
                only: ['history'],
                onSuccess: (visit) => {
                    const next = (visit.props as unknown as { history: HistoryPayload }).history;

                    setEvents(next.events);
                    setCursor(next.nextCursor);
                    setHasMore(next.hasMore);
                },
                onFinish: () => setLoading(false),
            });
        },
        [project.id, query],
    );

    useEffect(() => {
        const element = sentinel.current;
        if (!element || !hasMore) return;

        const observer = new IntersectionObserver((entries) => {
            if (entries[0]?.isIntersecting) loadMore();
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, [hasMore, loadMore]);

    const days = useMemo(() => groupByDay(events), [events]);

    if (!history.hasAnyHistory) {
        return (
            <EmptyState
                illustration={<ListIcon size={26} />}
                direction="Nothing has happened yet."
                body="Activity appears here as you create posts and update the project."
                action={
                    <Link href={`/catalog?project=${project.id}`}>
                        <Button size="lg">Find a website</Button>
                    </Link>
                }
            />
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-ink-500">History is permanent and cannot be edited.</p>

            <div className="flex flex-col gap-3 rounded-card border border-subtle bg-card p-3 shadow-card">
                <div className="flex flex-wrap items-end gap-2">
                    <div className="min-w-[220px] flex-1">
                        <Input
                            label="Search history"
                            hideLabel
                            type="search"
                            defaultValue={filters.q ?? ''}
                            placeholder="Search descriptions"
                            leadingIcon={<SearchIcon size={16} />}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') applyFilters({ q: event.currentTarget.value || undefined });
                            }}
                            onBlur={(event) => applyFilters({ q: event.target.value || undefined })}
                        />
                    </div>

                    <Select
                        label="Actor"
                        hideLabel
                        className="w-44"
                        value={filters.actor ?? ''}
                        onChange={(event) => applyFilters({ actor: event.target.value || undefined })}
                        options={ACTORS}
                    />

                    <Input
                        label="From"
                        hideLabel
                        type="date"
                        className="w-36"
                        value={filters.from ?? ''}
                        onChange={(event) => applyFilters({ from: event.target.value || undefined })}
                    />
                    <span className="pb-2.5 text-sm text-ink-500">to</span>
                    <Input
                        label="To"
                        hideLabel
                        type="date"
                        className="w-36"
                        value={filters.to ?? ''}
                        onChange={(event) => applyFilters({ to: event.target.value || undefined })}
                    />

                    <a href={`/projects/${project.id}/history/export?${new URLSearchParams(flatten(filters))}`}>
                        <Button variant="secondary">
                            <DownloadIcon size={14} />
                            Export history
                        </Button>
                    </a>
                </div>

                <fieldset className="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-subtle pt-3">
                    <legend className="sr-only">Event families</legend>

                    {FAMILIES.map((family) => {
                        const active = filters.families ?? [];
                        // No boxes ticked means everything, which is what an
                        // unfiltered log should be — so all of them read as on.
                        const checked = active.length === 0 || active.includes(family.id);

                        return (
                            <Checkbox
                                key={family.id}
                                label={family.label}
                                checked={checked}
                                onChange={() => {
                                    const current = active.length === 0 ? FAMILIES.map((f) => f.id) : active;
                                    const next = current.includes(family.id)
                                        ? current.filter((id) => id !== family.id)
                                        : [...current, family.id];

                                    applyFilters({
                                        families: next.length === FAMILIES.length ? undefined : next,
                                    });
                                }}
                            />
                        );
                    })}

                    <span className="num ml-auto text-sm text-ink-500">
                        {number(history.total)} {history.total === 1 ? 'entry' : 'entries'}
                    </span>
                </fieldset>
            </div>

            {events.length === 0 ? (
                <EmptyState
                    illustration={<SearchIcon size={22} />}
                    direction="No history matches these filters"
                    action={
                        <Button
                            variant="secondary"
                            onClick={() =>
                                applyFilters({
                                    families: undefined,
                                    actor: undefined,
                                    from: undefined,
                                    to: undefined,
                                    q: undefined,
                                })
                            }
                        >
                            Clear all filters
                        </Button>
                    }
                />
            ) : (
                <div className="rounded-card border border-subtle bg-card px-4 py-2 shadow-card">
                    <div className="flex items-center justify-end gap-2 border-b border-subtle py-2">
                        {/* The visible words are decorative: the input carries
                            the same text as its accessible name, and two real
                            labels on one control reads it out twice. */}
                        <span aria-hidden className="text-sm text-ink-500">
                            Jump to date
                        </span>
                        <Input
                            label="Jump to date"
                            hideLabel
                            type="date"
                            className="w-36"
                            value={jump}
                            onChange={(event) => jumpToDate(event.target.value)}
                        />
                    </div>

                    {days.map((day) => (
                        <section key={day.iso} id={dayId(day.iso)} aria-label={day.label}>
                            {/* Sticky under the app header, so the day you are
                                reading stays named while you scroll it. */}
                            <h3 className="sticky top-header z-10 -mx-4 bg-card px-4 py-2 font-sora text-sm font-semibold text-ink-700">
                                {day.label}
                            </h3>

                            <ol className="pt-1">
                                {day.events.map((event) => (
                                    <HistoryEntry key={event.id} projectId={project.id} event={event} />
                                ))}
                            </ol>
                        </section>
                    ))}

                    <div ref={sentinel} className="py-4 text-center text-sm text-ink-500">
                        {loading ? 'Loading…' : hasMore ? 'Scroll for more' : 'That is the whole history.'}
                    </div>
                </div>
            )}
        </div>
    );
}

/** Query params, with the array flattened the way the server reads it. */
function flatten(filters: HistoryPayload['filters']): [string, string][] {
    const out: [string, string][] = [];

    for (const [key, value] of Object.entries(filters)) {
        if (value === undefined || value === null || value === '') continue;

        if (Array.isArray(value)) for (const item of value) out.push([`${key}[]`, String(item)]);
        else out.push([key, String(value)]);
    }

    return out;
}

/**
 * "Today", "Yesterday", then the date — compared in the reader's own timezone,
 * because a log that calls this morning "yesterday" is worse than one with no
 * headings at all.
 */
function groupByDay(events: HistoryEvent[]): { iso: string; label: string; events: HistoryEvent[] }[] {
    const groups = new Map<string, HistoryEvent[]>();

    for (const event of events) {
        const key = localDay(new Date(event.occurredAt));

        groups.set(key, [...(groups.get(key) ?? []), event]);
    }

    const today = localDay(new Date());
    const yesterday = localDay(new Date(Date.now() - 86_400_000));

    return [...groups.entries()].map(([iso, dayEvents]) => ({
        iso,
        label:
            iso === today
                ? 'Today'
                : iso === yesterday
                  ? 'Yesterday'
                  : new Intl.DateTimeFormat('en-US', { dateStyle: 'long' }).format(new Date(`${iso}T12:00:00`)),
        events: dayEvents,
    }));
}

/** The anchor a jump scrolls to, when that day is already on screen. */
function dayId(iso: string): string {
    return `history-day-${iso}`;
}

function localDay(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
