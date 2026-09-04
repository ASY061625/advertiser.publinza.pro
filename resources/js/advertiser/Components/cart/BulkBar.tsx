import { router } from '@inertiajs/react';
import { Button, Checkbox, Dropdown, TrashIcon } from '@shared/ui';
import type { CartProject } from '@shared/types/cart';

interface Props {
    total: number;
    selected: Set<number>;
    projects: CartProject[];
    onSelectAll: (selected: boolean) => void;
}

/**
 * Select all, remove selected, move selected.
 *
 * The two destructive-ish controls stay visible and disabled rather than
 * appearing when something is selected. A toolbar that changes shape on
 * selection makes the buttons move under the pointer at exactly the moment
 * somebody is reaching for one.
 */
export function BulkBar({ total, selected, projects, onSelectAll }: Props) {
    const count = selected.size;
    const ids = [...selected];

    function bulk(payload: Record<string, unknown>) {
        router.post('/cart/bulk', { ids, ...payload }, { preserveScroll: true, preserveState: false });
    }

    return (
        <div className="flex flex-wrap items-center gap-3 rounded-card border border-subtle bg-card px-4 py-2.5 shadow-card">
            <Checkbox
                label={count > 0 ? `${count} selected` : 'Select all'}
                checked={count > 0 && count === total}
                indeterminate={count > 0 && count < total}
                onChange={(event) => onSelectAll(event.target.checked)}
            />

            <span className="ml-auto flex items-center gap-2">
                <Button
                    size="sm"
                    variant="secondary"
                    disabled={count === 0}
                    onClick={() => bulk({ action: 'remove' })}
                >
                    <TrashIcon size={14} />
                    Remove
                </Button>

                <Dropdown
                    trigger={
                        <Button size="sm" variant="secondary" disabled={count === 0 || projects.length === 0}>
                            Move to project
                        </Button>
                    }
                    items={projects.map((project) => ({
                        id: String(project.id),
                        label: project.name,
                        onSelect: () => bulk({ action: 'move', project_id: project.id }),
                    }))}
                />
            </span>
        </div>
    );
}
