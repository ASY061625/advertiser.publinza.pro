import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { Button, EmptyState, ListIcon, Pagination, SearchIcon, Select, useToast } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { Paginated } from '@shared/types';
import type { ColumnPreferences, PostFilterState, PostOptions, PostRow, SavedViewRecord } from '@shared/types/posts';
import { BulkBar } from './BulkBar';
import { CancelDialog } from './CancelDialog';
import { ColumnMenu } from './ColumnMenu';
import { DuplicateDialog } from './DuplicateDialog';
import { FilterBar } from './FilterBar';
import { FilterChips } from './FilterChips';
import { PostDrawer } from './PostDrawer';
import { PostsBoard } from './PostsBoard';
import { PostsSkeleton } from './PostsSkeleton';
import { PostsTable, type RowAction } from './PostsTable';
import { SavedViews } from './SavedViews';
import { StatusTabs } from './StatusTabs';
import { toRequest, usePostFilters } from './usePostFilters';

export type PostsView = 'table' | 'board';

export interface PostsGridProps {
    posts: Paginated<PostRow>;
    tabCounts: Record<string, number>;
    filters: PostFilterState;
    isFiltering: boolean;
    options: PostOptions;
    columns: ColumnPreferences;
    /** Omitted on a scoped grid: a saved view carrying a project means nothing there. */
    savedViews?: SavedViewRecord[];

    /** Where filter changes, pagination and the drawer deep-link navigate. */
    path: string;
    /** Params that always travel with that path — a project page's own `?tab=`. */
    fixedQuery?: Record<string, string>;
    /** The status tab's query-string key. `posts_tab` where `tab` is taken. */
    tabKey?: string;
    /**
     * The props a filter change re-fetches. These are the *page's* prop names,
     * so a surface that nests the grid under one prop has to say so — naming
     * props the page does not have means Inertia returns none of them and the
     * grid keeps its old data while the URL says otherwise.
     */
    only?: string[];

    /**
     * Locks the grid to one project. Removes the Project column and filter,
     * promotes Folder into the visible row, and keeps the scope on anything
     * that leaves the page — the export in particular.
     */
    scope?: { projectId: number; folders: { id: number; name: string }[] } | null;

    /** Rendered above the status tabs. */
    summary?: ReactNode;
    /** Shown instead of everything when the account or project has no posts. */
    emptyState: ReactNode;
    /** Offers the Table/Board toggle when given. */
    view?: PostsView;
    onViewChange?: (view: PostsView) => void;
}

/**
 * The posts grid: tabs, filters, chips, table or board, bulk actions, drawer.
 *
 * One component for /posts and for a project's Post management tab, because
 * they are one grid. The differences are props — a path to navigate, a project
 * to lock to, a summary strip — and everything else is shared, so a fix to the
 * filter round trip or the bulk bar lands on both at once.
 */
