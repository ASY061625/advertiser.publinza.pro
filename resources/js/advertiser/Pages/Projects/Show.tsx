import { useForm } from '@inertiajs/react';
import { Alert, Button, Input, Select, Textarea } from '@shared/ui';
import type { ProjectDetail, ProjectFolderRow, ProjectOverviewStats, ProjectTabId } from '@shared/types/projects';
import { ProjectLayout } from '../../Layouts/ProjectLayout';
import { DealsPanel } from '../../Components/projects/general/DealsPanel';
import { FinancePanel } from '../../Components/projects/general/FinancePanel';
import { FirstDealCard } from '../../Components/projects/general/FirstDealCard';
import { FoldersSection } from '../../Components/projects/general/FoldersSection';

interface Props {
    project: ProjectDetail;
    stats: ProjectOverviewStats;
    folders: ProjectFolderRow[];
    tab: ProjectTabId;
    categories: { id: number; name: string }[];
    /** Flashed by the create wizard, read once. */
    justCreated?: boolean;
    /** Flashed by the folder editor: the folder it just saved. */
    folderSaved?: number | null;
}

/**
 * One project. The header and tab bar are the layout's; this file is the body
 * of whichever tab `?tab=` selected.
 *
 * General and Project settings are built. Statistics, History and Competitors
 * are their own pieces of work and say so rather than rendering an empty panel
 * that looks broken. Post management is the posts grid scoped to this project,
 * so the controller redirects `?tab=posts` there instead of copying the grid.
 */
export default function ProjectsShow({
    project,
    stats,
    folders,
    tab,
    categories,
    justCreated = false,
    folderSaved = null,
}: Props) {
    return (
        <ProjectLayout project={project} tab={tab} postMix={stats.posts}>
            {tab === 'settings' ? (
                <SettingsForm project={project} categories={categories} />
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

const COMING: Record<string, string> = {
    statistics: 'Traffic, rankings and link health for every placement on this project, over time.',
    history: 'Every change to this project and its posts, with who made it and when.',
    competitors: 'The sites ranking against yours, and where they are being published.',
};

function NotBuiltYet({ tab }: { tab: ProjectTabId }) {
    return (
        <div className="rounded-card border border-subtle bg-card p-8 text-center shadow-card">
            <p className="text-base font-medium text-ink-900">Not here yet.</p>
            <p className="mx-auto mt-1 max-w-prose text-sm text-ink-500">{COMING[tab]}</p>
        </div>
    );
}

function SettingsForm({ project, categories }: { project: ProjectDetail; categories: { id: number; name: string }[] }) {
    const form = useForm({
        name: project.name,
        website_url: project.websiteUrl,
        category_id: project.categoryId === null ? '' : String(project.categoryId),
        publisher_task: project.publisherTask ?? '',
    });

    const archived = project.isArchived;

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/projects/${project.id}`, { preserveScroll: true });
            }}
            className="flex max-w-xl flex-col gap-4 rounded-card border border-subtle bg-card p-5 shadow-card"
        >
            {archived && (
                <p className="rounded-card bg-sunken px-3 py-2 text-sm text-ink-700">
                    This project is archived and read-only. Restore it from the projects list to edit it.
                </p>
            )}

            <Input
                label="Project name"
                value={form.data.name}
                onChange={(event) => form.setData('name', event.target.value)}
                error={form.errors.name}
                disabled={archived}
                required
            />

            <Input
                label="Website you are promoting"
                type="url"
                value={form.data.website_url}
                onChange={(event) => form.setData('website_url', event.target.value)}
                error={form.errors.website_url}
                disabled={archived}
                required
            />

            <Select
                label="Category"
                value={form.data.category_id}
                onChange={(event) => form.setData('category_id', event.target.value)}
                error={form.errors.category_id}
                disabled={archived}
                options={[
                    { value: '', label: 'No category' },
                    ...categories.map((c) => ({ value: String(c.id), label: c.name })),
                ]}
            />

            <Textarea
                label="Notes for writers"
                value={form.data.publisher_task}
                onChange={(event) => form.setData('publisher_task', event.target.value)}
                error={form.errors.publisher_task}
                disabled={archived}
                rows={4}
                hint="The default brief. A folder can override it for the pages inside it."
            />

            <div className="flex justify-end border-t border-subtle pt-4">
                <Button type="submit" loading={form.processing} disabled={archived}>
                    Save changes
                </Button>
            </div>
        </form>
    );
}
