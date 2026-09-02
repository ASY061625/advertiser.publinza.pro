import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { AppShell } from '../../Layouts/AppShell';
import { Button, Input, PlusIcon, SearchIcon, Select } from '@shared/ui';
import { money, number } from '@shared/lib/format';
import type { ProjectFilterState, ProjectRow, ProjectTotals } from '@shared/types/projects';
import { DeleteProjectDialog } from '../../Components/projects/DeleteProjectDialog';
import { ProjectCards } from '../../Components/projects/ProjectCards';
import { ProjectsTable } from '../../Components/projects/ProjectsTable';
import { ViewToggle } from '../../Components/projects/ViewToggle';

interface Props {
    projects: ProjectRow[];
    totals: ProjectTotals;
    filters: ProjectFilterState;
    hasAnyProjects: boolean;
    isFiltering: boolean;
    view: 'table' | 'cards';
}

const STATUSES = [
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
    { value: 'all', label: 'All' },
];

export default function ProjectsIndex({
    projects,
    totals,
    filters: serverFilters,
    hasAnyProjects,
    isFiltering,
    view: serverView,
}: Props) {
    const [filters, setFilters] = useState<ProjectFilterState>(serverFilters);
    const [view, setView] = useState(serverView);
    const [deleting, setDeleting] = useState<ProjectRow | null>(null);
    const [search, setSearch] = useState(serverFilters.q ?? '');

    // The server echoes the filters it actually applied; adopting them keeps
    // the controls honest about what is on screen.
    useEffect(() => {
        setFilters(serverFilters);
        setSearch(serverFilters.q ?? '');
    }, [serverFilters]);

    useEffect(() => setView(serverView), [serverView]);

    const apply = useCallback((patch: Partial<ProjectFilterState>) => {
        setFilters((current) => {
            const next = { ...current, ...patch };

            for (const [key, value] of Object.entries(patch)) {
                if (value === undefined || value === '') delete next[key as keyof ProjectFilterState];
            }

            router.get('/projects', { ...next }, { preserveState: true, preserveScroll: true });

            return next;
        });
    }, []);

    // Debounced so the list does not reload on every keystroke.
    useEffect(() => {
        if (search === (filters.q ?? '')) return;

        const timer = window.setTimeout(() => apply({ q: search || undefined }), 300);

        return () => window.clearTimeout(timer);
        // apply is rebuilt per render; depending on it would restart the timer
        // mid-word.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    function changeView(next: 'table' | 'cards') {
        setView(next);

        // Fire and forget: the layout has already changed, and a failed write
        // only means the next browser starts from the table.
        router.patch('/projects/view', { view: next }, { preserveScroll: true, preserveState: true });
    }

    const header = (
        <header className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-baseline gap-3">
                <h1 className="font-sora text-xl font-semibold text-ink-900">My projects</h1>
                {hasAnyProjects && (
                    <span className="num text-sm text-ink-500">
                        {number(projects.length)} {projects.length === 1 ? 'project' : 'projects'}
                    </span>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {hasAnyProjects && (
                    <>
                        <div className="w-52">
                            <Input
                                label="Search projects"
                                hideLabel
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Name or URL"
                                leadingIcon={<SearchIcon size={16} />}
                            />
                        </div>

                        <Select
                            label="Status"
                            hideLabel
                            className="w-32"
                            value={filters.status ?? 'active'}
                            onChange={(event) => apply({ status: event.target.value as ProjectFilterState['status'] })}
                            options={STATUSES}
                        />

                        <ViewToggle value={view} onChange={changeView} />
                    </>
                )}

                <Link href="/projects/create">
                    <Button>
                        <PlusIcon size={14} />
                        Create project
                    </Button>
                </Link>
            </div>
        </header>
    );

    // Nothing at all, ever. An invitation, not an error — and distinct from
    // "nothing matches this filter", which has a different thing to do about it.
    if (!hasAnyProjects && !isFiltering) {
        return (
            <AppShell title="Projects">
                <Head title="My projects" />
                {header}

                <section className="mx-auto mt-8 max-w-lg rounded-card border border-subtle bg-card px-6 py-14 text-center shadow-card">
                    <span className="mx-auto flex size-14 items-center justify-center rounded-card bg-brand-subtle text-brand">
                        <PlusIcon size={26} />
                    </span>

                    <h2 className="mt-5 font-sora text-lg font-semibold text-ink-900">Create your first project</h2>

                    <p className="mx-auto mt-2 max-w-sm text-md text-ink-700">
                        A project is the site you&rsquo;re promoting. Sites, posts and spend are all tracked per
                        project.
                    </p>

                    <Link href="/projects/create" className="mt-6 inline-block">
                        <Button size="lg">Create project</Button>
                    </Link>
                </section>
            </AppShell>
        );
    }

    return (
        <AppShell title="Projects">
            <Head title="My projects" />

            {header}

            <div className="mt-5">
                {projects.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-card border border-dashed border-subtle bg-sunken px-6 py-12 text-center">
                        <SearchIcon size={22} className="text-ink-300" />
                        <p className="text-md font-medium text-ink-700">No projects match this filter</p>
                        <Button variant="secondary" size="sm" onClick={() => apply({ status: 'active', q: undefined })}>
                            Show active projects
                        </Button>
                    </div>
                ) : view === 'cards' ? (
                    <>
                        <ProjectCards projects={projects} onDelete={setDeleting} />
                        <CardTotals totals={totals} count={projects.length} />
                    </>
                ) : (
                    <ProjectsTable
                        projects={projects}
                        totals={totals}
                        sort={{
                            column: filters.sort ?? 'spent_month',
                            direction: filters.direction ?? 'desc',
                        }}
                        onSortChange={(column) =>
                            apply({
                                sort: column as ProjectFilterState['sort'],
                                direction: filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc',
                            })
                        }
                        onDelete={setDeleting}
                    />
                )}
            </div>

            <DeleteProjectDialog project={deleting} onClose={() => setDeleting(null)} />
        </AppShell>
    );
}

/**
 * The card grid has no tfoot, so the totals get their own strip — the same four
 * figures, so switching view never loses the summary.
 */
function CardTotals({ totals, count }: { totals: ProjectTotals; count: number }) {
    const cells: [string, string][] = [
        ['Posts', number(totals.posts)],
        ['Frozen', money(totals.frozenCents)],
        ['Spent this month', money(totals.spentMonthCents)],
        ['Spent this quarter', money(totals.spentQuarterCents)],
    ];

    return (
        <div className="mt-4 flex flex-wrap items-center gap-x-8 gap-y-3 rounded-card border border-subtle bg-sunken px-4 py-3">
            <span className="text-sm font-medium text-ink-900">
                Totals · {number(count)} project{count === 1 ? '' : 's'}
            </span>

            {cells.map(([label, value], index) => (
                <span key={label} className="flex items-baseline gap-2">
                    <span className="text-xs text-ink-500">{label}</span>
                    <span className={cn('num text-sm font-medium', index === 1 ? 'text-gold' : 'text-ink-900')}>
                        {value}
                    </span>
                </span>
            ))}
        </div>
    );
}
