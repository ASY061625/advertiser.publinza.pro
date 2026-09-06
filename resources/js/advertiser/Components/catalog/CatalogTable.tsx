import type { ReactNode } from 'react';
import { Checkbox, QuantBar, Skeleton } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type { CatalogRangeSet, CatalogRow } from '@shared/types/catalog';
import { CategoryPill, PriceCell, SiteIdentity, SpamCell } from './SiteCells';
import { SiteActions } from './SiteActions';
import { useVirtualRows } from './useVirtualRows';

/** The columns this table knows how to draw, in the order it draws them. */
export type CatalogColumn = 'website' | 'category' | 'traffic' | 'dr' | 'da' | 'spam' | 'published' | 'price';

const ALL_COLUMNS: CatalogColumn[] = [
    'website',
    'category',
    'traffic',
    'dr',
    'da',
    'spam',
    'published',
    'price',
];

const HEADINGS: Record<CatalogColumn, [string, string]> = {
    website: ['Website', 'w-[200px]'],
    category: ['Category', ''],
    traffic: ['Monthly traffic', 'text-right'],
    dr: ['DR', 'text-right'],
    da: ['DA', 'text-right'],
    spam: ['Spam', 'text-right'],
    published: ['Published in', ''],
    price: ['Price', 'text-right'],
};

/** A column the caller supplies, drawn between the known ones and the actions. */
export interface ExtraColumn<T> {
    key: string;
    header: string;
    className?: string;
    render: (row: T) => ReactNode;
}

export interface SelectionProps {
    selected: Set<number>;
    onToggle: (id: number, next: boolean) => void;
    onToggleAll: (next: boolean) => void;
}

interface Props<T extends CatalogRow> {
    sites: T[];
    ranges: CatalogRangeSet;
    projectId?: number | null;
    loading?: boolean;
    onOpenDetail?: (site: T) => void;
    /**
     * Which of the known columns to draw. Defaults to all eight — the catalog's
     * own set. The lists pass a reduced set, because a shortlist is read for
     * different reasons than a search result and six of these are noise there.
     */
    columns?: CatalogColumn[];
    extraColumns?: ExtraColumn<T>[];
    /** Replaces the catalog's own buy-and-menu cell. */
    renderActions?: (site: T) => ReactNode;
    /** Adds a leading checkbox column. */
    selection?: SelectionProps;
    /** The minimum width the table needs before it scrolls sideways. */
    minWidth?: string;
    /**
     * How wide the trailing actions cell is. The catalog's own cell holds a
     * Buy button and a menu and needs every pixel of the default; a list row
     * holds two icon buttons, and 168px of reserved space there is 168px the
     * note column does not get.
     */
    actionsWidth?: string;
    /** Off on the blacklist tab, where every row is blacklisted by definition. */
    showBlacklistState?: boolean;
}

/** 56px, to fit the QuantBars. Also the row height the virtualiser assumes. */
const ROW_HEIGHT = 56;

/**
 * The catalog table.
 *
 * Hand-built rather than the shared Table component, for one reason that turns
 * out to matter: above a hundred rows this only renders the ones on screen, and
 * that needs control of the row container's height and offset. Everything else
 * — 56px rows, the sticky header, the hover tint — matches the shared table by
 * using the same tokens.
 */
