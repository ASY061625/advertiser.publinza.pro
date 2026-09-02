import { useState } from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { Button, ChevronDownIcon, Input, Modal, TrashIcon, useDismiss } from '@shared/ui';
import type { PostFilterState, SavedViewRecord } from '@shared/types/posts';
import { asPayload } from './usePostFilters';

interface Props {
    views: SavedViewRecord[];
    filters: PostFilterState;
    onApply: (filters: PostFilterState) => void;
}

/**
 * Named filter presets.
 *
 * A view stores the query string, not a bespoke filter format, so applying one
 * is the same operation as opening a shared link — there is no second code path
 * that could interpret the same filters differently.
 */
export function SavedViews({ views, filters, onApply }: Props) {
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [name, setName] = useState('');
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    // Sort and page size describe how you read the grid, not what it contains,
    // so they do not count as something worth saving on their own.
    const hasFilters = Object.keys(filters).some((key) => !['sort', 'direction', 'per_page'].includes(key));

    return (
        <div className="flex items-center gap-2">
            <div ref={ref} className="relative">
                <Button variant="secondary" onClick={() => setOpen((value) => !value)} aria-expanded={open}>
                    Saved views
                    <span className="num text-ink-500">{views.length}</span>
                    <ChevronDownIcon
                        size={14}
                        className={cn('transition-transform duration-fast', open && 'rotate-180')}
                    />
                </Button>

                {open && (
                    <div
                        role="menu"
                        className="absolute left-0 z-40 mt-1 w-72 animate-scale-in rounded-card border border-subtle bg-card py-1 shadow-card"
                    >
                        {views.length === 0 ? (
                            <p className="px-3 py-3 text-sm text-ink-500">
                                No saved views yet. Set some filters, then save them here.
                            </p>
                        ) : (
                            views.map((view) => (
                                <div key={view.id} className="flex items-center gap-1 px-1">
                                    <button
                                        type="button"
                                        role="menuitem"
                                        onClick={() => {
                                            onApply(view.query);
                                            setOpen(false);
                                        }}
                                        className="flex-1 truncate rounded-card px-2 py-2 text-left text-base text-ink-700 hover:bg-sunken"
                                    >
                                        {view.name}
                                    </button>

                                    <button
                                        type="button"
                                        aria-label={`Delete view ${view.name}`}
                                        title={`Delete view ${view.name}`}
                                        onClick={() =>
                                            router.delete(`/posts/views/${view.id}`, { preserveScroll: true })
                                        }
                                        className="rounded-button p-1.5 text-ink-500 hover:bg-danger-bg hover:text-danger"
                                    >
                                        <TrashIcon size={14} />
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                )}
            </div>

            <Button variant="ghost" disabled={!hasFilters} onClick={() => setSaving(true)}>
                Save this view
            </Button>

            <Modal
                open={saving}
                onClose={() => setSaving(false)}
                title="Save this view"
                description="The filters you have set now, under a name you choose."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setSaving(false)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={name.trim() === ''}
                            onClick={() => {
                                router.post('/posts/views', asPayload({ ...filters, name: name.trim() }), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setName('');
                                        setSaving(false);
                                    },
                                });
                            }}
                        >
                            Save
                        </Button>
                    </>
                }
            >
                <Input
                    label="Name"
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                    placeholder="Live links, last quarter"
                    hint="Saving over a name you already used replaces that view."
                    maxLength={80}
                />
            </Modal>
        </div>
    );
}
