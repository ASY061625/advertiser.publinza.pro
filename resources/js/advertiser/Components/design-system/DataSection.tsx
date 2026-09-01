import { useState } from 'react';
import {
    Badge,
    Button,
    Card,
    DataGridToolbar,
    DownloadIcon,
    EmptyState,
    Pagination,
    QuantBar,
    SearchIcon,
    Select,
    StatCard,
    Table,
    Tabs,
    type Column,
    type SortState,
} from '@shared/ui';
import { Panel, Row, Section } from './Shell';

interface DemoRow {
    id: string;
    domain: string;
    category: string;
    traffic: number;
    dr: number;
    spam: number;
    price: string;
}

const ROWS: DemoRow[] = [
    { id: '1', domain: 'techcrunch.com', category: 'Technology', traffic: 412000, dr: 91, spam: 2, price: '$1,240.00' },
    { id: '2', domain: 'wired.com', category: 'Technology', traffic: 288000, dr: 89, spam: 3, price: '$980.00' },
    { id: '3', domain: 'healthline.com', category: 'Health', traffic: 96500, dr: 87, spam: 6, price: '$640.00' },
    { id: '4', domain: 'nomadtravel.blog', category: 'Travel', traffic: 12400, dr: 41, spam: 18, price: '$180.00' },
    { id: '5', domain: 'fintechdaily.io', category: 'Finance', traffic: 3200, dr: 28, spam: 31, price: '$95.00' },
];

// The ranges the quant-bars scale against: the whole catalog's, not this page's.
const RANGES = {
    traffic: [3200, 412000] as [number, number],
    dr: [28, 91] as [number, number],
    spam: [2, 31] as [number, number],
};

