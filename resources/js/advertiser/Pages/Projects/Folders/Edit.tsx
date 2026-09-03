import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Input } from '@shared/ui';
import { AppShell } from '../../../Layouts/AppShell';
import { RichBriefEditor } from '../../../Components/wizard/RichBriefEditor';

interface Props {
    project: { id: number; name: string; publisherTask: string | null };
    /** Null when creating. */
    folder: { id: number; name: string; publisherTask: string | null } | null;
}

/**
 * Add or edit a folder.
 *
 * Its own page rather than a modal: the brief is the substantial field here,
 * it is edited in the same rich editor the wizard uses, and a 5,000-character
 * document does not belong in a dialog.
 *
 * Leaving the brief empty is a real choice, not an omission — the folder then
 * inherits the project's, which is what most folders want — so the field says
 * what empty means instead of nagging for a value.
 */
export default function FolderEdit({ project, folder }: Props) {
    const editing = folder !== null;

    const form = useForm({
        name: folder?.name ?? '',
        publisher_task: folder?.publisherTask ?? '',
    });

    const action = editing ? `/projects/${project.id}/folders/${folder.id}` : `/projects/${project.id}/folders`;

    return (
        <AppShell
            title="Projects"
            crumbs={[
                { label: 'My projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.id}` },
                { label: editing ? folder.name : 'New folder' },
            ]}
        >
            <Head title={editing ? `Edit ${folder.name}` : `New folder — ${project.name}`} />

            <h1 className="font-sora text-xl font-semibold text-ink-900">
                {editing ? `Edit “${folder.name}”` : 'Add a folder'}
            </h1>

            <p className="mt-1 max-w-prose text-sm text-ink-500">
                Folders group the landing pages you are promoting, and can give writers different instructions for each
                group.
            </p>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    if (editing) {
                        form.put(action);
                    } else {
                        form.post(action);
                    }
                }}
                className="mt-5 flex max-w-2xl flex-col gap-5 rounded-card border border-subtle bg-card p-5 shadow-card"
            >
                <Input
                    label="Folder name"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    error={form.errors.name}
                    placeholder="Spring campaign"
                    maxLength={120}
                    required
                    autoFocus
                />

                {/* The editor carries its own label, toolbar and counter — the
                    same one the create wizard uses, so a brief written here and
                    a brief written there behave identically. */}
                <div>
                    <RichBriefEditor
                        value={form.data.publisher_task}
                        onChange={(html) => form.setData('publisher_task', html)}
                        error={form.errors.publisher_task}
                    />

                    <p className="mt-1.5 text-sm text-ink-500">
                        {project.publisherTask
                            ? 'Leave this empty and the folder uses the project’s brief.'
                            : 'Leave this empty and the folder uses the project’s brief, once you write one.'}
                    </p>
                </div>

                <div className="flex justify-end gap-2 border-t border-subtle pt-4">
                    <Link href={`/projects/${project.id}`}>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </Link>

                    <Button type="submit" loading={form.processing}>
                        {editing ? 'Save folder' : 'Add folder'}
                    </Button>
                </div>
            </form>
        </AppShell>
    );
}
