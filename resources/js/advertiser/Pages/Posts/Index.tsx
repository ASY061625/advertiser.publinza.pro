import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { AppShell } from '../../Layouts/AppShell';
import { Button, DownloadIcon, EmptyState, Pagination, PlusIcon, SearchIcon, Select, useToast } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { Paginated } from '@shared/types';
import type { ColumnPreferences, PostFilterState, PostOptions, PostRow, SavedViewRecord } from '@shared/types/posts';
import { BulkBar } from '../../Components/posts/BulkBar';
import { CancelDialog } from '../../Components/posts/CancelDialog';
import { ColumnMenu } from '../../Components/posts/ColumnMenu';
import { DuplicateDialog } from '../../Components/posts/DuplicateDialog';
import { FilterBar } from '../../Components/posts/FilterBar';
import { FilterChips } from '../../Components/posts/FilterChips';
import { PostDrawer } from '../../Components/posts/PostDrawer';
import { PostsSkeleton } from '../../Components/posts/PostsSkeleton';
import { PostsTable, type RowAction } from '../../Components/posts/PostsTable';
import { SavedViews } from '../../Components/posts/SavedViews';
import { StatusTabs } from '../../Components/posts/StatusTabs';
import { asPayload, usePostFilters } from '../../Components/posts/usePostFilters';

interface Props {
    posts: Paginated<PostRow>;
    tabCounts: Record<string, number>;
    filters: PostFilterState;
    hasAnyPosts: boolean;
    isFiltering: boolean;
    options: PostOptions;
    columns: ColumnPreferences;
    savedViews: SavedViewRecord[];
}

