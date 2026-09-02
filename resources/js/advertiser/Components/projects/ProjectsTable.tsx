import { Link, router } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { money, number } from '@shared/lib/format';
import { ArrowDownIcon, ArrowUpIcon, ChevronDownIcon, ChevronUpIcon, SortIcon, Tooltip } from '@shared/ui';
import type { ProjectRow, ProjectTotals } from '@shared/types/projects';
import { PostMixBar } from './PostMixBar';
import { ProjectActions } from './ProjectActions';
import { ProjectIdentity } from './ProjectIdentity';

interface Props {
    projects: ProjectRow[];
    totals: ProjectTotals;
    sort: { column: string; direction: 'asc' | 'desc' };
    onSortChange: (column: string) => void;
    onDelete: (project: ProjectRow) => void;
}

const SORTABLE: Record<string, string> = {
    name: 'Project',
    posts: 'Posts',
    spent_month: 'Spent this month',
    created_at: 'Created',
};

/**
 * The reporting table.
 *
 * The three status counts are links rather than numbers: seeing that eleven
 * posts are in progress and then having to go and rebuild that filter by hand
 * on /posts is the kind of small friction that makes a reporting screen a
 * dead end.
 */
export function ProjectsTable({ projects, totals, sort, onSortChange, onDelete }: Props) {
    return (
        <div className="overflow-x-auto rounded-card border border-subtle bg-card shadow-card">
            <table className="w-full min-w-[1100px] border-collapse text-left">
                <caption className="sr-only">
                    Your projects, with post counts and spend. Select a row to open it.
                </caption>

                <thead className="table-sticky-header">
                    <tr className="border-b border-subtle bg-card">
                        <Th id="name" sort={sort} onSortChange={onSortChange}>
                            Project
                        </Th>
                        <Th id="posts" sort={sort} onSortChange={onSortChange}>
                            Posts
                        </Th>
                        <Th numeric>New</Th>
                        <Th numeric>In progress</Th>
                        <Th numeric>Posted</Th>
                        <Th numeric hint="Funds held until links are verified">
                            Frozen price
                        </Th>
                        <Th numeric hint="Across completed posts only">
                            Average price
                        </Th>
                        <Th id="spent_month" numeric sort={sort} onSortChange={onSortChange}>
                            Spent this month
                        </Th>
                        <Th numeric>Spent this quarter</Th>
                        <th scope="col" className="px-3 py-2.5 text-right">
                            <span className="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {projects.map((project) => (
                        <tr
                            key={project.id}
                            onClick={() => router.visit(`/projects/${project.id}`)}
                            className={cn(
                                'cursor-pointer border-b border-subtle transition-colors duration-fast ease-standard',
                                'hover:bg-row-hover',
                                // Archived rows recede rather than disappear:
                                // still readable, clearly not the live work.
                                project.isArchived && 'opacity-60',
                            )}
                        >
                            <td className="max-w-xs px-3 py-3">
                                <ProjectIdentity project={project} />
                            </td>

                            <td className="px-3 py-3">
                                <span className="num block text-sm font-medium text-ink-900">
                                    {number(project.posts.total)}
                                </span>
                                <span className="mt-1.5 block">
                                    <PostMixBar mix={project.posts} />
                                </span>
                            </td>

                            <CountCell project={project} status="new" value={project.posts.new} />
                            <CountCell project={project} status="in_progress" value={project.posts.progress} />
                            <CountCell project={project} status="posted" value={project.posts.posted} />

                            <td className="num px-3 py-3 text-right text-sm font-medium text-gold">
                                <Tooltip content="Funds held until links are verified">
                                    <span>{money(project.frozenCents)}</span>
                                </Tooltip>
                            </td>

                            <td className="num px-3 py-3 text-right text-sm text-ink-700">
                                {project.averageCents === null ? (
                                    <span className="text-ink-300">—</span>
                                ) : (
                                    money(project.averageCents)
                                )}
                            </td>

                            <td className="px-3 py-3 text-right">
                                <span className="num block text-sm font-medium text-ink-900">
                                    {money(project.spentMonthCents)}
                                </span>
                                <DeltaChip pct={project.spentMonthDeltaPct} />
                            </td>

                            <td className="num px-3 py-3 text-right text-sm text-ink-700">
                                {money(project.spentQuarterCents)}
                            </td>

                            <td className="px-3 py-3">
                                <span className="flex justify-end">
                                    <ProjectActions project={project} onDelete={onDelete} />
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>

                <tfoot>
                    <tr className="border-t-2 border-subtle bg-sunken">
                        <td className="px-3 py-3 text-sm font-medium text-ink-900">
                            Totals · {number(projects.length)} project{projects.length === 1 ? '' : 's'}
                        </td>
                        <td className="num px-3 py-3 text-sm font-medium text-ink-900">{number(totals.posts)}</td>
                        <td colSpan={3} />
                        <td className="num px-3 py-3 text-right text-sm font-medium text-gold">
                            {money(totals.frozenCents)}
                        </td>
                        <td />
                        <td className="num px-3 py-3 text-right text-sm font-medium text-ink-900">
                            {money(totals.spentMonthCents)}
                        </td>
                        <td className="num px-3 py-3 text-right text-sm font-medium text-ink-900">
                            {money(totals.spentQuarterCents)}
                        </td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

/** A count that takes you to exactly the posts behind it. */
function CountCell({ project, status, value }: { project: ProjectRow; status: string; value: number }) {
    return (
        <td className="num px-3 py-3 text-right text-sm">
            {value === 0 ? (
                <span className="text-ink-300">0</span>
            ) : (
                <Link
                    href={`/posts?project=${project.id}&status=${status}`}
                    onClick={(event) => event.stopPropagation()}
                    className="text-ink-900 underline-offset-2 hover:text-brand hover:underline"
                >
                    {number(value)}
                </Link>
            )}
        </td>
    );
}

export function DeltaChip({ pct }: { pct: number | null }) {
    // Null means last month was zero. "Up 100%" from nothing is not a fact.
    if (pct === null) {
        return (
            <span className="mt-1 inline-flex rounded-pill bg-brand-subtle px-1.5 py-0.5 text-xs font-medium text-brand">
                New
            </span>
        );
    }

    if (pct === 0) {
        return (
            <span className="num mt-1 inline-flex rounded-pill bg-sunken px-1.5 py-0.5 text-xs text-ink-500">
                No change
            </span>
        );
    }

    const up = pct > 0;

    return (
        <span
            className={cn(
                'num mt-1 inline-flex items-center gap-0.5 rounded-pill px-1.5 py-0.5 text-xs font-medium',
                up ? 'bg-teal-subtle text-success' : 'bg-danger-bg text-danger',
            )}
        >
            {up ? <ArrowUpIcon size={10} /> : <ArrowDownIcon size={10} />}
            {Math.abs(pct).toFixed(1)}%
        </span>
    );
}

function Th({
    id,
    children,
    numeric = false,
    hint,
    sort,
    onSortChange,
}: {
    id?: string;
    children: React.ReactNode;
    numeric?: boolean;
    hint?: string;
    sort?: { column: string; direction: 'asc' | 'desc' };
    onSortChange?: (column: string) => void;
}) {
    const sortable = id !== undefined && sort !== undefined && onSortChange !== undefined && id in SORTABLE;
    const active = sortable && sort.column === id;

    const label = hint ? (
        <Tooltip content={hint}>
            <span className="underline decoration-dotted underline-offset-2">{children}</span>
        </Tooltip>
    ) : (
        children
    );

    return (
        <th
            scope="col"
            aria-sort={active ? (sort.direction === 'asc' ? 'ascending' : 'descending') : undefined}
            className={cn(
                'whitespace-nowrap px-3 py-2.5 text-xs font-medium uppercase tracking-wide text-ink-500',
                numeric && 'text-right',
            )}
        >
            {sortable ? (
                <button
                    type="button"
                    onClick={() => onSortChange(id)}
                    className={cn(
                        // `uppercase` is repeated here on purpose: a browser's
                        // own stylesheet sets text-transform: none on button,
                        // which beats the inherited value from the th.
                        '-mx-1 inline-flex items-center gap-1 rounded-button px-1 py-0.5 uppercase',
                        'transition-colors duration-fast hover:text-ink-700',
                        active && 'text-ink-900',
                    )}
                >
                    {label}
                    {active ? (
                        sort.direction === 'asc' ? (
                            <ChevronUpIcon size={12} />
                        ) : (
                            <ChevronDownIcon size={12} />
                        )
                    ) : (
                        <SortIcon size={12} className="text-ink-300" />
                    )}
                </button>
            ) : (
                label
            )}
        </th>
    );
}