export function PostsGrid({
    posts,
    tabCounts,
    filters: serverFilters,
    isFiltering,
    options,
    columns: initialColumns,
    savedViews,
    path,
    fixedQuery,
    tabKey = 'tab',
    only,
    scope = null,
    summary,
    emptyState,
    view = 'table',
    onViewChange,
}: PostsGridProps) {
    const page = usePage();
    const { toast } = useToast();

    const target = useMemo(
        () => ({ path, fixed: fixedQuery, tabKey, only }),
        // A fresh object each render would rebuild every callback below it.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [path, tabKey, JSON.stringify(fixedQuery ?? {}), JSON.stringify(only ?? [])],
    );

    const { filters, set, clearAll, visit } = usePostFilters(serverFilters, target);
    const [selected, setSelected] = useState<number[]>([]);
    const [columns, setColumns] = useState(initialColumns);
    const [loading, setLoading] = useState(false);
    const [cancelling, setCancelling] = useState<number[] | null>(null);
    const [duplicating, setDuplicating] = useState<PostRow | null>(null);

    // The page size the table was on before the board borrowed it.
    const beforeBoard = useRef<number | null>(null);

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

    // A project's grid has one project, so the column would repeat it on every
    // row. It is dropped from the view, not from the preferences — the same
    // account's /posts keeps whatever it had.
    const visible = useMemo(
        () =>
            columns.order
                .filter((id) => !columns.hidden.includes(id))
                .filter((id) => !(scope !== null && id === 'project')),
        [columns, scope],
    );

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
            `${path}?${query.toString()}`,
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

        // The export endpoint is the global one, so the scope has to travel as
        // a filter or a project's export would quietly be the whole account's.
        if (scope !== null) query.append('projects[]', String(scope.projectId));

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
    if (!isFiltering && posts.total === 0 && !loading) {
        return <>{emptyState}</>;
    }

    return (
        <>
            {summary}

            <div className={summary ? 'mt-5' : undefined}>
                <StatusTabs
                    tabs={options.tabs}
                    counts={tabCounts}
                    value={filters.tab ?? 'all'}
                    onChange={(tab) => set({ tab: tab === 'all' ? undefined : tab })}
                />
            </div>

            <div className="mt-4 flex flex-col gap-3">
                <FilterBar
                    filters={filters}
                    options={options}
                    onChange={set}
                    scopedFolders={scope === null ? null : scope.folders}
                />

                <FilterChips filters={filters} options={options} onChange={set} onClearAll={clearAll} />

                <div className="flex flex-wrap items-center justify-between gap-2">
                    {savedViews ? <SavedViews views={savedViews} filters={filters} onApply={visit} /> : <span />}

                    <div className="flex items-end gap-2">
                        {onViewChange && (
                            <ViewToggle
                                value={view}
                                onChange={(next) => {
                                    // A board of twenty-five rows is not a
                                    // board: whole columns come up empty while
                                    // the tab above says six. Switching asks
                                    // for the largest page, and switching back
                                    // returns the page size that was chosen.
                                    if (next === 'board' && (filters.per_page ?? 25) < 100) {
                                        beforeBoard.current = filters.per_page ?? 25;
                                        set({ per_page: 100 });
                                    } else if (next === 'table' && beforeBoard.current !== null) {
                                        const restore = beforeBoard.current;
                                        beforeBoard.current = null;
                                        set({ per_page: restore });
                                    }

                                    onViewChange(next);
                                }}
                            />
                        )}

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

                        {/* The board draws its own cards; there are no columns
                            to choose between. */}
                        {view === 'table' && <ColumnMenu preferences={columns} onChange={saveColumns} />}
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
                ) : view === 'board' ? (
                    <PostsBoard
                        rows={posts.data}
                        onCardClick={(row) => openDrawer(row.id)}
                        activeId={openPostId}
                        paged={posts.last_page > 1}
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
                            router.get(path, toRequest({ ...filters, page: next }, tabKey, fixedQuery), {
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
        </>
    );
}

/** Exported so a page's own header can trigger the same export. */
export function postsExportHref(filters: PostFilterState, projectId?: number): string {
    const query = queryOf(filters);

    if (projectId !== undefined) query.append('projects[]', String(projectId));

    return `/posts/export?${query.toString()}`;
}

function ViewToggle({ value, onChange }: { value: PostsView; onChange: (view: PostsView) => void }) {
    return (
        <div role="group" aria-label="View" className="flex rounded-button border border-subtle p-0.5">
            {(
                [
                    ['table', 'Table', <ListIcon key="t" size={14} />],
                    ['board', 'Board', <BoardIcon key="b" />],
                ] as const
            ).map(([id, label, icon]) => (
                <button
                    key={id}
                    type="button"
                    aria-pressed={value === id}
                    onClick={() => onChange(id)}
                    className={cn(
                        'flex items-center gap-1.5 rounded-[calc(var(--radius-button)-2px)] px-2.5 py-1.5 text-sm',
                        'transition-colors duration-fast ease-standard',
                        value === id ? 'bg-sunken font-medium text-ink-900' : 'text-ink-500 hover:text-ink-700',
                    )}
                >
                    {icon}
                    {label}
                </button>
            ))}
        </div>
    );
}

function BoardIcon() {
    return (
        <svg
            width={14}
            height={14}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <rect x="3" y="4" width="5" height="16" rx="1" />
            <rect x="10" y="4" width="5" height="11" rx="1" />
            <rect x="17" y="4" width="4" height="7" rx="1" />
        </svg>
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
