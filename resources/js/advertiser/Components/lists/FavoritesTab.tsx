import { router } from '@inertiajs/react';
import { Button, CartIcon, EmptyState, HeartIcon, Select, Tooltip } from '@shared/ui';
import { date } from '@shared/lib/format';
import type { CatalogRangeSet } from '@shared/types/catalog';
import type { ListFilters, ListRow, WishlistSummary } from '@shared/types/lists';
import { CatalogTable } from '../catalog/CatalogTable';
import { ListToolbar } from './ListToolbar';
import { RemoveButton, RowMenu } from './RowMenu';

interface Props {
    rows: ListRow[];
    ranges: CatalogRangeSet;
    filters: ListFilters;
    categories: { id: number; name: string }[];
    sorts: { value: string; label: string }[];
    wishlists: WishlistSummary[];
    projects: { id: number; name: string; color: string | null }[];
    projectId: number | null;
    onProject: (id: number | null) => void;
    selected: Set<number>;
    onToggle: (id: number, next: boolean) => void;
    onToggleAll: (next: boolean) => void;
    apply: (changes: Record<string, unknown>) => void;
    exportHref: string;
}

/**
 * Sites saved with the heart.
 *
 * A reduced column set: six columns, not nine. Somebody looking at their
 * favourites has already decided these sites are interesting — the spam score
 * and the DA that helped them decide are noise now, and the date they saved it
 * is the column they actually want and the catalog cannot show.
 */
export function FavoritesTab({
    rows,
    ranges,
    filters,
    categories,
    sorts,
    wishlists,
    projects,
    projectId,
    onProject,
    selected,
    onToggle,
    onToggleAll,
    apply,
    exportHref,
}: Props) {
    if (rows.length === 0 && filters.q === null && filters.category === null) {
        return (
            <EmptyState
                illustration={<HeartIcon size={26} />}
                direction="No favorites yet"
                body="Tap the heart on any site in the catalog to save it here."
                action={
                    <a href="/catalog">
                        <Button size="lg">Browse the catalog</Button>
                    </a>
                }
            />
        );
    }

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <ListToolbar
                filters={filters}
                categories={categories}
                sorts={sorts}
                apply={apply}
                exportHref={exportHref}
            >
                {/* The picker appears only when no project is scoped. With one
                    active the sidebar already says which, and a second control
                    saying the same thing invites them to disagree. */}
                {projectId === null && projects.length > 0 && (
                    <Select
                        label="Buy for"
                        hideLabel
                        value=""
                        onChange={(event) => onProject(Number(event.target.value) || null)}
                        options={[
                            { value: '', label: 'Choose a project…' },
                            ...projects.map((project) => ({
                                value: String(project.id),
                                label: project.name,
                            })),
                        ]}
                    />
                )}

                <AddAllButton
                    ids={selected.size > 0 ? [...selected] : rows.map((row) => row.id)}
                    count={selected.size > 0 ? selected.size : rows.length}
                    everything={selected.size === 0}
                    projectId={projectId}
                />
            </ListToolbar>

            <CatalogTable
                sites={rows}
                ranges={ranges}
                columns={['website', 'category', 'traffic', 'dr', 'price']}
                minWidth="880px"
                selection={{ selected, onToggle, onToggleAll }}
                extraColumns={[
                    {
                        key: 'added',
                        header: 'Date added',
                        className: 'whitespace-nowrap text-sm text-ink-500',
                        render: (row) => (row.addedAt === null ? '—' : date(row.addedAt)),
                    },
                ]}
                renderActions={(row) => (
                    <span className="flex items-center justify-end gap-1">
                        {projectId !== null && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        '/lists/cart',
                                        { website_ids: [row.id], project_id: projectId },
                                        { preserveScroll: true, preserveState: false },
                                    )
                                }
                            >
                                <CartIcon size={14} />
                                Add
                            </Button>
                        )}

                        <RemoveButton
                            label={`Remove ${row.domain} from favorites`}
                            onClick={() =>
                                router.post(
                                    `/sites/${row.slug}/favorite`,
                                    {},
                                    { preserveScroll: true, preserveState: false },
                                )
                            }
                        />

                        <RowMenu row={row} from="favorites" wishlists={wishlists} />
                    </span>
                )}
            />

            {rows.length === 0 && (
                <p className="py-8 text-center text-base text-ink-500">
                    No favourite matches that. Clear the search or the category.
                </p>
            )}
        </div>
    );
}

/**
 * "Add all to cart", or "add the four you selected".
 *
 * Disabled with the reason on hover when no project is scoped, rather than
 * hidden: a missing button reads as "you cannot buy these", a disabled one
 * reads as "not yet, and here is the step".
 */
function AddAllButton({
    ids,
    count,
    everything,
    projectId,
}: {
    ids: number[];
    count: number;
    everything: boolean;
    projectId: number | null;
}) {
    const label = everything ? `Add all ${count} to cart` : `Add ${count} to cart`;

    if (projectId === null) {
        return (
            <Tooltip content="Choose a project first">
                <span className="inline-block">
                    <Button disabled>
                        <CartIcon size={14} />
                        {label}
                    </Button>
                </span>
            </Tooltip>
        );
    }

    return (
        <Button
            disabled={count === 0}
            onClick={() =>
                router.post(
                    '/lists/cart',
                    { website_ids: ids, project_id: projectId },
                    { preserveScroll: true, preserveState: false },
                )
            }
        >
            <CartIcon size={14} />
            {label}
        </Button>
    );
}
