import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button, Input, Modal, Select, Switch } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { CartLine, CartProject } from '@shared/types/cart';

interface Props {
    item: CartLine | null;
    projects: CartProject[];
    onClose: () => void;
}

/**
 * The configuration popover, reopened on a line already in the cart.
 *
 * Same five choices as the catalog's add-to-cart, because they are the same
 * five choices — a second, subtly different form for editing is how a folder
 * that can be set on the way in turns out to be unchangeable afterwards.
 *
 * The fee figures come from the line the server priced, so what this shows is
 * what will be charged rather than a second calculation of it.
 */
export function EditItemModal({ item, projects, onClose }: Props) {
    if (item === null) return null;

    return <Editor key={item.id} item={item} projects={projects} onClose={onClose} />;
}

function Editor({ item, projects, onClose }: { item: CartLine; projects: CartProject[]; onClose: () => void }) {
    const form = useForm({
        service_type: item.serviceType,
        content_mode: item.contentMode,
        project_id: item.projectId === null ? '' : String(item.projectId),
        folder_id: item.folder === null ? '' : String(item.folder.id),
        anchor_text: item.anchorText ?? '',
        target_url: item.targetUrl ?? '',
        express: item.express,
    });

    const project = projects.find((entry) => String(entry.id) === form.data.project_id) ?? null;
    const publisherWrites = form.data.content_mode === 'publisher_writes';

    const pages = useMemo(
        () =>
            (project?.landingPages ?? []).filter(
                (page) => form.data.folder_id === '' || String(page.folderId ?? '') === form.data.folder_id,
            ),
        [form.data.folder_id, project],
    );

    // Starts on the saved list when the line already points at one of them, and
    // on the manual fields otherwise — which is where a line configured by hand
    // was left, and where an unconfigured line needs to be.
    const [manual, setManual] = useState(
        () => !(project?.landingPages ?? []).some((page) => page.url === item.targetUrl),
    );

    // What the publisher offers, not what this line is currently charged: the
    // express switch has to show its price while express is still off.
    const writingFee = item.fees.writingCents;
    const expressFee = item.fees.expressCents;

    function save() {
        // Empty strings mean "none" in a select and null in the payload. The
        // transform runs on submit rather than on change, so the selects stay
        // controlled by strings and the server never sees "".
        form.transform((data) => ({
            ...data,
            project_id: data.project_id === '' ? null : Number(data.project_id),
            folder_id: data.folder_id === '' ? null : Number(data.folder_id),
        }));

        form.patch(`/cart/${item.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <Modal
            open
            onClose={onClose}
            title={`Configure ${item.website.domain}`}
            description="Changing any of these re-prices the line."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        loading={form.processing}
                        onClick={save}
                    >
                        Save changes
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-3">
                <Select
                    label="Project"
                    value={form.data.project_id}
                    error={form.errors.project_id}
                    onChange={(event) => {
                        form.setData('project_id', event.target.value);
                        // A folder belongs to one project, so it cannot survive
                        // the move. Left set, the post would be filed into a
                        // folder in a project it is no longer part of.
                        form.setData('folder_id', '');
                    }}
                    options={[
                        { value: '', label: 'No project' },
                        ...projects.map((entry) => ({ value: String(entry.id), label: entry.name })),
                    ]}
                />

                {project !== null && project.folders.length > 0 && (
                    <Select
                        label="Folder"
                        value={form.data.folder_id}
                        onChange={(event) => form.setData('folder_id', event.target.value)}
                        options={[
                            { value: '', label: 'No folder' },
                            ...project.folders.map((folder) => ({
                                value: String(folder.id),
                                label: folder.name,
                            })),
                        ]}
                    />
                )}

                <fieldset className="flex flex-col gap-2">
                    <legend className="mb-1 text-sm font-medium text-ink-700">Content</legend>

                    {(
                        [
                            ['advertiser_provides', 'I provide the article', 0],
                            ['publisher_writes', 'The publisher writes it', writingFee],
                        ] as const
                    ).map(([value, label, fee]) => (
                        <label key={value} className="flex cursor-pointer items-center gap-2 text-base text-ink-700">
                            <input
                                type="radio"
                                name={`content-mode-${item.id}`}
                                checked={form.data.content_mode === value}
                                onChange={() => form.setData('content_mode', value)}
                                className="size-4 accent-[color:var(--brand-blue)]"
                            />
                            <span>{label}</span>
                            {fee > 0 && value === 'publisher_writes' && (
                                <span className="num ml-auto text-sm text-ink-500">+{money(fee)}</span>
                            )}
                        </label>
                    ))}
                </fieldset>

                {pages.length > 0 && !manual ? (
                    <Select
                        label="Landing page"
                        value={form.data.target_url}
                        onChange={(event) => {
                            const page = pages.find((entry) => entry.url === event.target.value);
                            if (page) {
                                form.setData('target_url', page.url);
                                form.setData('anchor_text', page.anchorText);
                            }
                        }}
                        options={pages.map((page) => ({ value: page.url, label: page.anchorText }))}
                    />
                ) : (
                    <>
                        <Input
                            label="Anchor text"
                            value={form.data.anchor_text}
                            error={form.errors.anchor_text}
                            onChange={(event) => form.setData('anchor_text', event.target.value)}
                        />
                        <Input
                            label="Target URL"
                            type="url"
                            placeholder="https://"
                            value={form.data.target_url}
                            error={form.errors.target_url}
                            onChange={(event) => form.setData('target_url', event.target.value)}
                        />
                    </>
                )}

                {pages.length > 0 && (
                    <button
                        type="button"
                        onClick={() => setManual((value) => !value)}
                        className="self-start text-sm text-brand underline-offset-2 hover:underline"
                    >
                        {manual ? 'Choose a saved landing page' : 'Enter one manually'}
                    </button>
                )}

                {expressFee > 0 && (
                    <Switch
                        label="Express delivery"
                        hint={`+${money(expressFee)}`}
                        checked={form.data.express}
                        onCheckedChange={(next) => form.setData('express', next)}
                    />
                )}

                {publisherWrites && (
                    <p className="text-sm text-ink-500">
                        The publisher writes and publishes the article. You will not be asked for copy at
                        checkout.
                    </p>
                )}
            </div>
        </Modal>
    );
}
