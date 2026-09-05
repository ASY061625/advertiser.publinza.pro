import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, Button, EmptyState, PlusIcon } from '@shared/ui';
import type {
    ProjectDetail,
    ProjectFolderRow,
    ProjectOverviewStats,
    ProjectPostsGrid,
    ProjectSettingsPayload,
    ProjectTabId,
} from '@shared/types/projects';
import type { CompetitorsPayload } from '@shared/types/competitors';
import type { HistoryPayload } from '@shared/types/history';
import type { StatisticsPayload } from '@shared/types/statistics';
import { PostsGrid, type PostsView } from '../../Components/posts/PostsGrid';
import { ProjectSettingsForm } from '../../Components/projects/settings/ProjectSettingsForm';
import { CompetitorsTab } from '../../Components/projects/competitors/CompetitorsTab';
import { HistoryTab } from '../../Components/projects/history/HistoryTab';
import { StatisticsTab } from '../../Components/projects/statistics/StatisticsTab';
import { ProjectSummaryStrip } from '../../Components/projects/general/ProjectSummaryStrip';
import { ProjectLayout } from '../../Layouts/ProjectLayout';
import { DealsPanel } from '../../Components/projects/general/DealsPanel';
import { FinancePanel } from '../../Components/projects/general/FinancePanel';
import { FirstDealCard } from '../../Components/projects/general/FirstDealCard';
import { FoldersSection } from '../../Components/projects/general/FoldersSection';
import { useAddPost } from '../../Components/post-wizard/AddPostProvider';

interface Props {
    project: ProjectDetail;
    stats: ProjectOverviewStats;
    folders: ProjectFolderRow[];
    tab: ProjectTabId;
    /** Flashed by the create wizard, read once. */
    justCreated?: boolean;
    /** Flashed by the folder editor: the folder it just saved. */
    folderSaved?: number | null;
    /** Only sent for the Post management tab. */
    grid?: ProjectPostsGrid | null;
    /** Only sent for the Project settings tab. */
    settings?: ProjectSettingsPayload | null;
    /** Only sent for the Statistics tab. */
    statistics?: StatisticsPayload | null;
    /** Only sent for the History tab. */
    history?: HistoryPayload | null;
    /** Only sent for the Competitors tab. */
    competitors?: CompetitorsPayload | null;
}

/**
 * One project. The header and tab bar are the layout's; this file is the body
 * of whichever tab `?tab=` selected.
 *
 * Post management is the posts grid scoped to this project rather than a copy
 * of it, which is why that branch passes so much configuration: the same
 * component serves /posts, and the differences between the two are props.
 */
export default function ProjectsShow({
    project,
    stats,
    folders,
    tab,
    justCreated = false,
    folderSaved = null,
    grid = null,
    settings = null,
    statistics = null,
    history = null,
    competitors = null,
}: Props) {
    return (
        <ProjectLayout project={project} tab={tab} postMix={stats.posts}>
            {tab === 'posts' && grid !== null ? (
                <PostManagement project={project} stats={stats} grid={grid} />
            ) : tab === 'settings' && settings !== null ? (
                <ProjectSettingsForm project={project} settings={settings} />
            ) : tab === 'statistics' && statistics !== null ? (
                <StatisticsTab project={project} statistics={statistics} />
            ) : tab === 'history' && history !== null ? (
                <HistoryTab project={project} history={history} />
            ) : tab === 'competitors' && competitors !== null ? (
                <CompetitorsTab project={project} competitors={competitors} />
            ) : tab === 'general' ? (
                <General
                    project={project}
                    stats={stats}
                    folders={folders}
                    justCreated={justCreated}
                    folderSaved={folderSaved}
                />
            ) : (
                <NotBuiltYet tab={tab} />
            )}
        </ProjectLayout>
    );
}

