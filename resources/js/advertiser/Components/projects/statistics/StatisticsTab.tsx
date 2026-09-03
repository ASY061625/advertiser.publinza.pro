import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { Button, EmptyState, GlobeIcon, StatCard } from '@shared/ui';
import { money, number } from '@shared/lib/format';
import type { StatisticsGranularity, StatisticsPayload } from '@shared/types/statistics';
import type { ProjectDetail } from '@shared/types/projects';
import type { RangeSelection } from '../../dashboard/DateRangeControl';
import { BreakdownChart } from './BreakdownChart';
import { BudgetChart } from './BudgetChart';
import { ChartSkeleton, SharedHoverProvider } from './chartFoundation';
import { GuestPostsChart } from './GuestPostsChart';
import { LinksChart } from './LinksChart';
import { StatisticsControlBar } from './StatisticsControlBar';
import { StatisticsTable } from './StatisticsTable';

interface Props {
    project: ProjectDetail;
    statistics: StatisticsPayload;
}

/**
 * How a project is doing, over a range the advertiser picks.
 *
 * Everything on the tab reads one payload, so the cards, the charts and the
 * table cannot disagree; changing a control is one partial reload of that
 * payload rather than five requests racing each other.
 */
export function StatisticsTab({ project, statistics }: Props) {
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const started = router.on('start', (event) => {
            if (event.detail.visit.url.pathname.startsWith(`/projects/${project.id}`)) setLoading(true);
        });
        const finished = router.on('finish', () => setLoading(false));

        return () => {
            started();
            finished();
        };
    }, [project.id]);

    const visit = useCallback(
        (patch: { range?: RangeSelection; granularity?: StatisticsGranularity; folderId?: number | null }) => {
            const range = patch.range ?? {
                key: statistics.range.key as RangeSelection['key'],
                from: statistics.range.from,
                to: statistics.range.to,
            };

            const query: Record<string, string> = {
                tab: 'statistics',
                range: range.key,
                granularity: patch.granularity ?? statistics.granularity,
            };

            if (range.key === 'custom') {
                query.from = range.from ?? statistics.range.from;
                query.to = range.to ?? statistics.range.to;
            }

            const folder = patch.folderId === undefined ? statistics.folderId : patch.folderId;
            if (folder !== null) query.folder = String(folder);

            router.get(`/projects/${project.id}`, query, {
                preserveState: true,
                preserveScroll: true,
                // The page nests everything under one prop; naming `series`
                // here would ask for something this page does not have.
                only: ['statistics'],
            });
        },
        [project.id, statistics],
    );

    const reset = useCallback(() => visit({ range: { key: 'last_30' } }), [visit]);

    // Never had a post at all: an invitation, and none of the furniture. A
    // control bar over five empty charts is a worse answer than one sentence.
    if (!statistics.hasEverHadPosts) {
        return (
            <EmptyState
                illustration={<GlobeIcon size={26} />}
                direction="Statistics appear after your first placement"
                body="Once a link goes live we start plotting spend, posts and links for this project."
                action={
                    <Link href={`/catalog?project=${project.id}`}>
                        <Button size="lg">Find a website</Button>
                    </Link>
                }
            />
        );
    }

    const { summary, series } = statistics;

    const exportQuery: Record<string, string> = {
        range: statistics.range.key,
        from: statistics.range.from,
        to: statistics.range.to,
        granularity: statistics.granularity,
        ...(statistics.folderId === null ? {} : { folder: String(statistics.folderId) }),
    };

    return (
        <div className="flex flex-col gap-5">
            <StatisticsControlBar
                projectId={project.id}
                range={{
                    key: statistics.range.key as RangeSelection['key'],
                    from: statistics.range.from,
                    to: statistics.range.to,
                }}
                rangeLabel={statistics.range.label}
                granularity={statistics.granularity}
                folderId={statistics.folderId}
                folders={statistics.folders}
                onChange={visit}
                exportQuery={exportQuery}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Budget spent"
                    value={money(summary.spentCents)}
                    delta={summary.spentDeltaPct ?? undefined}
                    deltaPlaceholder={summary.spentDeltaPct === null ? 'New' : undefined}
                    deltaLabel="vs previous period"
                    loading={loading}
                />
                <StatCard
                    label="Guest posts published"
                    value={number(summary.published)}
                    delta={summary.publishedDeltaPct ?? undefined}
                    deltaPlaceholder={summary.publishedDeltaPct === null ? 'New' : undefined}
                    deltaLabel="vs previous period"
                    loading={loading}
                />
                <StatCard
                    label="Links live"
                    value={number(summary.links)}
                    delta={summary.linksDeltaPct ?? undefined}
                    deltaPlaceholder={summary.linksDeltaPct === null ? 'New' : undefined}
                    deltaLabel="vs previous period"
                    loading={loading}
                />
                <StatCard
                    label="Average price per link"
                    // Null, not zero: nothing published means there is no
                    // average, and $0.00 would read as "these were free".
                    value={summary.averageCents === null ? '—' : money(summary.averageCents)}
                    delta={summary.averageDeltaPct ?? undefined}
                    deltaPlaceholder={summary.averageDeltaPct === null ? 'New' : undefined}
                    deltaLabel="vs previous period"
                    loading={loading}
                />
            </div>

            {loading ? (
                <div className="flex flex-col gap-5">
                    <ChartSkeleton height={190} />
                    <ChartSkeleton height={170} />
                    <ChartSkeleton height={130} />
                </div>
            ) : (
                // One provider around all four time charts: pointing at a period
                // in any of them puts the crosshair on that period in all.
                <SharedHoverProvider>
                    <div className="flex flex-col gap-5">
                        <BudgetChart
                            projectId={project.id}
                            series={series}
                            granularity={statistics.granularity}
                            onReset={reset}
                        />
                        <GuestPostsChart
                            projectId={project.id}
                            series={series}
                            granularity={statistics.granularity}
                            onReset={reset}
                        />
                        <LinksChart
                            projectId={project.id}
                            series={series}
                            granularity={statistics.granularity}
                            onReset={reset}
                        />

                        <BreakdownChart
                            title="Spend by category"
                            explanation="Where the money went, by the kind of site it went to. The top ten; everything else is folded into Other."
                            rows={statistics.byCategory}
                            onReset={reset}
                        />

                        {/* Only worth drawing once there is more than one folder
                            to compare — a single full-width bar is a number. */}
                        {statistics.folders.length > 1 && (
                            <BreakdownChart
                                title="Spend by folder"
                                explanation="The same money, grouped by the folder each post was ordered under."
                                rows={statistics.byFolder}
                                onReset={reset}
                            />
                        )}
                    </div>
                </SharedHoverProvider>
            )}

            <StatisticsTable series={series} />
        </div>
    );
}
