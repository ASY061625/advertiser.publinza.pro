import { FilterIcon, ListIcon, Select } from '@shared/ui';
import { number } from '@shared/lib/format';
import { cn } from '@shared/lib/cn';
import type { CatalogFilterState, CatalogOptions } from '@shared/types/catalog';

interface Props {
    total: number;
    filters: CatalogFilterState;
    options: CatalogOptions;
    appliedCount: number;
    apply: (patch: Partial<CatalogFilterState>) => void;
    onOpenFilters: () => void;
}

export function CatalogToolbar({ total, filters, options, appliedCount, apply, onOpenFilters }: Props) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            {/* Below 1100px the rail is a drawer, and this is its handle. The
                badge is what stops a filtered-to-nothing catalog looking
                broken on a phone, where the rail that explains it is offscreen. */}
            <button
                type="button"
                onClick={onOpenFilters}
                className="inline-flex items-center gap-2 rounded-button border border-subtle bg-card px-3 py-2 text-sm font-medium text-ink-700 hover:bg-sunken rail:hidden"
            >
                <FilterIcon size={14} />
                Filters
                {appliedCount > 0 && (
                    <span className="num rounded-pill bg-brand px-1.5 py-0.5 text-xs font-medium text-white">
                        {appliedCount}
                    </span>
                )}
            </button>

            <p aria-live="polite" className="num text-base font-medium text-ink-900">
                {number(total)} {total === 1 ? 'site' : 'sites'}
            </p>

            <div className="ml-auto flex flex-wrap items-center gap-2">
                <Select
                    label="Sort"
                    hideLabel
                    className="w-44"
                    value={filters.sort ?? 'relevance'}
                    onChange={(event) => apply({ sort: event.target.value })}
                    options={options.sorts}
                />

                <div
                    role="group"
                    aria-label="Result layout"
                    className="flex overflow-hidden rounded-button border border-subtle"
                >
                    {(['table', 'cards'] as const).map((view) => (
                        <button
                            key={view}
                            type="button"
                            aria-pressed={(filters.view ?? 'table') === view}
                            onClick={() => apply({ view })}
                            className={cn(
                                'flex items-center gap-1.5 px-2.5 py-2 text-sm',
                                (filters.view ?? 'table') === view
                                    ? 'bg-brand-subtle font-medium text-brand'
                                    : 'bg-card text-ink-700 hover:bg-sunken',
                            )}
                        >
                            {view === 'table' ? <ListIcon size={14} /> : <GridIcon />}
                            {view === 'table' ? 'Table' : 'Cards'}
                        </button>
                    ))}
                </div>

                <Select
                    label="Rows per page"
                    hideLabel
                    className="w-28"
                    value={String(filters.per_page ?? 50)}
                    onChange={(event) => apply({ per_page: Number(event.target.value) })}
                    options={options.perPage.map((size) => ({ value: String(size), label: `${size} / page` }))}
                />
            </div>
        </div>
    );
}

/** Four squares. The icon set has no grid glyph and this needs exactly one. */
function GridIcon() {
    return (
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            {[
                [1, 1],
                [9, 1],
                [1, 9],
                [9, 9],
            ].map(([x, y]) => (
                <rect key={`${x}-${y}`} x={x} y={y} width="6" height="6" rx="1.5" fill="currentColor" />
            ))}
        </svg>
    );
}
