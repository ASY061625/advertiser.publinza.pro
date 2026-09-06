import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Dropdown, Input, ListIcon, Modal, PlusIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type { WishlistSummary } from '@shared/types/lists';

interface Props {
    lists: WishlistSummary[];
    activeId: number | null;
    onSelect: (id: number) => void;
}

/**
 * The lists themselves, down the left.
 *
 * Named lists rather than one flat wishlist because a flat one stops being
 * useful past about thirty sites: "Q3 finance push" and "backup options" are
 * different plans, and a single list forces somebody to hold the difference in
 * their head while they read it.
 */
export function WishlistSidebar({ lists, activeId, onSelect }: Props) {
    const [creating, setCreating] = useState(false);
    const [renaming, setRenaming] = useState<WishlistSummary | null>(null);
    const [deleting, setDeleting] = useState<WishlistSummary | null>(null);

    return (
        <>
            <nav aria-label="Wishlists" className="flex flex-col gap-1">
                {lists.map((list) => (
                    <div
                        key={list.id}
                        className={cn(
                            'group flex items-center gap-1 rounded-button pr-1',
                            list.id === activeId ? 'bg-brand-subtle' : 'hover:bg-sunken',
                        )}
                    >
                        <button
                            type="button"
                            onClick={() => onSelect(list.id)}
                            aria-current={list.id === activeId ? 'true' : undefined}
                            className="flex min-w-0 flex-1 items-center gap-2 px-3 py-2 text-left"
                        >
                            <ListIcon
                                size={14}
                                className={cn('shrink-0', list.id === activeId ? 'text-brand' : 'text-ink-500')}
                            />
                            <span
                                className={cn(
                                    'truncate text-base',
                                    list.id === activeId ? 'font-medium text-ink-900' : 'text-ink-700',
                                )}
                            >
                                {list.name}
                            </span>
                            <span className="num ml-auto shrink-0 text-sm text-ink-500">{list.itemCount}</span>
                        </button>

                        <Dropdown
                            trigger={
                                <button
                                    type="button"
                                    aria-label={`Manage ${list.name}`}
                                    className="rounded-button px-1.5 py-1 text-ink-500 opacity-0 hover:bg-card focus-visible:opacity-100 group-hover:opacity-100"
                                >
                                    ⋯
                                </button>
                            }
                            items={[
                                { id: 'rename', label: 'Rename', onSelect: () => setRenaming(list) },
                                {
                                    id: 'duplicate',
                                    label: 'Duplicate',
                                    onSelect: () =>
                                        router.post(
                                            `/lists/wishlists/${list.id}/duplicate`,
                                            {},
                                            { preserveScroll: true, preserveState: false },
                                        ),
                                },
                                {
                                    id: 'delete',
                                    label: 'Delete',
                                    destructive: true,
                                    onSelect: () => setDeleting(list),
                                },
                            ]}
                        />
                    </div>
                ))}

                <Button variant="secondary" size="sm" className="mt-2" onClick={() => setCreating(true)}>
                    <PlusIcon size={14} />
                    New list
                </Button>
            </nav>

            {creating && (
                <NameDialog
                    title="New wishlist"
                    description="Name it for the campaign it is for — that is what makes a second list worth having."
                    initial=""
                    submitLabel="Create"
                    onClose={() => setCreating(false)}
                    onSubmit={(name, done) =>
                        router.post(
                            '/lists/wishlists',
                            { name },
                            { preserveScroll: true, preserveState: false, onFinish: done },
                        )
                    }
                />
            )}

            {renaming !== null && (
                <NameDialog
                    title={`Rename ${renaming.name}`}
                    initial={renaming.name}
                    submitLabel="Save"
                    onClose={() => setRenaming(null)}
                    onSubmit={(name, done) =>
                        router.patch(
                            `/lists/wishlists/${renaming.id}`,
                            { name },
                            { preserveScroll: true, preserveState: false, onFinish: done },
                        )
                    }
                />
            )}

            <Modal
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                size="sm"
                title={`Delete ${deleting?.name ?? ''}?`}
                description="The sites stay in the catalog. Only the shortlist goes."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setDeleting(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant="danger"
                            onClick={() =>
                                router.delete(`/lists/wishlists/${deleting?.id}`, {
                                    preserveScroll: true,
                                    preserveState: false,
                                    onSuccess: () => setDeleting(null),
                                })
                            }
                        >
                            Delete the list
                        </Button>
                    </>
                }
            >
                <p className="num text-base text-ink-700">
                    {deleting?.itemCount ?? 0} {deleting?.itemCount === 1 ? 'site is' : 'sites are'} on it.
                </p>
            </Modal>
        </>
    );
}

/**
 * One text field and two buttons, shared by create and rename.
 *
 * Mounted only while open, so `initial` is a real initial value rather than a
 * prop the field has to be kept in sync with.
 */
function NameDialog({
    title,
    description,
    initial,
    submitLabel,
    onClose,
    onSubmit,
}: {
    title: string;
    description?: string;
    initial: string;
    submitLabel: string;
    onClose: () => void;
    onSubmit: (name: string, done: () => void) => void;
}) {
    const [name, setName] = useState(initial);
    const [saving, setSaving] = useState(false);

    function submit() {
        if (name.trim() === '') return;

        setSaving(true);
        onSubmit(name.trim(), () => {
            setSaving(false);
            onClose();
        });
    }

    return (
        <Modal
            open
            onClose={onClose}
            size="sm"
            title={title}
            description={description}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button loading={saving} disabled={name.trim() === ''} onClick={submit}>
                        {submitLabel}
                    </Button>
                </>
            }
        >
            <Input
                label="List name"
                value={name}
                autoFocus
                onChange={(event) => setName(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submit();
                    }
                }}
                placeholder="Q3 finance push"
            />
        </Modal>
    );
}
