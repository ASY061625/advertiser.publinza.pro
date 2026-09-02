import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Alert, Button, Input, Modal } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { ProjectRow } from '@shared/types/projects';

interface Props {
    project: ProjectRow | null;
    onClose: () => void;
}

/**
 * Deleting asks for the project's name to be typed.
 *
 * A click can be muscle memory; typing a name cannot. The name is checked again
 * on the server, because a confirmation only enforced in the browser is not a
 * confirmation.
 *
 * When the project still has work in flight the dialog says so before anything
 * is typed, rather than letting someone type the name and then refusing. The
 * server refuses too — this is the courtesy, not the guard.
 */
export function DeleteProjectDialog({ project, onClose }: Props) {
    const [typed, setTyped] = useState('');

    useEffect(() => setTyped(''), [project]);

    if (project === null) {
        return <Modal open={false} onClose={onClose} title="" />;
    }

    const inFlight = project.posts.new + project.posts.progress + project.posts.frozen;
    const blocked = inFlight > 0;
    const matches = typed.trim() === project.name;

    return (
        <Modal
            open
            onClose={onClose}
            title={`Delete “${project.name}”`}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Keep it
                    </Button>
                    <Button
                        variant="danger"
                        disabled={blocked || !matches}
                        onClick={() =>
                            router.delete(`/projects/${project.id}`, {
                                data: { name: typed.trim() },
                                preserveScroll: true,
                                onSuccess: onClose,
                            })
                        }
                    >
                        Delete project
                    </Button>
                </>
            }
        >
            {blocked ? (
                <Alert tone="warning" title="This project still has work in progress.">
                    {number(inFlight)} post{inFlight === 1 ? '' : 's'} on “{project.name}”{' '}
                    {inFlight === 1 ? 'is' : 'are'} still being written, reviewed or verified, and money is held against{' '}
                    {inFlight === 1 ? 'it' : 'them'}. Cancel {inFlight === 1 ? 'it' : 'them'} or wait for{' '}
                    {inFlight === 1 ? 'it' : 'them'} to finish, then delete the project.
                </Alert>
            ) : (
                <>
                    <Alert tone="danger" title="This cannot be undone.">
                        The project is removed from your account. Its finished posts stay readable, because an invoice
                        that references a placement has to keep resolving.
                    </Alert>

                    <div className="mt-4">
                        <Input
                            label={`Type the project name to confirm`}
                            value={typed}
                            onChange={(event) => setTyped(event.target.value)}
                            placeholder={project.name}
                            autoComplete="off"
                            spellCheck={false}
                            hint="Exactly as it appears above."
                        />
                    </div>
                </>
            )}
        </Modal>
    );
}
