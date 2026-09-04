import { QuantBar, Skeleton } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type { CatalogRangeSet, CatalogRow } from '@shared/types/catalog';
import { CategoryPill, PriceCell, SiteIdentity, SpamCell } from './SiteCells';
import { SiteActions } from './SiteActions';

interface Props {
    sites: CatalogRow[];
    ranges: CatalogRangeSet;
    projectId: number | null;
    loading: boolean;
    onOpenDetail: (site: CatalogRow) => void;
}

/**
 * The same sites as cards.
 *
 * The three QuantBars stack as labelled rows here rather than sitting in
 * columns, which is the only real difference: in a table the column heading
 * names the measure, and on a card nothing else would.
 *
 * Price and the buy button are pinned to the footer of every card, so a grid of
 * cards of different heights still has one line your eye can run along.
 */
export function CatalogCards({ sites, ranges, projectId, loading, onOpenDetail }: Props) {
    if (loading && sites.length === 0) {
        return (
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                {Array.from({ length: 6 }, (_, index) => (
                    <div key={index} className="flex flex-col gap-3 rounded-card border border-subtle bg-card p-4">
                        <Skeleton className="h-5 w-2/3" />
                        <Skeleton className="h-3 w-full" />
                        <Skeleton className="h-3 w-full" />
                        <Skeleton className="h-3 w-full" />
                        <Skeleton className="h-9 w-full" />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <ul className="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
            {sites.map((site) => (
                <li key={site.id}>
                    <article
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
                        className={cn(
                            'flex h-full cursor-pointer flex-col gap-3 rounded-card border border-subtle bg-card p-4 shadow-card',
                            'hover:border-strong',
                            site.isBlacklisted && 'opacity-50',
                        )}
                    >
                        <SiteIdentity site={site} />

                        <div className="flex flex-wrap items-center gap-2">
                            <CategoryPill name={site.category} />
                            <span className="text-xs text-ink-500">{site.publicationLabel}</span>
                            <span className="ml-auto text-xs text-ink-500">
                                Spam <SpamCell score={site.spamScore} />
                            </span>
                        </div>

                        <dl className="flex flex-col gap-2">
                            {(
                                [
                                    ['Monthly traffic', site.traffic, ranges.traffic, false],
                                    ['DR', site.domainRating, ranges.domainRating, true],
                                    ['DA', site.domainAuthority, ranges.domainAuthority, true],
                                ] as const
                            ).map(([label, value, range, exact]) => (
                                <div key={label} className="flex items-center gap-3">
                                    <dt className="w-28 shrink-0 text-xs text-ink-500">{label}</dt>
                                    <dd className="min-w-0 flex-1">
                                        {value === null ? (
                                            <span className="text-sm text-ink-500">Not measured</span>
                                        ) : (
                                            <QuantBar
                                                value={value}
                                                range={range}
                                                format={exact ? String : undefined}
                                                className="!items-start"
                                            />
                                        )}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        {/* mt-auto is what pins the footer: cards in a row are
                            as tall as the tallest, and without it the price
                            floats wherever the content above happens to end. */}
                        <div
                            className="mt-auto flex items-end justify-between gap-3 border-t border-subtle pt-3"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <PriceCell site={site} align="left" />
                            <SiteActions site={site} projectId={projectId} onOpenDetail={onOpenDetail} />
                        </div>
                    </article>
                </li>
            ))}
        </ul>
    );
}
