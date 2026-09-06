import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, CartIcon, EmptyState, FlagIcon, Input, ListIcon, Modal, Tooltip } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { compactNumber, money, number } from '@shared/lib/format';
import type { CatalogRangeSet } from '@shared/types/catalog';
import type { ListFilters, ListRow, WishlistSummary, WishlistTotals } from '@shared/types/lists';
import { CatalogTable } from '../catalog/CatalogTable';
import { ListToolbar } from './ListToolbar';
import { RemoveButton, RowMenu } from './RowMenu';
import { WishlistSidebar } from './WishlistSidebar';

interface Props {
    rows: ListRow[];
    ranges: CatalogRangeSet;
    filters: ListFilters;
    categories: { id: number; name: string }[];
    sorts: { value: string; label: string }[];
    wishlists: WishlistSummary[];
    summary: WishlistTotals | null;
    projectId: number | null;
    selected: Set<number>;
    onToggle: (id: number, next: boolean) => void;
    onToggleAll: (next: boolean) => void;
    apply: (changes: Record<string, unknown>) => void;
    exportHref: string;
}

/**
 * Planned buys, in named lists.
 *
 * The summary strip is the reason this tab is not just a filtered favourites:
 * a shortlist is read to answer "what would this campaign cost and is it strong
 * enough", and four numbers answer that without adding anything up by hand.
 */
export function WishlistTab({
    rows,
    ranges,
    filters,
    categories,
    sorts,
    wishlists,
    summary,
    projectId,
    selected,
    onToggle,
    onToggleAll,
    apply,
    exportHref,
}: Props) {
    const [confirmBuy, setConfirmBuy] = useState(false);

    if (wishlists.length === 0) {
        return (
            <EmptyState
                illustration={<ListIcon size={26} />}
                direction="Build a shortlist before you buy"
                body="Add sites from the catalog to plan a campaign."
                action={
                    <a href="/catalog">
                        <Button size="lg">Browse the catalog</Button>
                    </a>
                }
            />
        );
    }

    return (
        <div className="flex min-w-0 flex-col gap-4 lg:flex-row">
            <aside className="w-full shrink-0 lg:w-[240px]">
                <WishlistSidebar
                    lists={wishlists}
                    activeId={filters.list}
                    onSelect={(id) => apply({ list: id })}
                />
            </aside>

            <div className="flex min-w-0 flex-1 flex-col gap-4">
                {summary !== null && (
                    <SummaryStrip
                        summary={summary}
                        projectId={projectId}
                        onBuy={() => setConfirmBuy(true)}
                    />
                )}

                <ListToolbar
                    filters={filters}
                    categories={categories}
                    sorts={sorts}
                    apply={apply}
                    exportHref={exportHref}
                />

                {rows.length === 0 ? (
                    <p className="rounded-card border border-subtle bg-card py-10 text-center text-base text-ink-500">
                        {filters.q === null && filters.category === null
                            ? 'This list is empty. Add sites to it from the catalog.'
                            : 'Nothing on this list matches that.'}
                    </p>
                ) : (
                    <CatalogTable
                        sites={rows}
                        ranges={ranges}
                        // No category column here, unlike favourites. The note
                        // needs 200px to be a field somebody can read back what
                        // they typed into, and this tab has already given 240px
                        // to the list rail — so the column that the toolbar
                        // filter can stand in for is the one that goes.
                        columns={['website', 'traffic', 'dr', 'price']}
                        minWidth="900px"
                        actionsWidth="w-[104px]"
                        selection={{ selected, onToggle, onToggleAll }}
                        extraColumns={[
                            {
                                key: 'priority',
                                header: 'Priority',
                                className: 'w-12 text-center',
                                render: (row) => <PriorityFlag row={row} />,
                            },
                            {
                                key: 'note',
                                header: 'Note',
                                className: 'w-[200px]',
                                render: (row) => <NoteField row={row} />,
                            },
                        ]}
                        renderActions={(row) => (
                            <span className="flex items-center justify-end gap-1">
                                <RemoveButton
                                    label={`Remove ${row.domain} from this list`}
                                    onClick={() =>
                                        router.post(
                                            '/lists/move',
                                            { website_id: row.id, from: 'wishlist', to: 'favorites' },
                                            { preserveScroll: true, preserveState: false },
                                        )
                                    }
                                />
                                <RowMenu row={row} from="wishlist" wishlists={wishlists} />
                            </span>
                        )}
                    />
                )}
            </div>

            <Modal
                open={confirmBuy}
                onClose={() => setConfirmBuy(false)}
                size="sm"
                title="Add this list to your cart?"
                description="Nothing is bought yet — they go to the cart, where you can still change or remove any of them."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setConfirmBuy(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() =>
                                router.post(
                                    '/lists/cart',
                                    { website_ids: rows.map((row) => row.id), project_id: projectId },
                                    {
                                        preserveScroll: true,
                                        preserveState: false,
                                        onSuccess: () => setConfirmBuy(false),
                                    },
                                )
                            }
                        >
                            Add {summary?.siteCount ?? rows.length} to cart
                        </Button>
                    </>
                }
            >
                {/* The total is the whole reason to confirm. A bulk add with no
                    figure in front of it is somebody finding out afterwards. */}
                <dl className="flex items-baseline justify-between gap-4 rounded-card bg-sunken px-4 py-3">
                    <dt className="text-base text-ink-700">Combined price</dt>
                    <dd className="num font-sora text-lg font-semibold text-ink-900">
                        {money(summary?.totalCents ?? 0)}
                    </dd>
                </dl>
            </Modal>
        </div>
    );
}