export function DataSection() {
    const [selected, setSelected] = useState<string[]>(['2']);
    const [sort, setSort] = useState<SortState>({ column: 'traffic', direction: 'desc' });
    const [page, setPage] = useState(3);
    const [tab, setTab] = useState('all');
    const [search, setSearch] = useState('');

    const columns: Column<DemoRow>[] = [
        { id: 'domain', header: 'Site', cell: (row) => <span className="font-medium text-ink-900">{row.domain}</span> },
        { id: 'category', header: 'Category', cell: (row) => row.category },
        {
            id: 'traffic',
            header: 'Traffic',
            numeric: true,
            sortable: true,
            cell: (row) => <QuantBar value={row.traffic} range={RANGES.traffic} />,
        },
        {
            id: 'dr',
            header: 'DR',
            numeric: true,
            sortable: true,
            cell: (row) => <QuantBar value={row.dr} range={RANGES.dr} format={String} />,
        },
        {
            id: 'spam',
            header: 'Spam score',
            numeric: true,
            cell: (row) => <QuantBar value={row.spam} range={RANGES.spam} inverted format={(v) => `${v}%`} />,
        },
        { id: 'price', header: 'Price', numeric: true, sortable: true, cell: (row) => row.price },
    ];

    return (
        <>
            <Section
                id="quantbar"
                title="QuantBar"
                note="The signature component: tabular digits with a 3px proportional bar beneath, scaled against a min/max range passed as props. Brand blue by default, teal in the top quartile. Used ONLY in the catalog — never in dashboards, drawers or admin tables."
            >
                <Row label="Scaling" stack>
                    <Panel>
                        <div className="flex gap-10">
                            {[3200, 96500, 288000, 412000].map((value) => (
                                <div key={value} className="w-24">
                                    <QuantBar value={value} range={RANGES.traffic} />
                                </div>
                            ))}
                        </div>
                        <p className="mt-4 max-w-2xl text-base text-ink-500">
                            All four are scaled against the same catalog-wide range. Only the last crosses into the top
                            quartile and turns teal, so standouts are visible before you read a digit.
                        </p>
                    </Panel>
                </Row>

                <Row label="Inverted" stack>
                    <Panel>
                        <div className="flex gap-10">
                            {[2, 6, 18, 31].map((value) => (
                                <div key={value} className="w-24">
                                    <QuantBar value={value} range={RANGES.spam} inverted format={(v) => `${v}%`} />
                                </div>
                            ))}
                        </div>
                        <p className="mt-4 max-w-2xl text-base text-ink-500">
                            Spam score is inverted: low is good, so the bar fills from the good end and a spam score of
                            2% reads as a full teal bar.
                        </p>
                    </Panel>
                </Row>
            </Section>

            <Section
                id="statcard"
                title="StatCard"
                note="Label, big tabular number, delta chip, icon. Deliberately does not use QuantBar."
            >
                <Row label="States" stack>
                    <div className="grid w-full grid-cols-4 gap-5">
                        <StatCard
                            label="Posts published"
                            value="128"
                            delta={12.4}
                            deltaLabel="vs last month"
                            icon={<SearchIcon size={16} />}
                        />
                        <StatCard label="Spend this month" value="$14,280.00" delta={-3.2} deltaLabel="vs last month" />
                        <StatCard label="Active projects" value="9" />
                        <StatCard label="Loading" value="—" loading />
                    </div>
                </Row>
            </Section>

            <Section id="card" title="Card">
                <Row label="Variants" stack>
                    <div className="grid w-full grid-cols-2 gap-5">
                        <Card
                            title="Wallet"
                            action={
                                <Button size="sm" variant="secondary">
                                    Top up balance
                                </Button>
                            }
                        >
                            <p className="num text-xl font-semibold text-ink-900">$2,480.00</p>
                            <p className="mt-1 text-base text-ink-500">$640.00 frozen against open orders.</p>
                        </Card>
                        <Card>
                            <p className="text-base text-ink-700">A card with no header — just a padded surface.</p>
                        </Card>
                    </div>
                </Row>
            </Section>

            <Section
                id="tabs"
                title="Tabs"
                note="Underline style. Arrow keys move between tabs, Home and End jump to the ends."
            >
                <Row label="Default" stack>
                    <Tabs
                        value={tab}
                        onChange={setTab}
                        items={[
                            { id: 'all', label: 'All sites', count: 214 },
                            { id: 'saved', label: 'Saved', count: 12 },
                            { id: 'used', label: 'Previously used', count: 38 },
                            { id: 'blocked', label: 'Blocked', disabled: true },
                        ]}
                    />
                </Row>
            </Section>

            <Section
                id="table"
                title="Table"
                note="Sticky header, optional sticky first column, sortable columns and row selection. 48px rows everywhere except the catalog, which uses 56px to make room for the quant-bars."
            >
                <Row label="Toolbar" stack>
                    <DataGridToolbar
                        search={search}
                        onSearchChange={setSearch}
                        searchPlaceholder="Search sites"
                        filters={
                            <div className="w-44">
                                <Select
                                    hideLabel
                                    label="Category"
                                    value=""
                                    placeholder="Any category"
                                    onChange={() => undefined}
                                    options={[
                                        { value: 'tech', label: 'Technology' },
                                        { value: 'finance', label: 'Finance' },
                                    ]}
                                />
                            </div>
                        }
                        actions={
                            <Button variant="secondary" size="sm" trailingIcon={<DownloadIcon size={15} />}>
                                Export CSV
                            </Button>
                        }
                    />
                </Row>

                <Row label="Selection bar" stack>
                    <DataGridToolbar
                        selectedCount={selected.length}
                        onClearSelection={() => setSelected([])}
                        bulkActions={<Button size="sm">Add to cart</Button>}
                    />
                </Row>

                <Row label="Catalog density" stack>
                    <Table
                        density="catalog"
                        columns={columns}
                        rows={ROWS}
                        rowKey={(row) => row.id}
                        sort={sort}
                        onSortChange={setSort}
                        selectedKeys={selected}
                        onSelectionChange={setSelected}
                    />
                </Row>

                <Row label="Default density" stack>
                    <Table
                        columns={[
                            { id: 'domain', header: 'Site', cell: (row: DemoRow) => row.domain },
                            { id: 'category', header: 'Category', cell: (row: DemoRow) => row.category },
                            { id: 'status', header: 'Status', cell: () => <Badge status="in_progress" /> },
                            { id: 'price', header: 'Price', numeric: true, cell: (row: DemoRow) => row.price },
                        ]}
                        rows={ROWS.slice(0, 3)}
                        rowKey={(row) => row.id}
                    />
                </Row>

                <Row label="Loading" stack>
                    <Table columns={columns.slice(0, 4)} rows={[]} rowKey={(row) => row.id} loading />
                </Row>

                <Row label="Empty" stack>
                    <Table
                        columns={columns.slice(0, 4)}
                        rows={[]}
                        rowKey={(row) => row.id}
                        empty={
                            <EmptyState
                                illustration={<SearchIcon size={32} />}
                                direction="No sites match these filters. Widen the traffic or price range to see more."
                                action={<Button variant="secondary">Clear filters</Button>}
                            />
                        }
                    />
                </Row>
            </Section>

            <Section id="pagination" title="Pagination">
                <Row label="Windowed" stack>
                    <Pagination page={page} pageCount={24} total={1180} perPage={50} onPageChange={setPage} />
                </Row>
                <Row label="First page" stack>
                    <Pagination page={1} pageCount={5} total={214} perPage={50} onPageChange={() => undefined} />
                </Row>
            </Section>
        </>
    );
}
