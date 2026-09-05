import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { money, number } from '@shared/lib/format';
import { Button, Card, CartIcon, FolderIcon, GlobeIcon, ListIcon, SparkleIcon, StatCard, WalletIcon } from '@shared/ui';
import type { DashboardMetrics, Granularity, Stat } from '@shared/types/dashboard';
import type { PostDraftCard } from '@shared/types/postWizard';
import { AppShell } from '../Layouts/AppShell';
import { DateRangeControl, type RangeSelection } from '../Components/dashboard/DateRangeControl';
import { EmptyRangeState, NoPostsState, NoProjectsState } from '../Components/dashboard/DashboardEmptyStates';
import {
    ChartSkeleton,
    RowsSkeleton,
    StatCardSkeleton,
    StatusSkeleton,
    TableSkeleton,
} from '../Components/dashboard/DashboardSkeletons';
import { ResumeDraftCard } from '../Components/post-wizard/ResumeDraftCard';
import { PostsByStatus } from '../Components/dashboard/PostsByStatus';
import { RecentPosts } from '../Components/dashboard/RecentPosts';
import { SpendPlacementsChart } from '../Components/dashboard/SpendPlacementsChart';
import { TopWebsites } from '../Components/dashboard/TopWebsites';
import { UpcomingDeadlines } from '../Components/dashboard/UpcomingDeadlines';

interface DashboardProps {
    firstName: string;
    metrics: DashboardMetrics;
    /** An interrupted add-post wizard, if there is one to resume. */
    postDraft: PostDraftCard | null;
}

const STAT_ORDER: {
    key: keyof DashboardMetrics['stats'];
    label: string;
    icon: JSX.Element;
}[] = [
    { key: 'totalSpent', label: 'Total spent', icon: <CartIcon size={16} /> },
    { key: 'availableBalance', label: 'Available balance', icon: <WalletIcon size={16} /> },
    { key: 'frozenFunds', label: 'Frozen funds', icon: <WalletIcon size={16} /> },
    { key: 'activeProjects', label: 'Active projects', icon: <FolderIcon size={16} /> },
    { key: 'postsInProgress', label: 'Posts in progress', icon: <ListIcon size={16} /> },
    { key: 'liveLinks', label: 'Live links', icon: <GlobeIcon size={16} /> },
];

