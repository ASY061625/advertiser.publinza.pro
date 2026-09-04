import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { AppShell } from '../../Layouts/AppShell';
import { Button, Drawer, EmptyState, SearchIcon } from '@shared/ui';
import { number } from '@shared/lib/format';
import type {
    CatalogFacets,
    CatalogFilterState,
    CatalogOptions,
    CatalogPagination,
    CatalogProject,
    CatalogRangeSet,
    CatalogRow,
    CatalogSuggestion,
} from '@shared/types/catalog';
import { AppliedChips } from '../../Components/catalog/AppliedChips';
import { CatalogCards } from '../../Components/catalog/CatalogCards';
import { CatalogTable } from '../../Components/catalog/CatalogTable';
import { CatalogToolbar } from '../../Components/catalog/CatalogToolbar';
import { FilterRail } from '../../Components/catalog/FilterRail';
import { ProjectBar } from '../../Components/catalog/ProjectBar';
import { SiteDrawer } from '../../Components/catalog/SiteDrawer';
import { toPayload, useCatalogFilters } from '../../Components/catalog/useCatalogFilters';

interface Props {
    sites: CatalogRow[];
    pagination: CatalogPagination;
    total: number;
    ranges: CatalogRangeSet;
    facets: CatalogFacets;
    options: CatalogOptions;
    filters: CatalogFilterState;
    isFiltering: boolean;
    suggestions: CatalogSuggestion[];
    project: CatalogProject | null;
    projects: { id: number; name: string; color: string | null }[];
    canBuy: boolean;
}

/**
 * The catalog: a 280px filter rail and the results.
 *
 * Two modes, one page, told apart by `?project=`. Browse mode reads; buying mode
 * buys. Everything else — the rail, the table, the cards, the drawer — is the
 * same in both, so there is one catalog to maintain rather than two that drift.
 */
