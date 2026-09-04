import { QuantBar, Skeleton } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type { CatalogRangeSet, CatalogRow } from '@shared/types/catalog';
import { CategoryPill, PriceCell, SiteIdentity, SpamCell } from './SiteCells';
import { SiteActions } from './SiteActions';
import { useVirtualRows } from './useVirtualRows';

interface Props {
    sites: CatalogRow[];
    ranges: CatalogRangeSet;
    projectId: number | null;
    loading: boolean;
    onOpenDetail: (site: CatalogRow) => void;
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
export function CatalogTable({ sites, ranges, projectId, loading, onOpenDetail }: Props) {
    const { containerRef, spacerBefore, spacerAfter, visible } = useVirtualRows(sites, ROW_HEIGHT);

    return (
        <div ref={containerRef} className="overflow-x-auto rounded-card border border-subtle bg-card shadow-card">
            <table className="table-sticky-head table-sticky-action w-full min-w-[980px] border-collapse text-left text-base">
                <caption className="sr-only">Websites matching your filters</caption>

                <thead>
                    <tr>
                        {[
                            ['Website', 'w-[200px]'],
                            ['Category', ''],
                            ['Monthly traffic', 'text-right'],
                            ['DR', 'text-right'],
                            ['DA', 'text-right'],
                            ['Spam', 'text-right'],
                            ['Published in', ''],
                            ['Price', 'text-right'],
                            ['', 'w-[168px]'],
                        ].map(([heading, className], index) => (
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
                                  {Array.from({ length: 9 }, (_, cell) => (
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
                            tabIndex={0}
                            role="button"
                            aria-label={`Open ${site.domain}`}
                            onClick={() => onOpenDetail(site)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' || event.key === ' ') {
                                    event.preventDefault();
                                    onOpenDetail(site);
                                }
                            }}
                            style={{ height: ROW_HEIGHT }}
                            className={cn(
                                'cursor-pointer border-b border-subtle hover:bg-row-hover',
                                // Dimmed, not hidden: it is only on screen
                                // because the buyer asked to see blacklisted
                                // sites, and it still has to be actionable.
                                // On the cells rather than the row, because the
                                // pinned action column paints its own opaque
                                // background and would otherwise stay bright.
                                site.isBlacklisted && '[&>td]:opacity-50',
                            )}
                        >
                            <td className="px-3">
                                <SiteIdentity site={site} />
                            </td>
                            <td className="px-3">
                                <CategoryPill name={site.category} />
                            </td>
                            <td className="px-3">
                                <MetricCell value={site.traffic} range={ranges.traffic} />
                            </td>
                            <td className="px-3">
                                <MetricCell value={site.domainRating} range={ranges.domainRating} exact />
                            </td>
                            <td className="px-3">
                                <MetricCell value={site.domainAuthority} range={ranges.domainAuthority} exact />
                            </td>
                            <td className="px-3 text-right">
                                <SpamCell score={site.spamScore} />
                            </td>
                            <td className="whitespace-nowrap px-3 text-sm text-ink-700">{site.publicationLabel}</td>
                            <td className="whitespace-nowrap px-3">
                                <PriceCell site={site} />
                            </td>
                            <td className="whitespace-nowrap px-3" onClick={(event) => event.stopPropagation()}>
                                <SiteActions site={site} projectId={projectId} onOpenDetail={onOpenDetail} />
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
