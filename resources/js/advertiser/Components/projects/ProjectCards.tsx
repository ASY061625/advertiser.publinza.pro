import { Link, router } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { money, number } from '@shared/lib/format';
import { Tooltip } from '@shared/ui';
import type { ProjectRow } from '@shared/types/projects';
import { DeltaChip } from './ProjectsTable';
import { PostMixBar } from './PostMixBar';
import { ProjectActions } from './ProjectActions';
import { ProjectIdentity } from './ProjectIdentity';

interface Props {
    projects: ProjectRow[];
    onDelete: (project: ProjectRow) => void;
}

/**
 * The same figures as the table, three to a row.
 *
 * The same numbers deliberately — a view toggle that changed what was reported
 * would make the two views disagree about the account. What changes is the
 * shape: the table is for comparing projects down a column, the cards for
 * reading one project at a time.
 */
export function ProjectCards({ projects, onDelete }: Props) {
    return (
        <ul className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {projects.map((project) => (
                <li key={project.id}>
                    <article
                        onClick={() => router.visit(`/projects/${project.id}`)}
                        className={cn(
                            'flex h-full cursor-pointer flex-col rounded-card border border-subtle bg-card p-4 shadow-card',
                            'transition-colors duration-fast ease-standard hover:border-brand',
                            project.isArchived && 'opacity-60',
                        )}
                    >
                        <ProjectIdentity project={project} size="card" />

                        <div className="mt-4">
                            <div className="mb-1.5 flex items-baseline justify-between">
                                <span className="text-xs text-ink-500">Posts</span>
                                <span className="num text-sm font-medium text-ink-900">
                                    {number(project.posts.total)}
                                </span>
                            </div>
                            <PostMixBar mix={project.posts} size="full" />
                        </div>

                        <dl className="mt-4 grid grid-cols-3 gap-x-3 gap-y-3">
                            <Stat label="New">
                                <CountLink project={project} status="new" value={project.posts.new} />
                            </Stat>
                            <Stat label="In progress">
                                <CountLink project={project} status="in_progress" value={project.posts.progress} />
                            </Stat>
                            <Stat label="Posted">
                                <CountLink project={project} status="posted" value={project.posts.posted} />
                            </Stat>

                            <Stat label="Frozen">
                                <Tooltip content="Funds held until links are verified">
                                    <span className="num text-sm font-medium text-gold">
                                        {money(project.frozenCents)}
                                    </span>
                                </Tooltip>
                            </Stat>
                            <Stat label="Average">
                                <span className="num text-sm text-ink-700">
                                    {project.averageCents === null ? (
                                        <span className="text-ink-300">—</span>
                                    ) : (
                                        money(project.averageCents)
                                    )}
                                </span>
                            </Stat>
                            <Stat label="This quarter">
                                <span className="num text-sm text-ink-700">{money(project.spentQuarterCents)}</span>
                            </Stat>
                        </dl>

                        <div className="mt-4 flex items-end justify-between gap-3 pb-4">
                            <span>
                                <span className="block text-xs text-ink-500">This month</span>
                                <span className="num block text-md font-semibold text-ink-900">
                                    {money(project.spentMonthCents)}
                                </span>
                                <DeltaChip pct={project.spentMonthDeltaPct} />
                            </span>
                        </div>

                        {/* mt-auto, not mt-4: the card is a full-height flex
                            column, so this pushes the actions to the bottom and
                            they line up across a row of uneven cards. */}
                        <div className="mt-auto flex justify-end border-t border-subtle pt-3">
                            <ProjectActions project={project} onDelete={onDelete} />
                        </div>
                    </article>
                </li>
            ))}
        </ul>
    );
}

function Stat({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-ink-500">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}

function CountLink({ project, status, value }: { project: ProjectRow; status: string; value: number }) {
    if (value === 0) {
        return <span className="num text-sm text-ink-300">0</span>;
    }

    return (
        <Link
            href={`/posts?project=${project.id}&status=${status}`}
            onClick={(event) => event.stopPropagation()}
            className="num text-sm font-medium text-ink-900 underline-offset-2 hover:text-brand hover:underline"
        >
            {number(value)}
        </Link>
    );
}
