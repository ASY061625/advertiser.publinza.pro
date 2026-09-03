import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Alert, Button, Input, Modal } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { ProjectDetail, ProjectBlockingPost } from '@shared/types/projects';

interface Props {
    project: ProjectDetail;
    blockingPosts: ProjectBlockingPost[];
    retentionDays: number;
    /** Cleared before either action navigates, so the guard does not fire. */
    onLeave: () => void;
}

/**
 * The two ways to stop using a project, and the difference between them.
 *
 * Archiving and deleting sit together because that is how people arrive at
 * them — "I am done with this" — and apart in every other way: one is a status
 * you can flip back this afternoon, the other keeps the row for a month and
 * then does not.
 *
 * Delete is refused while any post is still in flight, and the refusal is on
 * screen before anything is typed. Naming the posts, with links, is the
 * difference between a rule and a dead end.
 */
export function DangerZone({ project, blockingPosts, retentionDays, onLeave }: Props) {
    const [confirming, setConfirming] = useState(false);
    const [typed, setTyped] = useState('');

    useEffect(() => setTyped(''), [confirming]);

    const blocked = blockingPosts.length > 0;
    const matches = typed.trim() === project.name;

    return (
        <div className="flex flex-col gap-5">
            <Row
                title={project.isArchived ? 'Restore project' : 'Archive project'}
                body={
                    project.isArchived
                        ? 'Puts the project back in your active lists and lets you order posts on it again.'
                        : 'Hides it from your active lists and blocks new posts. Everything it already holds stays ' +
                          'exactly as it is, and posts already in flight keep running. Reversible at any time.'
                }
                action={
                    <Button
                        variant="secondary"
                        onClick={() => {
                            onLeave();
                            router.post(
                                `/projects/${project.id}/${project.isArchived ? 'restore' : 'archive'}`,
                                {},
                                { preserveScroll: true },
                            );
                        }}
                    >
                        {project.isArchived ? 'Restore' : 'Archive'}
                    </Button>
                }
            />

            <div className="border-t border-subtle pt-5">
                <Row
                    title="Delete project"
                    body={`Removed from your account and recoverable for ${retentionDays} days, then permanent. Finished posts stay readable, because an invoice that references a placement has to keep resolving.`}
                    action={
                        <Button variant="danger" disabled={blocked} onClick={() => setConfirming(true)}>
                            Delete
                        </Button>
                    }
                />

                {blocked && (
                    <div className="mt-4">
                        <Alert
                            tone="warning"
                            title={`${number(blockingPosts.length)} post${blockingPosts.length === 1 ? '' : 's'} on this project ${blockingPosts.length === 1 ? 'has' : 'have'} not finished.`}
                        >
                            {/* Deliberately not "money is held against them":
                                a cancelled post is on this list until it is
                                refunded, and nothing is frozen against it. The
                                sentence has to be true of every row below. */}
                            A post is on this list until it has been placed, rejected or refunded. Wait for{' '}
                            {blockingPosts.length === 1 ? 'it' : 'them'} to settle — or cancel what is still being
                            written — and the project can be deleted.
                        </Alert>

                        <ul className="mt-3 flex flex-col gap-1.5">
                            {blockingPosts.map((post) => (
                                <li key={post.id} className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-sm">
                                    <Link
                                        href={`/posts?post=${post.id}`}
                                        className="num font-medium text-brand hover:underline"
                                    >
                                        #{post.id}
                                    </Link>
                                    <span className="text-ink-900">{post.domain}</span>
                                    {post.anchorText && (
                                        <span className="truncate text-ink-500">{post.anchorText}</span>
                                    )}
                                    <span className="text-ink-500">· {post.statusLabel}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            <Modal
                open={confirming}
                onClose={() => setConfirming(false)}
                title={`Delete “${project.name}”`}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setConfirming(false)}>
                            Keep it
                        </Button>
                        <Button
                            variant="danger"
                            disabled={!matches}
                            onClick={() => {
                                onLeave();
                                router.delete(`/projects/${project.id}`, { data: { name: typed.trim() } });
                            }}
                        >
                            Delete project
                        </Button>
                    </>
                }
            >
                <Alert tone="danger" title={`Recoverable for ${retentionDays} days, then gone.`}>
                    The project leaves your account now. Its finished posts stay readable and stay in your reports.
                </Alert>

                <div className="mt-4">
                    {/* A click can be muscle memory; typing a name cannot. The
                        name is checked again on the server — a confirmation
                        only enforced in the browser is not a confirmation. */}
                    <Input
                        label="Type the project name to confirm"
                        value={typed}
                        onChange={(event) => setTyped(event.target.value)}
                        placeholder={project.name}
                        autoComplete="off"
                        spellCheck={false}
                        hint="Exactly as it appears above."
                    />
                </div>
            </Modal>
        </div>
    );
}

function Row({ title, body, action }: { title: string; body: string; action: React.ReactNode }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
            <div className="min-w-0 max-w-prose flex-1">
                <p className="text-sm font-medium text-ink-900">{title}</p>
                <p className="mt-0.5 text-sm text-ink-500">{body}</p>
            </div>

            <div className="shrink-0">{action}</div>
        </div>
    );
}
