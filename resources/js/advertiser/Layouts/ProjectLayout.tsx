import { Head, Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button, Dropdown, IconButton, MoreIcon, Tabs, type TabItem } from '@shared/ui';
import type { ProjectDetail, ProjectPostMix, ProjectTabId } from '@shared/types/projects';
import { AppShell } from './AppShell';
import { DeleteProjectDialog } from '../Components/projects/DeleteProjectDialog';
import { projectDot } from '../Components/projects/ProjectIdentity';

interface Props {
    project: ProjectDetail;
    tab: ProjectTabId;
    /** Only so the delete dialog can explain a refusal before anything is typed. */
    postMix: ProjectPostMix;
    children: ReactNode;
}

/**
 * The frame every tab of a project renders inside: identity, the two actions
 * worth a button, and the tab bar.
 *
 * Built once here rather than per tab so the header cannot drift between them —
 * six copies of a page title is six chances for one of them to stop matching.
 *
 * A tab is a URL. `?tab=` is the state, validated server-side, so a tab can be
 * bookmarked, reloaded and sent to a colleague; a tab held only in React state
 * survives none of that. The switch is a partial visit reloading nothing —
 * every tab's data already came down with the page — so it is instant and the
 * back button walks the tabs.
 */
const TABS: { id: ProjectTabId; label: string }[] = [
    { id: 'general', label: 'General' },
    { id: 'posts', label: 'Post management' },
    { id: 'settings', label: 'Project settings' },
    { id: 'statistics', label: 'Statistics' },
    { id: 'history', label: 'History' },
    { id: 'competitors', label: 'Competitors' },
];

export function ProjectLayout({ project, tab, postMix, children }: Props) {
    const [deleting, setDeleting] = useState(false);

    const items: TabItem[] = TABS.map((item) => ({
        id: item.id,
        label: item.label,
        content: item.id === tab ? children : undefined,
    }));

    return (
        <AppShell title="Projects" crumbs={[{ label: 'My projects', href: '/projects' }, { label: project.name }]}>
            <Head title={project.name} />

            <header className="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
                <div className="flex min-w-0 items-start gap-3">
                    <span
                        aria-hidden="true"
                        className="mt-2 size-3 shrink-0 rounded-pill"
                        style={{ backgroundColor: projectDot(project) }}
                    />

                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="font-sora text-xl font-semibold text-ink-900">{project.name}</h1>

                            {project.category && (
                                <span className="rounded-pill bg-sunken px-2.5 py-1 text-xs text-ink-500">
                                    {project.category}
                                </span>
                            )}

                            {project.isArchived && (
                                <span className="rounded-pill bg-status-frozen-bg px-2.5 py-1 text-xs font-medium text-status-frozen-fg">
                                    Archived
                                </span>
                            )}
                        </div>

                        <a
                            href={project.websiteUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-1 inline-block max-w-full truncate text-sm text-ink-500 hover:text-brand hover:underline"
                        >
                            {project.websiteUrl.replace(/^https?:\/\//, '').replace(/\/$/, '')}
                        </a>
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-2">
                    {/* The catalog carries the project through, so the sites it
                        offers are filtered to this project's targeting rather
                        than the whole inventory. */}
                    <Link href={`/catalog?project=${project.id}`}>
                        <Button>Find a website</Button>
                    </Link>

                    <Link href={`/catalog?project=${project.id}&intent=add-post`}>
                        <Button variant="secondary">Add post</Button>
                    </Link>

                    <Dropdown
                        trigger={<IconButton label="More project actions" icon={<MoreIcon size={18} />} />}
                        items={[
                            {
                                id: 'edit',
                                label: 'Edit project',
                                disabled: project.isArchived,
                                onSelect: () => router.visit(`/projects/${project.id}?tab=settings`),
                            },
                            {
                                id: 'archive',
                                label: project.isArchived ? 'Restore project' : 'Archive project',
                                onSelect: () =>
                                    router.post(
                                        `/projects/${project.id}/${project.isArchived ? 'restore' : 'archive'}`,
                                        {},
                                        { preserveScroll: true },
                                    ),
                            },
                            {
                                id: 'delete',
                                label: 'Delete project',
                                destructive: true,
                                onSelect: () => setDeleting(true),
                            },
                        ]}
                    />
                </div>
            </header>

            {/* Six tabs do not fit a phone, so the row scrolls sideways rather
                than wrapping into a second line. `scrollable` puts the overflow
                on the row alone — putting it on the whole component would drag
                the panel's content off the right of the screen with it. */}
            <Tabs
                scrollable
                // Post management is a navigation, not a panel swap. With
                // automatic activation you could not arrow past it to reach
                // Project settings — the first ArrowRight would take the whole
                // page with it. Arrows move focus; Enter commits.
                manualActivation
                className="mt-5"
                items={items}
                value={tab}
                onChange={(next) =>
                    router.visit(`/projects/${project.id}?tab=${next}`, {
                        preserveScroll: true,
                        // Post management is the posts grid scoped to this
                        // project, so it is a real navigation. The others
                        // arrived with the page and only need the URL.
                        preserveState: next !== 'posts',
                        ...(next === 'posts' ? {} : { only: [] }),
                    })
                }
            />

            <DeleteProjectDialog
                project={deleting ? deleteShape(project, postMix) : null}
                onClose={() => setDeleting(false)}
            />
        </AppShell>
    );
}

/**
 * The delete dialog was written against a projects-list row. It is given the
 * same shape here, with the real post mix, so it can say up front that work is
 * still in flight instead of letting someone type the name and then refusing.
 * The spend figures it does not read are zeroed rather than faked.
 */
function deleteShape(project: ProjectDetail, postMix: ProjectPostMix) {
    return {
        ...project,
        statusLabel: project.isArchived ? 'Archived' : 'Active',
        posts: postMix,
        frozenCents: 0,
        averageCents: null,
        spentMonthCents: 0,
        spentQuarterCents: 0,
        spentMonthDeltaPct: null,
    };
}
