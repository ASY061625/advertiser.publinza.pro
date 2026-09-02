import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Button, Modal, Select } from '@shared/ui';
import type { PostRow, ProjectOption } from '@shared/types/posts';

interface Props {
    post: PostRow | null;
    projects: ProjectOption[];
    onClose: () => void;
}

/**
 * Copies a post's brief into another project.
 *
 * The copy is always a draft: duplicating a live placement must not produce a
 * second one that claims to be live, and nothing is charged until checkout.
 * The dialog says so, because "Duplicate" on its own does not.
 */
export function DuplicateDialog({ post, projects, onClose }: Props) {
    const [projectId, setProjectId] = useState('');
    const [folderId, setFolderId] = useState('');

    // Default to a project that is not the one it is already in — the common
    // case for this action is moving a brief somewhere else.
    useEffect(() => {
        if (post === null) return;

        const other = projects.find((project) => project.id !== post.projectId) ?? projects[0];

        setProjectId(other ? String(other.id) : '');
        setFolderId('');
    }, [post, projects]);

    const folders = projects.find((project) => String(project.id) === projectId)?.folders ?? [];

    return (
        <Modal
            open={post !== null}
            onClose={onClose}
            title={post === null ? '' : `Duplicate post #${post.id}`}
            description="Creates a new draft with the same website, anchor and target."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        disabled={projectId === ''}
                        onClick={() => {
                            if (post === null) return;

                            router.post(
                                `/posts/${post.id}/duplicate`,
                                {
                                    project_id: Number(projectId),
                                    folder_id: folderId === '' ? null : Number(folderId),
                                },
                                { preserveScroll: true, onSuccess: onClose },
                            );
                        }}
                    >
                        Duplicate
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <Select
                    label="Project"
                    value={projectId}
                    onChange={(event) => {
                        setProjectId(event.target.value);
                        setFolderId('');
                    }}
                    options={projects.map((project) => ({ value: String(project.id), label: project.name }))}
                />

                <Select
                    label="Folder"
                    value={folderId}
                    onChange={(event) => setFolderId(event.target.value)}
                    options={[
                        { value: '', label: 'No folder' },
                        ...folders.map((folder) => ({ value: String(folder.id), label: folder.name })),
                    ]}
                    hint="Only folders belonging to the project above."
                />

                <p className="rounded-card bg-sunken px-3 py-2 text-sm text-ink-700">
                    The copy starts as a draft. Nothing is charged until you check it out.
                </p>
            </div>
        </Modal>
    );
}