export default function Dashboard({ firstName, metrics: initial, postDraft }: DashboardProps) {
    const [metrics, setMetrics] = useState(initial);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [selection, setSelection] = useState<RangeSelection>({
        key: initial.range.key,
        from: initial.range.from,
        to: initial.range.to,
    });

    // The granularity the chart is showing is whatever the server last chose or
    // was last told — never a second copy of that truth kept in the client.
    const granularity: Granularity = metrics.granularity;

    // Each fetch carries a sequence number so a slow "Year" cannot land after a
    // fast "Last 7 days" and repaint the page with the wrong range.
    const sequence = useRef(0);

    /**
     * Fetching is driven by the handlers, not by an effect watching state.
     * An effect would also fire on mount, and the first payload is already in
     * the page — the dashboard would refetch everything it just rendered.
     *
     * `nextGranularity` of null means "you decide": on a range change the
     * server's own bucketing rule should win, so switching from 7 days to a
     * year does not ask for 365 daily bars.
     */
    function load(next: RangeSelection, nextGranularity: Granularity | null) {
        const id = ++sequence.current;
        setLoading(true);
        setFailed(false);

        const params = new URLSearchParams({ range: next.key });
        if (next.key === 'custom' && next.from && next.to) {
            params.set('from', next.from.slice(0, 10));
            params.set('to', next.to.slice(0, 10));
        }
        if (nextGranularity) params.set('granularity', nextGranularity);
        if (metrics.projectId !== null) params.set('project', String(metrics.projectId));

        void fetch(`/dashboard/metrics?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));

                return response.json() as Promise<DashboardMetrics>;
            })
            .then((payload: DashboardMetrics) => {
                if (id !== sequence.current) return;

                setMetrics(payload);
                setLoading(false);
            })
            .catch(() => {
                if (id !== sequence.current) return;

                // The previous figures stay on screen rather than being wiped:
                // stale-but-labelled beats an empty page.
                setFailed(true);
                setLoading(false);
            });
    }

    function changeRange(next: RangeSelection) {
        setSelection(next);
        load(next, null);
    }

    // A project scope change arrives as a full Inertia visit, so the inlined
    // payload is already correct — adopt it and drop any in-flight fetch.
    useEffect(() => {
        sequence.current += 1;
        setMetrics(initial);
        setSelection({ key: initial.range.key, from: initial.range.from, to: initial.range.to });
        setLoading(false);
        setFailed(false);
    }, [initial]);

    const resetRange = () => changeRange({ key: 'last_30' });

    const deltaLabel = `vs previous ${metrics.range.label.toLowerCase().replace('last ', '')}`;
    const hasRangeActivity =
        metrics.series.some((point) => point.placements > 0 || point.spendCents > 0) ||
        metrics.statusBreakdown.length > 0 ||
        metrics.topWebsites.length > 0;

    return (
        <AppShell title="Dashboard">
            <Head title="Dashboard" />

            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="font-sora text-xl font-semibold text-ink-900">Welcome back, {firstName}</h1>
                    <p className="mt-1 text-sm text-ink-500">
                        {metrics.range.label} · {formatSpan(metrics.range.from, metrics.range.to)}
                    </p>
                </div>

                {metrics.hasProjects && (
                    <DateRangeControl
                        value={selection}
                        activeLabel={formatSpan(metrics.range.from, metrics.range.to)}
                        onChange={changeRange}
                        disabled={loading}
                    />
                )}
            </header>

            {/* Above the metrics, because it is a thing to finish rather than a
                thing to read, and a card below six stat tiles is a card people
                scroll past. */}
            {postDraft !== null && (
                <div className="mt-6">
                    <ResumeDraftCard draft={postDraft} />
                </div>
            )}

            {failed && (
                <div className="mt-6 flex items-center justify-between gap-4 rounded-card border border-subtle bg-danger-bg px-4 py-3">
                    <p className="text-sm text-danger">
                        We could not load this range. The figures below are from your last successful load.
                    </p>
                    <Button variant="secondary" size="sm" onClick={() => load(selection, granularity)}>
                        Try again
                    </Button>
                </div>
            )}

            {/* Case 1: no projects at all. The entire body is one instruction. */}
            {!metrics.hasProjects ? (
                <div className="mt-6">
                    <NoProjectsState />
                </div>
            ) : (
                <>
                    {/* Row 1 — six stat cards. They stay visible in every other
                        empty state, reading zero, so the page keeps its shape. */}
                    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                        {STAT_ORDER.map(({ key, label, icon }) =>
                            loading ? (
                                <StatCardSkeleton key={key} />
                            ) : (
                                <StatCard
                                    key={key}
                                    label={label}
                                    icon={icon}
                                    value={formatStat(metrics.stats[key])}
                                    delta={metrics.stats[key].deltaPct ?? undefined}
                                    deltaPlaceholder={metrics.stats[key].deltaPct === null ? 'New' : undefined}
                                    deltaLabel={deltaLabel}
                                />
                            ),
                        )}
                    </div>

                    {/* Case 2: projects, but nothing ever ordered. */}
                    {!metrics.hasAnyPosts ? (
                        <div className="mt-6">
                            <NoPostsState />
                        </div>
                    ) : !loading && !hasRangeActivity ? (
                        /* Case 3: history exists, this range is just quiet. */
                        <div className="mt-6">
                            <EmptyRangeState onReset={resetRange} />
                        </div>
                    ) : (
                        <>
                            {/* Row 2 — 8/4 */}
                            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-12">
                                <Card
                                    title="Placements and spend"
                                    className="xl:col-span-8"
                                    action={
                                        <Link href="/posts" className="text-sm font-medium text-brand hover:underline">
                                            See all posts
                                        </Link>
                                    }
                                >
                                    {loading ? (
                                        <ChartSkeleton />
                                    ) : (
                                        <SpendPlacementsChart
                                            series={metrics.series}
                                            granularity={granularity}
                                            onGranularityChange={(next) => load(selection, next)}
                                        />
                                    )}
                                </Card>

                                <Card title="Posts by status" className="xl:col-span-4">
                                    {loading ? (
                                        <StatusSkeleton />
                                    ) : (
                                        <PostsByStatus slices={metrics.statusBreakdown} projectId={metrics.projectId} />
                                    )}
                                </Card>
                            </div>

                            {/* Row 3 — recent posts */}
                            <Card
                                title="Recent posts"
                                className="mt-6"
                                padded={false}
                                action={
                                    <Link href="/posts" className="text-sm font-medium text-brand hover:underline">
                                        See all posts
                                    </Link>
                                }
                            >
                                {loading ? (
                                    <div className="p-5">
                                        <TableSkeleton />
                                    </div>
                                ) : metrics.recentPosts.length === 0 ? (
                                    <p className="px-5 py-8 text-center text-sm text-ink-500">Nothing ordered yet.</p>
                                ) : (
                                    <div className="px-2 py-1">
                                        <RecentPosts posts={metrics.recentPosts} />
                                    </div>
                                )}
                            </Card>

                            {/* Row 4 — 6/6 */}
                            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <Card title="Top websites by spend">
                                    {loading ? (
                                        <RowsSkeleton />
                                    ) : metrics.topWebsites.length === 0 ? (
                                        <p className="py-8 text-center text-sm text-ink-500">
                                            Nothing published in this range yet.
                                        </p>
                                    ) : (
                                        <TopWebsites websites={metrics.topWebsites} />
                                    )}
                                </Card>

                                <Card
                                    title="Upcoming deadlines"
                                    action={
                                        <span className="inline-flex items-center gap-1.5 text-xs text-ink-500">
                                            <SparkleIcon size={13} />
                                            Next 7 days
                                        </span>
                                    }
                                >
                                    {loading ? (
                                        <RowsSkeleton />
                                    ) : metrics.deadlines.length === 0 ? (
                                        <p className="py-8 text-center text-sm text-ink-500">
                                            Nothing is due in the next seven days.
                                        </p>
                                    ) : (
                                        <UpcomingDeadlines deadlines={metrics.deadlines} />
                                    )}
                                </Card>
                            </div>
                        </>
                    )}
                </>
            )}

            <p className="sr-only" aria-live="polite">
                {loading ? 'Loading dashboard figures' : `Showing ${metrics.range.label}`}
            </p>
        </AppShell>
    );
}

function formatStat(stat: Stat): string {
    return stat.format === 'money' ? money(stat.value) : number(stat.value);
}

function formatSpan(from: string, to: string): string {
    const format = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' });

    return `${format.format(new Date(from))} – ${format.format(new Date(to))}`;
}
