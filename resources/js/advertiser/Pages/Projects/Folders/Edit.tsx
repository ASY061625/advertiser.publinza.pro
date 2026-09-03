import { Head, Link, router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { Alert, Button, Input, Modal, Tooltip } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { LandingPageRow } from '@shared/types/wizard';
import type { FolderEditorFolder, FolderEditorPage, FolderEditorProject } from '@shared/types/projects';
import { AppShell } from '../../../Layouts/AppShell';
import { RichBriefEditor } from '../../../Components/wizard/RichBriefEditor';
import { LandingPageEditor } from '../../../Components/projects/LandingPageEditor';

interface Props {
    project: FolderEditorProject;
    /** Null when adding a folder rather than editing one. */
    folder: FolderEditorFolder | null;
    landingPages: FolderEditorPage[];
}

const MAX_NAME = 80;

/**
 * One folder: its name, the brief its posts are written against, and the
 * landing pages inside it.
 *
 * Its own page rather than a modal. The brief runs to three thousand characters
 * and the page list is drag-reorderable — neither belongs in a dialog, and both
 * are things people come here specifically to do.
 *
 * Two rules the form enforces and the server enforces again: a landing page that
 * posts already point at cannot be removed, and a folder that posts are still
 * being written against cannot be deleted. The tooltips here are the courtesy;
 * SaveFolder and DeleteFolder are the guards.
 */
export default function FolderEdit({ project, folder, landingPages }: Props) {
    const editing = folder !== null;
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const form = useForm({
        name: folder?.name ?? '',
        publisher_task: folder?.publisherTask ?? '',
        landing_pages: landingPages.map((page): LandingPageRow & { id?: number } => ({
            key: page.key,
            id: page.id,
            anchor_text: page.anchor_text,
            url: page.url,
        })),
    });

    // Usage travels by row key, so a drag reorders the rows without the counts
    // following the wrong ones.
    const usage = useMemo(() => Object.fromEntries(landingPages.map((page) => [page.key, page.usage])), [landingPages]);

    const action = editing ? `/projects/${project.id}/folders/${folder.id}` : `/projects/${project.id}/folders`;

    // `isDirty` alone would fire the moment a row is added and then removed
    // again. Comparing against what was loaded is what "unsaved changes"
    // actually means.
    const initial = useRef(JSON.stringify(form.data));
    const dirty = JSON.stringify(form.data) !== initial.current;

    const submit = useCallback(() => {
        if (form.processing) return;

        // Cleared before the request, not after: a successful save redirects
        // away, and the unload guard must already be off by then.
        initial.current = JSON.stringify(form.data);

        if (editing) form.put(action, { preserveScroll: true });
        else form.post(action, { preserveScroll: true });
    }, [action, editing, form]);

    useUnsavedChangesGuard(dirty);
    useSaveShortcut(submit);

    const blockedReason = deleteBlockedReason(folder);

    return (
        <AppShell
            title="Projects"
            crumbs={[
                { label: 'My projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.id}` },
                { label: 'Folders', href: `/projects/${project.id}` },
                { label: editing ? folder.name : 'New folder' },
            ]}
        >
            <Head title={editing ? `Edit ${folder.name}` : `New folder — ${project.name}`} />

            {/* pb-24 clears the sticky bar, so the last field is never sitting
                underneath it. */}
            <div className="mx-auto w-full max-w-[760px] pb-24">
                <h1 className="font-sora text-xl font-semibold text-ink-900">
                    {editing ? `Edit “${folder.name}”` : 'Add a folder'}
                </h1>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                    className="mt-5 flex flex-col gap-6 rounded-card border border-subtle bg-card p-5 shadow-card"
                >
                    {form.errors.landing_pages && (
                        <Alert tone="danger" title="Nothing was saved.">
                            {form.errors.landing_pages}
                        </Alert>
                    )}

                    <Input
                        label="Folder name"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value.slice(0, MAX_NAME))}
                        error={form.errors.name}
                        hint="Use folders to group landing pages by campaign or by section of your site."
                        placeholder="Spring campaign"
                        maxLength={MAX_NAME}
                        required
                        autoFocus={!editing}
                    />

                    <div>
                        <RichBriefEditor
                            id="folder-task"
                            label="Task for the publisher"
                            hint="These instructions are sent to the writer with every post in this folder."
                            value={form.data.publisher_task}
                            onChange={(html) => form.setData('publisher_task', html)}
                            error={form.errors.publisher_task}
                            // Only offered when there is something to copy.
                            onCopyFromProject={
                                project.publisherTask
                                    ? () => form.setData('publisher_task', project.publisherTask ?? '')
                                    : undefined
                            }
                        />

                        <p className="mt-1.5 text-sm text-ink-500">
                            Leave this empty and the folder uses the project’s brief.
                        </p>
                    </div>

                    <div className="border-t border-subtle pt-6">
                        <LandingPageEditor
                            rows={form.data.landing_pages}
                            onChange={(rows) => form.setData('landing_pages', rows)}
                            websiteUrl={project.websiteUrl}
                            errors={form.errors}
                            usage={usage}
                        />
                    </div>
                </form>
            </div>

            {/* The action bar follows the page rather than sitting at the end of
                a long form, because Delete and Save are both things you reach
                for without having scrolled to the bottom first. */}
            <div
                className={cn(
                    'sticky bottom-0 z-20 -mx-4 -mb-6 flex flex-wrap items-center justify-between gap-3',
                    'border-t border-subtle bg-card px-4 py-3 lg:-mx-6 lg:px-6',
                )}
            >
                <div className="flex items-center gap-3">
                    {editing && (
                        <Tooltip content={blockedReason ?? `Delete “${folder.name}”`}>
                            <button
                                type="button"
                                aria-disabled={blockedReason !== null}
                                onClick={() => blockedReason === null && setConfirmingDelete(true)}
                                className={cn(
                                    'rounded-button px-2 py-1 text-sm font-medium transition-colors duration-fast',
                                    blockedReason === null
                                        ? 'text-danger hover:bg-danger-bg'
                                        : 'cursor-not-allowed text-ink-300',
                                )}
                            >
                                Delete folder
                            </button>
                        </Tooltip>
                    )}

                    {dirty && <span className="text-sm text-ink-500">Unsaved changes</span>}
                </div>

                <div className="flex items-center gap-2">
                    <Link href={`/projects/${project.id}`}>
                        <Button variant="ghost" type="button">
                            Cancel
                        </Button>
                    </Link>

                    <Button type="button" onClick={submit} loading={form.processing}>
                        {editing ? 'Save changes' : 'Add folder'}
                    </Button>
                </div>
            </div>

            <Modal
                open={confirmingDelete}
                onClose={() => setConfirmingDelete(false)}
                title={folder === null ? '' : `Delete “${folder.name}”`}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setConfirmingDelete(false)}>
                            Keep it
                        </Button>
                        <Button
                            variant="danger"
                            onClick={() => {
                                if (folder === null) return;

                                initial.current = JSON.stringify(form.data);
                                router.delete(`/projects/${project.id}/folders/${folder.id}`);
                            }}
                        >
                            Delete folder
                        </Button>
                    </>
                }
            >
                <Alert tone="warning" title="Finished posts keep their history.">
                    The folder’s landing pages and its instructions for writers go with it. Posts already completed
                    under it stay readable and stay in your reports — they simply stop belonging to a folder.
                </Alert>
            </Modal>
        </AppShell>
    );
}