export default function CatalogIndex({
    sites,
    pagination,
    total,
    ranges,
    facets,
    options,
    filters,
    isFiltering,
    suggestions,
    project,
    projects,
}: Props) {
    const { filters: state, apply, applyDebounced, clear } = useCatalogFilters(filters);
    // An index rather than the row itself: J and K step through the loaded
    // result set, and "which one of these is open" is the only state that
    // survives a step.
    const [drawerIndex, setDrawerIndex] = useState<number | null>(null);
    const [railOpen, setRailOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    // The skeletons need to know a request is in flight, and Inertia is the
    // only thing that knows. Scoped to catalog visits so a cart POST elsewhere
    // on the page does not blank the results.
    useEffect(() => {
        const started = router.on('start', (event) => {
            if (event.detail.visit.url.pathname === '/catalog') setLoading(true);
        });
        const finished = router.on('finish', () => setLoading(false));

        return () => {
            started();
            finished();
        };
    }, []);

    // The rows are re-fetched on every filter change, so the open row is found
    // by slug rather than held as an object that would go stale.
    const openDetail = useCallback(
        (row: CatalogRow) => setDrawerIndex(sites.findIndex((entry) => entry.slug === row.slug)),
        [sites],
    );

    const appliedCount = useMemo(() => countApplied(state), [state]);
    const view = state.view ?? 'table';

    const rail = (
        <FilterRail
            filters={state}
            facets={facets}
            ranges={ranges}
            options={options}
            hasProject={project !== null}
            apply={apply}
            applyDebounced={applyDebounced}
        />
    );

    return (
        <AppShell title="Catalog" crumbs={[{ label: 'Catalog of websites' }]}>
            <Head title="Catalog of websites" />

            <div className="flex flex-col gap-4">
                <ProjectBar project={project} projects={projects} query={{ ...state, project: undefined }} />

                <div className="flex gap-6">
                    {/* Sticky, and its own scroll: fourteen sections are taller
                        than a viewport, and a rail that scrolls the page away
                        from the results it is filtering is a rail you cannot
                        use while looking at them. */}
                    <aside className="hidden w-[280px] shrink-0 rail:block">
                        <div className="sticky top-[calc(theme(spacing.header)+1rem)] max-h-[calc(100vh-theme(spacing.header)-2rem)] overflow-y-auto rounded-card border border-subtle bg-card px-4 shadow-card">
                            {rail}
                        </div>
                    </aside>

                    <div className="flex min-w-0 flex-1 flex-col gap-4">
                        <CatalogToolbar
                            total={total}
                            filters={state}
                            options={options}
                            appliedCount={appliedCount}
                            apply={apply}
                            onOpenFilters={() => setRailOpen(true)}
                        />

                        <AppliedChips filters={state} facets={facets} options={options} apply={apply} clear={clear} />

                        {sites.length === 0 && !loading ? (
                            <Empty isFiltering={isFiltering} suggestions={suggestions} onClear={clear} />
                        ) : view === 'cards' ? (
                            <CatalogCards
                                sites={sites}
                                ranges={ranges}
                                projectId={project?.id ?? null}
                                loading={loading}
                                onOpenDetail={openDetail}
                            />
                        ) : (
                            <CatalogTable
                                sites={sites}
                                ranges={ranges}
                                projectId={project?.id ?? null}
                                loading={loading}
                                onOpenDetail={openDetail}
                            />
                        )}

                        <Pager pagination={pagination} sites={sites.length} apply={apply} />
                    </div>
                </div>
            </div>

            {/* Below 1100px the rail becomes this. Same component, so the two
                cannot drift apart. */}
            <Drawer open={railOpen} onClose={() => setRailOpen(false)} title="Filters">
                {rail}
            </Drawer>

            <SiteDrawer
                sites={sites}
                index={drawerIndex}
                projectId={project?.id ?? null}
                ranges={ranges}
                onNavigate={setDrawerIndex}
                onClose={() => setDrawerIndex(null)}
            />
        </AppShell>
    );
}

/**
 * Two empty states, because they mean opposite things.
 *
 * Filters and no results is an ordinary outcome with an obvious next step. No
 * filters and no results means the catalog itself is empty, which is not
 * something a buyer can fix by clicking anything — so that one says so and
 * points at a person.
 */
function Empty({
    isFiltering,
    suggestions,
    onClear,
}: {
    isFiltering: boolean;
    suggestions: CatalogSuggestion[];
    onClear: () => void;
}) {
    if (!isFiltering) {
        return (
            <EmptyState
                illustration={<SearchIcon size={26} />}
                direction="The catalog is not loading right now"
                body="This is not something you have filtered away — there are no sites to show at all. That is on us. Tell support and we will look into it straight away."
                action={
                    <a href="mailto:support@publinza.pro?subject=Catalog%20is%20empty">
                        <Button size="lg">Contact support</Button>
                    </a>
                }
            />
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <EmptyState
                illustration={<SearchIcon size={26} />}
                direction="No sites match these filters"
                action={
                    <Button variant="secondary" onClick={onClear}>
                        Clear all filters
                    </Button>
                }
            />

            {suggestions.length > 0 && (
                <ul className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {suggestions.map((suggestion) => (
                        <li key={suggestion.label}>
                            <button
                                type="button"
                                onClick={() =>
                                    router.get('/catalog', toPayload(suggestion.query), {
                                        preserveState: true,
                                        preserveScroll: true,
                                    })
                                }
                                className="flex h-full w-full flex-col gap-1 rounded-card border border-subtle bg-card p-4 text-left hover:border-strong"
                            >
                                <span className="text-base font-medium text-ink-900">{suggestion.label}</span>
                                <span className="num text-sm text-ink-500">
                                    to see {number(suggestion.count)} {suggestion.count === 1 ? 'site' : 'sites'}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Cursor paging.
 *
 * Cursors rather than page numbers because the catalog is sorted by figures
 * that move — a site's traffic changing between two clicks shifts every offset
 * after it, and the buyer sees a row twice or not at all. A cursor names a row.
 */
function Pager({
    pagination,
    sites,
    apply,
}: {
    pagination: CatalogPagination;
    sites: number;
    apply: (patch: Partial<CatalogFilterState>) => void;
}) {
    if (pagination.previousCursor === null && pagination.nextCursor === null) return null;

    return (
        <nav className="flex items-center justify-between gap-3" aria-label="Catalog pages">
            <Button
                variant="secondary"
                disabled={pagination.previousCursor === null}
                onClick={() => apply({ cursor: pagination.previousCursor ?? undefined })}
            >
                Previous
            </Button>

            <p className="num text-sm text-ink-500">{number(sites)} on this page</p>

            <Button
                variant="secondary"
                disabled={pagination.nextCursor === null}
                onClick={() => apply({ cursor: pagination.nextCursor ?? undefined })}
            >
                Next
            </Button>
        </nav>
    );
}

/** What the Filters button's badge counts: applied groups, not values. */
function countApplied(filters: CatalogFilterState): number {
    const groups: (keyof CatalogFilterState)[] = [
        'q',
        'domain',
        'categories',
        'countries',
        'languages',
        'price',
        'traffic',
        'dr',
        'da',
        'max_spam',
        'speed',
        'link_type',
        'topics',
        'favorites',
        'unused',
        'has_traffic',
    ];

    return groups.filter((key) => {
        const value = filters[key];

        return Array.isArray(value) ? value.length > 0 : value !== undefined && value !== false;
    }).length;
}
