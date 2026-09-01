import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '../../Layouts/AppLayout';
import {
    Button,
    DataGridToolbar,
    EmptyState,
    Pagination,
    QuantBar,
    SearchIcon,
    Table,
    type Column,
    type SortState,
} from '@shared/ui';
import { money } from '@shared/lib/format';
import type { CatalogRanges, CatalogSite, Paginated } from '@shared/types';

interface CatalogIndexProps {
    sites: Paginated<CatalogSite>;
    ranges: CatalogRanges;
    filters: { q?: string };
}

export default function CatalogIndex({ sites, ranges, filters }: CatalogIndexProps) {
    const [search, setSearch] = useState(filters.q ?? '');
    const [selected, setSelected] = useState<string[]>([]);
    const [sort, setSort] = useState<SortState>({ column: 'traffic', direction: 'desc' });

    /**
     * The catalog is the one place QuantBar is used. Every quantitative cell
     * gets a number plus a bar scaled against the whole catalog's range, so a
     * buyer scanning 200 rows reads shape before digits.
     */
    const columns: Column<CatalogSite>[] = [
        {
            id: 'domain',
            header: 'Site',
            cell: (site) => (
                <span>
                    <span className="font-medium text-ink-900">{site.domain}</span>
                    <span className="ml-2 text-sm text-ink-500">{site.language}</span>
                </span>
            ),
        },
        { id: 'category', header: 'Category', cell: (site) => site.category },
        {
            id: 'traffic',
            header: 'Traffic',
            numeric: true,
            sortable: true,
            cell: (site) => <QuantBar value={site.traffic} range={ranges.traffic} />,
        },
        {
            id: 'domain_rating',
            header: 'DR',
            numeric: true,
            sortable: true,
            cell: (site) => <QuantBar value={site.domainRating} range={ranges.domainRating} format={String} />,
        },
        {
            id: 'domain_authority',
            header: 'DA',
            numeric: true,
            cell: (site) => <QuantBar value={site.domainAuthority} range={ranges.domainAuthority} format={String} />,
        },
        {
            id: 'spam_score',
            header: 'Spam score',
            numeric: true,
            cell: (site) => (
                <QuantBar value={site.spamScore} range={ranges.spamScore} inverted format={(v) => `${v}%`} />
            ),
        },
        {
            id: 'price',
            header: 'Price',
            numeric: true,
            sortable: true,
            cell: (site) => <span className="num font-medium text-ink-900">{money(site.priceMinorUnits)}</span>,
        },
        {
            id: 'actions',
            header: '',
            width: '120px',
            cell: () => <Button size="sm">Add to cart</Button>,
        },
    ];

    return (
        <AppLayout title="Catalog">
            <Head title="Catalog" />

            <div className="flex flex-col gap-4">
                <DataGridToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Search sites"
                    selectedCount={selected.length}
                    onClearSelection={() => setSelected([])}
                    bulkActions={<Button size="sm">Add {selected.length} to cart</Button>}
                />

                <Table
                    density="catalog"
                    stickyFirstColumn
                    columns={columns}
                    rows={sites.data}
                    rowKey={(site) => String(site.id)}
                    sort={sort}
                    onSortChange={setSort}
                    selectedKeys={selected}
                    onSelectionChange={setSelected}
                    empty={
                        <EmptyState
                            illustration={<SearchIcon size={32} />}
                            direction="No sites match these filters. Widen the traffic or price range to see more."
                            action={
                                <Button variant="secondary" onClick={() => setSearch('')}>
                                    Clear filters
                                </Button>
                            }
                        />
                    }
                />

                <Pagination
                    page={sites.current_page}
                    pageCount={sites.last_page}
                    total={sites.total}
                    perPage={sites.per_page}
                    onPageChange={(page) => router.get('/catalog', { page }, { preserveState: true })}
                />
            </div>
        </AppLayout>
    );
}