/** What the list adds up to, and the one action it earns. */
function SummaryStrip({
    summary,
    projectId,
    onBuy,
}: {
    summary: WishlistTotals;
    projectId: number | null;
    onBuy: () => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-x-8 gap-y-3 rounded-card border border-subtle bg-card px-5 py-4 shadow-card">
            <Figure label="Sites" value={number(summary.siteCount)} />
            <Figure label="Combined price" value={money(summary.totalCents)} strong />
            <Figure
                label="Average DR"
                value={summary.averageDr === null ? '—' : String(summary.averageDr)}
                // Says what the average is of, rather than implying every site
                // was measured. A list of ten with two uncrawled has an average
                // of the eight.
                hint={
                    summary.measured === summary.siteCount || summary.averageDr === null
                        ? undefined
                        : `of ${summary.measured} measured`
                }
            />
            <Figure label="Combined traffic" value={compactNumber(summary.totalTraffic)} />

            <span className="ml-auto">
                {projectId === null ? (
                    <Tooltip content="Choose a project first">
                        <span className="inline-block">
                            <Button disabled>
                                <CartIcon size={14} />
                                Buy this list
                            </Button>
                        </span>
                    </Tooltip>
                ) : (
                    <Button onClick={onBuy} disabled={summary.siteCount === 0}>
                        <CartIcon size={14} />
                        Buy this list
                    </Button>
                )}
            </span>
        </div>
    );
}

function Figure({
    label,
    value,
    hint,
    strong = false,
}: {
    label: string;
    value: string;
    hint?: string;
    strong?: boolean;
}) {
    return (
        <div>
            <dt className="text-sm text-ink-500">{label}</dt>
            <dd
                className={cn(
                    'num font-sora font-semibold text-ink-900',
                    strong ? 'text-lg' : 'text-md',
                )}
            >
                {value}
            </dd>
            {hint && <p className="num text-xs text-ink-500">{hint}</p>}
        </div>
    );
}

/**
 * A flag, not a rank.
 *
 * Ordering thirty sites against each other is work nobody does twice; "these
 * four first" is the distinction people actually make. Flagged rows float to
 * the top whatever the sort, or the flag would do nothing.
 */
function PriorityFlag({ row }: { row: ListRow }) {
    return (
        <button
            type="button"
            aria-pressed={row.priority === true}
            aria-label={row.priority === true ? `Unflag ${row.domain}` : `Flag ${row.domain} as a priority`}
            onClick={() =>
                router.patch(
                    `/lists/items/${row.itemId}`,
                    { priority: row.priority !== true },
                    { preserveScroll: true, preserveState: false },
                )
            }
            className={cn(
                'rounded-button p-1.5 transition-colors duration-fast',
                row.priority === true ? 'text-warning' : 'text-ink-300 hover:text-ink-500',
            )}
        >
            <FlagIcon size={16} />
        </button>
    );
}

/** The note, saved when the field is left rather than on every keystroke. */
function NoteField({ row }: { row: ListRow }) {
    const [value, setValue] = useState(row.note ?? '');

    return (
        <Input
            label={`Note for ${row.domain}`}
            hideLabel
            value={value}
            placeholder="Why this one"
            maxLength={500}
            onChange={(event) => setValue(event.target.value)}
            onBlur={() => {
                if (value === (row.note ?? '')) return;

                router.patch(
                    `/lists/items/${row.itemId}`,
                    { note: value },
                    { preserveScroll: true, preserveState: false },
                );
            }}
        />
    );
}