export function CatalogTable<T extends CatalogRow>({
    sites,
    ranges,
    projectId = null,
    loading = false,
    onOpenDetail,
    columns = ALL_COLUMNS,
    extraColumns = [],
    renderActions,
    selection,
    minWidth = '980px',
    actionsWidth = 'w-[168px]',
    showBlacklistState = true,
}: Props<T>) {
    const { containerRef, spacerBefore, spacerAfter, visible } = useVirtualRows(sites, ROW_HEIGHT);

    const headings: [string, string][] = [
        ...columns.map((column) => HEADINGS[column]),
        ...extraColumns.map((column): [string, string] => [column.header, column.className ?? '']),
        ['', actionsWidth],
    ];

    const cellCount = headings.length + (selection ? 1 : 0);
    const allSelected = selection !== undefined && sites.length > 0 && sites.every((site) => selection.selected.has(site.id));

    return (
        // `relative`, so the box is the containing block for its own absolutely
        // positioned content. Without it an sr-only label deep inside a cell —
        // absolute, and so laid out against <body> instead — is never clipped by
        // this scroller, and its position out at x=820 in a 1180px table pushes
        // the *document* sideways on a phone while the table sits contained.
        <div
            ref={containerRef}
            className="relative overflow-x-auto rounded-card border border-subtle bg-card shadow-card"
        >
            <table
                className="table-sticky-head table-sticky-action w-full border-collapse text-left text-base"
                style={{ minWidth }}
            >
                <caption className="sr-only">Websites matching your filters</caption>

                <thead>
                    <tr>
                        {selection && (
                            <th scope="col" className="w-10 border-b border-subtle bg-sunken px-3 py-3">
                                <Checkbox
                                    label="Select every row"
                                    hideLabel
                                    checked={allSelected}
                                    indeterminate={selection.selected.size > 0 && !allSelected}
                                    onChange={(event) => selection.onToggleAll(event.target.checked)}
                                />
                            </th>
                        )}

                        {headings.map(([heading, className], index) => (
                            <th
                                key={heading || index}
                                scope="col"
                                className={cn(
                                    'border-b border-subtle bg-sunken px-3 py-3 text-sm font-medium text-ink-500',
                                    className,
                                )}
                            >
                                {heading}
                            </th>
                        ))}
                    </tr>
                </thead>

                <tbody>
                    {loading && sites.length === 0
                        ? Array.from({ length: 8 }, (_, index) => (
                              <tr key={index} style={{ height: ROW_HEIGHT }}>
                                  {Array.from({ length: cellCount }, (_, cell) => (
                                      <td key={cell} className="border-b border-subtle px-3">
                                          <Skeleton className="h-4 w-full" />
                                      </td>
                                  ))}
                              </tr>
                          ))
                        : null}

                    {/* Two spacer rows hold the height of everything scrolled
                        past and everything still below, so the scrollbar stays
                        honest about how much catalog there is. */}
                    {spacerBefore > 0 && <tr aria-hidden="true" style={{ height: spacerBefore }} />}

                    {visible.map((site) => (
                        <tr
                            key={site.id}
                            tabIndex={onOpenDetail ? 0 : undefined}
                            role={onOpenDetail ? 'button' : undefined}
                            aria-label={onOpenDetail ? `Open ${site.domain}` : undefined}
                            onClick={onOpenDetail === undefined ? undefined : () => onOpenDetail(site)}
                            onKeyDown={(event) => {
                                if (onOpenDetail && (event.key === 'Enter' || event.key === ' ')) {
                                    event.preventDefault();
                                    onOpenDetail(site);
                                }
                            }}
                            style={{ height: ROW_HEIGHT }}
                            className={cn(
                                'border-b border-subtle hover:bg-row-hover',
                                onOpenDetail && 'cursor-pointer',
                                // Dimmed, not hidden: it is only on screen
                                // because the buyer asked to see blacklisted
                                // sites, and it still has to be actionable.
                                // On the cells rather than the row, because the
                                // pinned action column paints its own opaque
                                // background and would otherwise stay bright.
                                // The blacklist tab is where they all are, so it
                                // opts out — dimming every row says nothing.
                                site.isBlacklisted && showBlacklistState && '[&>td]:opacity-50',
                            )}
                        >
                            {selection && (
                                <td className="px-3" onClick={(event) => event.stopPropagation()}>
                                    <Checkbox
                                        label={`Select ${site.domain}`}
                                        hideLabel
                                        checked={selection.selected.has(site.id)}
                                        onChange={(event) => selection.onToggle(site.id, event.target.checked)}
                                    />
                                </td>
                            )}

                            {columns.map((column) => (
                                <td
                                    key={column}
                                    className={cn(
                                        'px-3',
                                        (column === 'published' || column === 'price') && 'whitespace-nowrap',
                                        column === 'spam' && 'text-right',
                                        column === 'published' && 'text-sm text-ink-700',
                                    )}
                                >
                                    {column === 'website' && (
                                        <SiteIdentity site={site} showBlacklist={showBlacklistState} />
                                    )}
                                    {column === 'category' && <CategoryPill name={site.category} />}
                                    {column === 'traffic' && (
                                        <MetricCell value={site.traffic} range={ranges.traffic} />
                                    )}
                                    {column === 'dr' && (
                                        <MetricCell value={site.domainRating} range={ranges.domainRating} exact />
                                    )}
                                    {column === 'da' && (
                                        <MetricCell
                                            value={site.domainAuthority}
                                            range={ranges.domainAuthority}
                                            exact
                                        />
                                    )}
                                    {column === 'spam' && <SpamCell score={site.spamScore} />}
                                    {column === 'published' && site.publicationLabel}
                                    {column === 'price' && <PriceCell site={site} />}
                                </td>
                            ))}

                            {extraColumns.map((column) => (
                                <td
                                    key={column.key}
                                    className={cn('px-3', column.className)}
                                    onClick={(event) => event.stopPropagation()}
                                >
                                    {column.render(site)}
                                </td>
                            ))}

                            <td className="whitespace-nowrap px-3" onClick={(event) => event.stopPropagation()}>
                                {renderActions
                                    ? renderActions(site)
                                    : onOpenDetail && (
                                          <SiteActions
                                              site={site}
                                              projectId={projectId}
                                              // The row this fires with is the
                                              // one it was given, so narrowing
                                              // the parameter back to T is safe.
                                              onOpenDetail={() => onOpenDetail(site)}
                                          />
                                      )}
                            </td>
                        </tr>
                    ))}

                    {spacerAfter > 0 && <tr aria-hidden="true" style={{ height: spacerAfter }} />}
                </tbody>
            </table>
        </div>
    );
}

/**
 * A QuantBar, or an em dash where nothing was measured.
 *
 * A site with no metric row has not been assessed. Drawing it as a bar at zero
 * would rank it below every measured site and read as a fact about the site
 * rather than about our data.
 */
function MetricCell({
    value,
    range,
    exact = false,
}: {
    value: number | null;
    range: [number, number];
    exact?: boolean;
}) {
    if (value === null) {
        return <span className="block text-right text-ink-500">—</span>;
    }

    return <QuantBar value={value} range={range} format={exact ? String : undefined} />;
}
