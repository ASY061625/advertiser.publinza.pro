import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Checkbox, ChevronDownIcon, ChevronRightIcon } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { CartGroup as Group, CartLine } from '@shared/types/cart';
import { CartItemRow } from './CartItemRow';

interface Props {
    group: Group;
    selected: Set<number>;
    onSelect: (id: number, selected: boolean) => void;
    onSelectGroup: (ids: number[], selected: boolean) => void;
    onEdit: (item: CartLine) => void;
}

/**
 * One project's lines, under a header that can be collapsed.
 *
 * Grouping by project is the whole layout decision here. "Am I finished buying
 * for the spring launch" is a question about a group, and the subtotal in the
 * header answers it without opening anything — which is what makes collapsing
 * useful rather than just tidy.
 */
export function CartGroup({ group, selected, onSelect, onSelectGroup, onEdit }: Props) {
    const [open, setOpen] = useState(true);
    const ids = group.items.map((item) => item.id);
    const allSelected = ids.length > 0 && ids.every((id) => selected.has(id));

    return (
        <section className="overflow-hidden rounded-card border border-subtle bg-card shadow-card">
            <header className="flex items-center gap-3 border-b border-subtle px-4 py-3">
                <Checkbox
                    label={`Select every line in ${group.project?.name ?? 'the ungrouped lines'}`}
                    hideLabel
                    checked={allSelected}
                    onChange={(event) => onSelectGroup(ids, event.target.checked)}
                />

                {/* The name wraps rather than truncates. "Northwin…" on a
                    phone identifies nothing, and a second line costs less than
                    a group header that cannot name its group. The chevron stays
                    on the first line beside it rather than wrapping above. */}
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    aria-expanded={open}
                    className="flex min-w-0 flex-1 items-start gap-2 text-left"
                >
                    <span className="mt-1 shrink-0 text-ink-500">
                        {open ? <ChevronDownIcon size={16} /> : <ChevronRightIcon size={16} />}
                    </span>

                    <span className="min-w-0 flex-1">
                        {group.project ? (
                            <span className="font-sora text-md font-semibold text-ink-900">
                                <span
                                    aria-hidden="true"
                                    className="mr-2 inline-block size-2 rounded-full align-middle"
                                    style={{ backgroundColor: group.project.color ?? 'var(--ink-300)' }}
                                />
                                {group.project.name}
                            </span>
                        ) : (
                            <span className="font-sora text-md font-semibold text-ink-500">
                                No project chosen
                            </span>
                        )}

                        <span className="num ml-2 whitespace-nowrap text-sm text-ink-500">
                            {group.itemCount} {group.itemCount === 1 ? 'site' : 'sites'}
                        </span>
                    </span>
                </button>

                <span className="num shrink-0 font-sora text-md font-semibold text-ink-900">
                    {money(group.subtotalCents)}
                </span>
            </header>

            {/* An ungrouped line cannot be checked out into a project, so the
                fix is offered here rather than left for the buyer to discover
                at the confirm step. */}
            {group.project === null && open && (
                <p className="border-b border-subtle bg-warning-bg px-4 py-2 text-sm text-ink-700">
                    These lines have no project yet. Select them and use{' '}
                    <span className="font-medium">Move to project</span>, or open{' '}
                    <Link href="/catalog" className="font-medium text-brand hover:underline">
                        the catalog
                    </Link>{' '}
                    with a project chosen.
                </p>
            )}

            {open && (
                <ul className="divide-y divide-subtle">
                    {group.items.map((item) => (
                        <CartItemRow
                            key={item.id}
                            item={item}
                            selected={selected.has(item.id)}
                            onSelect={onSelect}
                            onEdit={onEdit}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}
