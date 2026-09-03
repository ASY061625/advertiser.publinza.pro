import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { number } from '@shared/lib/format';
import { Alert, Button, FolderIcon, Modal, Tooltip, TrashIcon } from '@shared/ui';
import type { ProjectFolderRow } from '@shared/types/projects';

interface Props {
    projectId: number;
    folders: ProjectFolderRow[];
    /** Archived projects are read-only, so the actions are not offered. */
    readOnly?: boolean;
    /** The folder the editor just saved, pointed at for a couple of seconds. */
    highlightId?: number | null;
}

/**
 * The project's folders: what each groups, and what it tells writers.
 *
 * A folder is only obviously useful once you have two, which is why a project
 * sitting on the one it was created with gets a line explaining what a second
 * would be for rather than an empty-looking section it will never touch.
 *
 * Delete is disabled — with the reason on the button — when posts are still in
 * flight against the folder. The server refuses too; this is the courtesy, not
 * the guard.
 */
export function FoldersSection({ projectId, folders, readOnly = false, highlightId = null }: Props) {
    const [confirming, setConfirming] = useState<ProjectFolderRow | null>(null);
    const onlyGeneral = folders.length === 1;

    // Long enough to catch the eye of someone arriving from the editor, short
    // enough that it is not still glowing when they come back tomorrow.
    const [lit, setLit] = useState<number | null>(highlightId);

    useEffect(() => {
        setLit(highlightId);

        if (highlightId === null) return;

        const timer = window.setTimeout(() => setLit(null), 2200);

        return () => window.clearTimeout(timer);
    }, [highlightId]);

    return (
        <section
            aria-labelledby="folders-heading"
            className="rounded-card border border-subtle bg-card p-5 shadow-card"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="folders-heading" className="font-sora text-md font-semibold text-ink-900">
                    Folders
                </h2>

                {!readOnly && (
                    <Link href={`/projects/${projectId}/folders/create`}>
                        <Button variant="secondary" size="sm">
                            Add folder
                        </Button>
                    </Link>
                )}
            </div>

            <ul className="mt-4 flex flex-col gap-2">
                {folders.map((folder) => (
                    <li
                        key={folder.id}
                        className={cn(
                            'flex flex-wrap items-start justify-between gap-x-4 gap-y-2 rounded-card',
                            'border px-4 py-3 transition-colors duration-drawer ease-standard',
                            lit === folder.id ? 'border-brand bg-brand-subtle' : 'border-subtle bg-canvas',
                        )}
                    >
                        <div className="flex min-w-0 flex-1 items-start gap-3">
                            <span className="mt-0.5 shrink-0 text-ink-500">
                                <FolderIcon size={16} />
                            </span>

                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-ink-900">{folder.name}</p>

                                <p className="mt-0.5 text-xs text-ink-500">
                                    <span className="num">{number(folder.landingPages)}</span> landing page
                                    {folder.landingPages === 1 ? '' : 's'} ·{' '}
                                    <span className="num">{number(folder.posts)}</span> post
                                    {folder.posts === 1 ? '' : 's'}
                                </p>

                                {folder.taskExcerpt !== null && (
                                    <p className="mt-1 truncate text-xs text-ink-500">{folder.taskExcerpt}</p>
                                )}
                            </div>
                        </div>

                        {!readOnly && (
                            <div className="flex shrink-0 items-center gap-1">
                                <Link
                                    href={`/projects/${projectId}/folders/${folder.id}/edit`}
                                    className={cn(
                                        'rounded-button px-2 py-1 text-sm text-ink-500',
                                        'transition-colors duration-fast ease-standard hover:bg-sunken hover:text-ink-900',
                                    )}
                                >
                                    Edit
                                </Link>

                                <DeleteButton
                                    folder={folder}
                                    onlyFolder={onlyGeneral}
                                    onConfirm={() => setConfirming(folder)}
                                />
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            {onlyGeneral && (
                <p className="mt-3 text-sm text-ink-500">
                    Everything in this project lives in{' '}
                    <span className="font-medium text-ink-700">{folders[0]?.name}</span> for now. Add a folder when you
                    want to promote a different landing page, or run a second campaign, without the two sharing one
                    brief — each folder keeps its own pages and its own instructions for writers.
                </p>
            )}

            <Modal
                open={confirming !== null}
                onClose={() => setConfirming(null)}
                title={confirming === null ? '' : `Delete “${confirming.name}”`}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setConfirming(null)}>
                            Keep it
                        </Button>
                        <Button
                            variant="danger"
                            onClick={() => {
                                if (confirming === null) return;

                                router.delete(`/projects/${projectId}/folders/${confirming.id}`, {
                                    preserveScroll: true,
                                    onFinish: () => setConfirming(null),
                                });
                            }}
                        >
                            Delete folder
                        </Button>
                    </>
                }
            >
                <Alert tone="warning" title="Finished posts keep their history.">
                    The folder’s writer instructions go with it. Posts already completed under it stay readable and stay
                    in your reports — they simply stop belonging to a folder.
                </Alert>
            </Modal>
        </section>
    );
}

function DeleteButton({
    folder,
    onlyFolder,
    onConfirm,
}: {
    folder: ProjectFolderRow;
    onlyFolder: boolean;
    onConfirm: () => void;
}) {
    // Every reason is spelled out. "Delete is greyed out" with no explanation
    // is the worst version of this control.
    const reason = onlyFolder
        ? 'This is the project’s only folder, and every landing page has to live in one. Add another first.'
        : folder.activePosts > 0
          ? `${folder.activePosts} post${folder.activePosts === 1 ? '' : 's'} ${
                folder.activePosts === 1 ? 'is' : 'are'
            } still being written against this folder’s brief. Wait for ${
                folder.activePosts === 1 ? 'it' : 'them'
            } to finish or cancel ${folder.activePosts === 1 ? 'it' : 'them'} first.`
          : folder.landingPages > 0
            ? `Move this folder’s ${folder.landingPages} landing page${
                  folder.landingPages === 1 ? '' : 's'
              } elsewhere first — deleting the folder would leave ${
                  folder.landingPages === 1 ? 'it' : 'them'
              } with no brief.`
            : null;

    const button = (
        <button
            type="button"
            aria-label={`Delete ${folder.name}`}
            aria-disabled={reason !== null}
            onClick={() => reason === null && onConfirm()}
            className={cn(
                'rounded-button p-1.5 transition-colors duration-fast ease-standard',
                reason === null
                    ? 'text-ink-500 hover:bg-danger-bg hover:text-danger'
                    : 'cursor-not-allowed text-ink-300',
            )}
        >
            <TrashIcon size={15} />
        </button>
    );

    return <Tooltip content={reason ?? `Delete ${folder.name}`}>{button}</Tooltip>;
}