function General({
    project,
    stats,
    folders,
    justCreated,
    folderSaved,
}: {
    project: ProjectDetail;
    stats: ProjectOverviewStats;
    folders: ProjectFolderRow[];
    justCreated: boolean;
    folderSaved: number | null;
}) {
    return (
        <div className="flex flex-col gap-5">
            {justCreated && (
                <Alert tone="success" title={`${project.name} is ready.`}>
                    Every site in the catalog is one we own and run, so a placement you order goes live.
                </Alert>
            )}

            {stats.posts.total === 0 && <FirstDealCard projectId={project.id} />}

            {/* 7/5 on desktop: Deals carries four tiles and a bar and needs the
                room; Finance is three rows of text and does not. */}
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
                <div className="lg:col-span-7">
                    <DealsPanel projectId={project.id} mix={stats.posts} />
                </div>

                <div className="lg:col-span-5">
                    <FinancePanel stats={stats} />
                </div>
            </div>

            <FoldersSection
                projectId={project.id}
                folders={folders}
                readOnly={project.isArchived}
                highlightId={folderSaved}
            />
        </div>
    );
}

/**
 * The posts grid, locked to this project.
 *
 * The same component /posts renders — not a copy of it — so the table, the
 * drawer, the filters and the bulk actions are the ones an advertiser already
 * knows. What this adds is the summary strip and the Table/Board toggle; what
 * it takes away is the Project column, the Project filter and saved views,
 * none of which mean anything inside a single project.
 */
function PostManagement({
    project,
    stats,
    grid,
}: {
    project: ProjectDetail;
    stats: ProjectOverviewStats;
    grid: ProjectPostsGrid;
}) {
    // Not persisted: which shape you want to read a project's work in is a
    // choice about this minute, and a remembered board is a surprise on the
    // next visit. The table is where everyone starts.
    const [view, setView] = useState<PostsView>('table');
    const addPost = useAddPost();

    return (
        <PostsGrid
            posts={grid.posts}
            tabCounts={grid.tabCounts}
            filters={grid.filters}
            isFiltering={grid.isFiltering}
            options={grid.options}
            columns={grid.columns}
            path={`/projects/${project.id}`}
            // Carried on every filter change, or the grid would navigate itself
            // off its own tab and back to General.
            fixedQuery={{ tab: 'posts' }}
            // This page already spends `tab` on which tab of the project is
            // open, so the grid's status tab travels under its own key.
            tabKey="posts_tab"
            // This page nests the whole grid under one prop, so that is what a
            // filter change asks for. Asking for `posts` here would name a prop
            // /posts has and this page does not, and the grid would silently
            // keep showing the last result.
            only={['grid']}
            scope={{ projectId: project.id, folders: grid.folders }}
            summary={<ProjectSummaryStrip stats={stats} />}
            view={view}
            onViewChange={setView}
            emptyState={
                <div className="flex flex-col gap-5">
                    <ProjectSummaryStrip stats={stats} />

                    <EmptyState
                        illustration={<PlusIcon size={26} />}
                        direction="Time to add your first post!"
                        body="Choose a site in the catalog, add your anchor and link, and we’ll handle the placement."
                        action={
                            <span className="flex flex-wrap items-center justify-center gap-2">
                                <Button size="lg" onClick={() => addPost.open({ projectId: project.id })}>
                                    Add post
                                </Button>
                                <Link href={`/catalog?project=${project.id}`}>
                                    <Button size="lg" variant="secondary">
                                        Find a website
                                    </Button>
                                </Link>
                            </span>
                        }
                    />
                </div>
            }
        />
    );
}

/**
 * Every tab is built now, so this is only reachable if a payload failed to
 * arrive for the tab that was asked for — a reload is the honest advice.
 */
function NotBuiltYet({ tab }: { tab: ProjectTabId }) {
    return (
        <div className="rounded-card border border-subtle bg-card p-8 text-center shadow-card">
            <p className="text-base font-medium text-ink-900">This tab did not load.</p>
            <p className="mx-auto mt-1 max-w-prose text-sm text-ink-500">
                Reload the page to try again. If it keeps happening, tell us which project and which tab ({tab}) and we
                will look.
            </p>
        </div>
    );
}