export default function PostsIndex({
    posts,
    tabCounts,
    filters: serverFilters,
    hasAnyPosts,
    isFiltering,
    options,
    columns: initialColumns,
    savedViews,
}: Props) {
    const page = usePage();
    const { toast } = useToast();

    const { filters, set, clearAll, visit } = usePostFilters(serverFilters);
    const [selected, setSelected] = useState<number[]>([]);
    const [columns, setColumns] = useState(initialColumns);
    const [loading, setLoading] = useState(false);
    const [cancelling, setCancelling] = useState<number[] | null>(null);
    const [duplicating, setDuplicating] = useState<PostRow | null>(null);

    // The open row lives in the URL, so a drawer is something you can link to.
    const openPostId = useMemo(() => {
        const value = new URLSearchParams(page.url.split('?')[1] ?? '').get('post');

        return value === null ? null : Number(value);
    }, [page.url]);

    useEffect(() => {
        const started = router.on('start', () => setLoading(true));
        const finished = router.on('finish', () => setLoading(false));

        return () => {
            started();
            finished();
        };
    }, []);

    // A new page of rows is a new set of rows; keeping ticks from the last one
    // would let a bulk action reach records nobody can see.
    useEffect(() => setSelected([]), [posts.current_page, serverFilters]);

    const visible = useMemo(() => columns.order.filter((id) => !columns.hidden.includes(id)), [columns]);

    const saveColumns = useCallback((order: string[], hidden: string[]) => {
        setColumns((current) => ({ ...current, order, hidden }));

        // Fire and forget: the grid has already rearranged, and a failed write
        // only means the next browser starts from the defaults.
        void fetch('/posts/columns', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ order, hidden }),
        }).catch(() => undefined);
    }, []);

    function openDrawer(id: number | null) {
        const query = new URLSearchParams(page.url.split('?')[1] ?? '');

        if (id === null) query.delete('post');
        else query.set('post', String(id));

        router.get(
            `/posts?${query.toString()}`,
            {},
            { preserveState: true, preserveScroll: true, replace: true, only: [] },
        );
    }

    /**
     * Downloads leave through a real navigation or a real form post rather than
     * fetch: the browser owns the file dialog, and a blob assembled in
     * JavaScript would be inert inside a sandboxed frame.
     */
    function exportCsv(ids?: number[]) {
        const query = queryOf(filters);

        for (const id of ids ?? []) query.append('ids[]', String(id));

        window.location.href = `/posts/export?${query.toString()}`;
    }

    function downloadArticles(ids: number[]) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/posts/bulk';
        form.style.display = 'none';
        form.append(hidden('_token', csrf()), hidden('action', 'download'));

        for (const id of ids) form.append(hidden('ids[]', String(id)));

        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    function bulk(action: 'cancel' | 'move', extra: Record<string, unknown>) {
        router.post(
            '/posts/bulk',
            { action, ids: selected, ...extra },
            {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
                onError: () => toast({ tone: 'danger', title: 'That did not work. Nothing was changed.' }),
            },
        );
    }

    const rowActions: RowAction[] = [
        { id: 'view', label: 'View details', enabled: () => true, run: (row) => openDrawer(row.id) },
        {
            id: 'conversation',
            label: 'Open conversation',
            enabled: () => true,
            run: (row) => router.visit(`/conversations?post=${row.id}`),
        },
        {
            id: 'article',
            label: 'Download article',
            enabled: () => true,
            run: (row) => downloadArticles([row.id]),
        },
        {
            id: 'copy',
            label: 'Copy published URL',
            enabled: (row) => row.publishedUrl !== null,
            run: (row) => {
                void navigator.clipboard
                    .writeText(row.publishedUrl ?? '')
                    .then(() => toast({ tone: 'success', title: 'Published URL copied.' }))
                    .catch(() =>
                        toast({
                            tone: 'danger',
                            title: 'Could not copy.',
                            description: row.publishedUrl ?? undefined,
                        }),
                    );
            },
        },
        {
            id: 'duplicate',
            label: 'Duplicate to another project',
            enabled: () => options.projects.length > 0,
            run: (row) => setDuplicating(row),
        },
        {
            id: 'cancel',
            label: 'Cancel post',
            destructive: true,
            enabled: (row) => row.canCancel,
            run: (row) => setCancelling([row.id]),
        },
    ];

    // Nothing at all, ever. An invitation, not an error — and distinct from
    // "nothing matches", which is a filter problem with a different fix.
    if (!hasAnyPosts && !isFiltering) {
        return (
            <AppShell title="Posts">
                <Head title="Posts" />
                <Header total={0} onExport={() => exportCsv()} exportDisabled />

                <div className="mt-6">
                    <EmptyState
                        illustration={<PlusIcon size={26} />}
                        direction="Time to add your first post!"
                        action={
                            <span className="flex flex-col items-center gap-3">
                                <Link href="/posts/create">
                                    <Button size="lg">Add post</Button>
                                </Link>
                                <Link
                                    href="/catalog"
                                    className="text-sm font-medium text-brand underline underline-offset-2"
                                >
                                    Find a website
                                </Link>
                            </span>
                        }
                    />
                </div>
            </AppShell>
        );
    }

    return (
        <AppShell title="Posts">
            <Head title="Posts" />

            <Header total={posts.total} onExport={() => exportCsv()} />

            <div className="mt-5">
                <StatusTabs
                    tabs={options.tabs}
                    counts={tabCounts}
                    value={filters.tab ?? 'all'}
                    onChange={(tab) => set({ tab: tab === 'all' ? undefined : tab })}
                />
            </div>

            <div className="mt-4 flex flex-col gap-3">
                <FilterBar filters={filters} options={options} onChange={set} />

                <FilterChips filters={filters} options={options} onChange={set} onClearAll={clearAll} />

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <SavedViews views={savedViews} filters={filters} onApply={visit} />

                    <div className="flex items-end gap-2">
                        <Select
                            label="Rows per page"
                            hideLabel
                            className="w-40"
                            value={String(filters.per_page ?? 25)}
                            onChange={(event) => set({ per_page: Number(event.target.value) })}
                            options={[25, 50, 100].map((size) => ({
                                value: String(size),
                                label: `${size} per page`,
                            }))}
                        />
                        <ColumnMenu preferences={columns} onChange={saveColumns} />
                    </div>
                </div>
            </div>

            <div className="mt-4">
                {loading ? (
                    <PostsSkeleton columns={visible.length} />
                ) : posts.data.length === 0 ? (
                    <EmptyState
                        illustration={<SearchIcon size={22} />}
                        direction="No posts match these filters"
                        action={
                            <Button variant="secondary" onClick={clearAll}>
                                Clear all filters
                            </Button>
                        }
                    />
                ) : (
                    <PostsTable
                        rows={posts.data}
                        columns={visible}
                        sort={{ column: filters.sort ?? 'created_at', direction: filters.direction ?? 'desc' }}
                        onSortChange={(column) =>
                            set({
                                sort: column,
                                direction: filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc',
                            })
                        }
                        selected={selected}
                        onSelectionChange={setSelected}
                        onRowClick={(row) => openDrawer(row.id)}
                        actions={rowActions}
                        activeId={openPostId}
                    />
                )}
            </div>

            {posts.last_page > 1 && (
                <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
                    <p className="num text-sm text-ink-500">
                        {number(posts.total)} post{posts.total === 1 ? '' : 's'}
                    </p>
                    <Pagination
                        page={posts.current_page}
                        pageCount={posts.last_page}
                        total={posts.total}
                        perPage={posts.per_page}
                        onPageChange={(next) =>
                            router.get('/posts', asPayload({ ...filters, page: next }), {
                                preserveState: true,
                                preserveScroll: true,
                            })
                        }
                    />
                </div>
            )}

            {/* Room for the sticky bar, so the last row is never hidden under it. */}
            {selected.length > 0 && <div className="h-20" aria-hidden="true" />}

            <BulkBar
                count={selected.length}
                projects={options.projects}
                onClear={() => setSelected([])}
                onExport={() => exportCsv(selected)}
                onDownload={() => downloadArticles(selected)}
                onCancel={() => setCancelling(selected)}
                onMove={(folderId) => bulk('move', { folder_id: folderId })}
            />

            <PostDrawer
                postId={openPostId}
                onClose={() => openDrawer(null)}
                onCancelPost={(id) => setCancelling([id])}
            />

            <CancelDialog
                open={cancelling !== null}
                count={cancelling?.length ?? 0}
                onClose={() => setCancelling(null)}
                onConfirm={(reason) => {
                    router.post(
                        '/posts/bulk',
                        { action: 'cancel', ids: cancelling ?? [], reason },
                        { preserveScroll: true, onSuccess: () => setSelected([]) },
                    );
                    setCancelling(null);
                }}
            />

            <DuplicateDialog post={duplicating} projects={options.projects} onClose={() => setDuplicating(null)} />
        </AppShell>
    );
}

function Header({
    total,
    onExport,
    exportDisabled = false,
}: {
    total: number;
    onExport: () => void;
    exportDisabled?: boolean;
}) {
    return (
        <header className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-baseline gap-3">
                <h1 className="font-sora text-xl font-semibold text-ink-900">Posts</h1>
                <span className="num text-sm text-ink-500">
                    {number(total)} {total === 1 ? 'post' : 'posts'}
                </span>
            </div>

            <div className="flex items-center gap-2">
                <Button variant="secondary" onClick={onExport} disabled={exportDisabled}>
                    <DownloadIcon size={14} />
                    Export CSV
                </Button>
                <Link href="/posts/create">
                    <Button>
                        <PlusIcon size={14} />
                        Add post
                    </Button>
                </Link>
            </div>
        </header>
    );
}

function queryOf(filters: PostFilterState): URLSearchParams {
    const query = new URLSearchParams();

    for (const [key, value] of Object.entries(filters)) {
        if (value === undefined || value === null || value === '') continue;

        if (Array.isArray(value)) for (const item of value) query.append(`${key}[]`, String(item));
        else query.set(key, String(value));
    }

    return query;
}

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function hidden(name: string, value: string): HTMLInputElement {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;

    return input;
}
