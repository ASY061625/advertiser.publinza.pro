import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Alert, EmptyState, GlobeIcon } from '@shared/ui';
import { date } from '@shared/lib/format';
import type { CompetitorsPayload } from '@shared/types/competitors';
import type { ProjectDetail } from '@shared/types/projects';
import { AddCompetitorRow } from './AddCompetitorRow';
import { AuthorityChart } from './AuthorityChart';
import { CompetitorTable } from './CompetitorTable';
import { GapKeywordsDrawer } from './GapKeywordsDrawer';
import { KeywordOverlapChart } from './KeywordOverlapChart';
import { RecommendationStrip } from './RecommendationStrip';
import { TrafficTrendChart } from './TrafficTrendChart';
import { YourSiteCard } from './YourSiteCard';

interface Props {
    project: ProjectDetail;
    competitors: CompetitorsPayload;
}

/** How often the tab re-reads itself while a row is still being measured. */
const POLL_MS = 4000;

export function CompetitorsTab({ project, competitors }: Props) {
    const [gapFor, setGapFor] = useState<{ id: number; domain: string; label: string | null } | null>(null);

    const { self, competitors: rows, slots, source, trend, overlap, recommendations } = competitors;
    const pending = rows.some((row) => row.state === 'pending') || self?.state === 'pending';

    // A row is added in a loading state and filled in by a queued job, so the
    // page has to come back for the answer. Polling stops the moment nothing is
    // pending — an idle tab open all afternoon should not be asking.
    useEffect(() => {
        if (!pending) return;

        const timer = window.setInterval(() => {
            router.reload({ only: ['competitors'] });
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [pending]);

    const failed = rows.filter((row) => row.state === 'failed' && row.error !== null);

    if (rows.length === 0) {
        return (
            <div className="flex flex-col gap-5">
                {/* Same position as in the populated view, so adding the first
                    competitor does not move the field out from under the cursor
                    that just used it. */}
                <AddCompetitorRow
                    projectId={project.id}
                    used={slots.used}
                    limit={slots.limit}
                    disabled={project.isArchived}
                    autoFocus
                />

                <YourSiteCard row={self} />

                <EmptyState
                    illustration={<GlobeIcon size={26} />}
                    direction="Add a competitor to see how your site compares"
                    body="Track up to ten rival domains. We measure the same figures for each of them and for your own site, so every comparison is like for like."
                />
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-5">
            <AddCompetitorRow
                projectId={project.id}
                used={slots.used}
                limit={slots.limit}
                disabled={project.isArchived}
            />

            {/* An outage keeps the last good figures on screen under a notice
                that dates them. An error screen here would throw away numbers
                that are still true, just not current. */}
            {source.showingCached && (
                <Alert
                    tone="warning"
                    title={`Showing cached data from ${source.cachedSince ? date(source.cachedSince) : 'an earlier fetch'}`}
                >
                    {failed.length === 1 && failed[0]
                        ? failed[0].error
                        : `${failed.length} domains could not be measured on the last attempt. The figures below are the most recent ones we hold.`}
                </Alert>
            )}

            <YourSiteCard row={self} />

            <CompetitorTable
                projectId={project.id}
                rows={rows}
                readOnly={project.isArchived}
                onOpenGap={(row) => setGapFor({ id: row.id, domain: row.domain, label: row.label })}
            />

            {/* Always, and always naming who: two vendors disagree about the
                same domain by a wide margin, so a figure without its source is
                not a figure anyone can act on. */}
            <p className="text-sm text-ink-500">
                Data from {source.provider}
                {source.updatedAt ? `, updated ${date(source.updatedAt)}` : ''}. Figures refresh every{' '}
                {source.cacheDays} days, or when you refresh a row.
            </p>

            <div className="grid grid-cols-1 gap-5">
                <TrafficTrendChart months={trend.months} series={trend.series} />

                <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <AuthorityChart self={self} competitors={rows} />
                    <KeywordOverlapChart
                        rows={overlap}
                        onOpenGap={(row) => setGapFor({ id: row.id, domain: row.domain, label: row.label })}
                    />
                </div>
            </div>

            <RecommendationStrip projectId={project.id} recommendations={recommendations} />

            <GapKeywordsDrawer
                projectId={project.id}
                competitor={gapFor}
                limit={competitors.maxGapKeywords}
                onClose={() => setGapFor(null)}
            />
        </div>
    );
}