/** Why Delete is refused, in a sentence, or null when it is allowed. */
function deleteBlockedReason(folder: FolderEditorFolder | null): string | null {
    if (folder === null) return 'There is nothing to delete yet.';

    if (folder.isOnlyFolder) {
        return 'This is the project’s only folder, and every landing page has to live in one. Add another first.';
    }

    if (folder.activePosts > 0) {
        const n = folder.activePosts;

        return (
            `${number(n)} post${n === 1 ? '' : 's'} ${n === 1 ? 'is' : 'are'} still being written against this ` +
            `folder’s brief. Wait for ${n === 1 ? 'it' : 'them'} to finish or cancel ${n === 1 ? 'it' : 'them'} first.`
        );
    }

    return null;
}

/**
 * The browser's own "leave site?" dialog for a hard navigation, and Inertia's
 * before-visit hook for a soft one.
 *
 * Both are needed: beforeunload does nothing for a client-side visit, and
 * Inertia's hook does nothing for a closed tab.
 */
function useUnsavedChangesGuard(dirty: boolean): void {
    useEffect(() => {
        if (!dirty) return;

        function onBeforeUnload(event: BeforeUnloadEvent) {
            event.preventDefault();
            // Assigned for the browsers that still require it; the text itself
            // has not been shown by anything since 2017.
            event.returnValue = '';
        }

        window.addEventListener('beforeunload', onBeforeUnload);

        const stop = router.on('before', (event) => {
            // The save itself is a visit. Only navigations away are questioned.
            if (event.detail.visit.method !== 'get') return;

            if (!window.confirm('You have unsaved changes to this folder. Leave without saving?')) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            stop();
        };
    }, [dirty]);
}

/** Cmd/Ctrl+S saves, as it does in every editor people already use. */
function useSaveShortcut(submit: () => void): void {
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key !== 's' || !(event.metaKey || event.ctrlKey)) return;

            event.preventDefault();
            submit();
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [submit]);
}
