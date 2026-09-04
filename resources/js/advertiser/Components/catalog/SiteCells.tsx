import { router } from '@inertiajs/react';
import { HeartIcon, Tooltip, WarningIcon } from '@shared/ui';
import { money } from '@shared/lib/format';
import { cn } from '@shared/lib/cn';
import type { CatalogRow } from '@shared/types/catalog';
import { flagFor } from './flags';

/**
 * The pieces a site is described by, shared between the table and the cards.
 *
 * Both layouts show the same facts, so they read the same components — the
 * alternative is two renderings of "price" that drift until a card and a row
 * disagree about what a site costs.
 */

/** Domain, title, and the two pills. The heart appears on hover or focus. */
export function SiteIdentity({ site, compact = false }: { site: CatalogRow; compact?: boolean }) {
    return (
        <div className="group/site flex min-w-0 items-center gap-2.5">
            <DomainMark domain={site.domain} />

            <div className="min-w-0 flex-1">
                <p className="flex min-w-0 items-center gap-1.5">
                    <span className={cn('truncate font-medium text-ink-900', site.isBlacklisted && 'line-through')}>
                        {site.domain}
                    </span>

                    <span className="shrink-0 rounded-pill bg-sunken px-1.5 py-0.5 text-xs uppercase text-ink-500">
                        {site.language.code}
                    </span>

                    {site.country.code && (
                        <Tooltip content={site.country.name}>
                            <span className="shrink-0 text-xs" aria-label={site.country.name}>
                                {flagFor(site.country.code)}
                            </span>
                        </Tooltip>
                    )}

                    {site.warnings.length > 0 && <WarningFlag site={site} />}
                    {site.isBlacklisted && <BlacklistedBadge slug={site.slug} />}
                </p>

                {!compact && <p className="truncate text-sm text-ink-500">{site.title}</p>}
            </div>

            <FavoriteHeart site={site} />
        </div>
    );
}

/**
 * The 20px mark beside a domain.
 *
 * A monogram, not a favicon fetched from a third party. Publinza owns and runs
 * every site in this catalog, so the icons could be served from here — but until
 * they are, asking an external service for two hundred of them on every page
 * load would ship the whole catalog, and which parts of it a buyer is browsing,
 * to somebody else. The dashboard made the same call for the same reason.
 */
function DomainMark({ domain }: { domain: string }) {
    return (
        <span
            aria-hidden="true"
            className="flex size-5 shrink-0 items-center justify-center rounded-[4px] bg-sunken text-xs font-semibold text-ink-700"
        >
            {(domain.replace(/^www\./, '')[0] ?? '?').toUpperCase()}
        </span>
    );
}

/**
 * Favourite, from the row.
 *
 * Visible on hover and on focus, and always visible once it is on — a heart
 * that only appears on hover is a heart nobody knows they already pressed, and
 * one that only appears on hover is unreachable by keyboard.
 */
function FavoriteHeart({ site }: { site: CatalogRow }) {
    return (
        <button
            type="button"
            aria-pressed={site.isFavorite}
            aria-label={site.isFavorite ? `Remove ${site.domain} from favorites` : `Add ${site.domain} to favorites`}
            onClick={(event) => {
                event.stopPropagation();
                router.post(
                    `/sites/${site.slug}/favorite`,
                    {},
                    { preserveScroll: true, preserveState: true, only: ['sites', 'total', 'facets', 'flash'] },
                );
            }}
            className={cn(
                'shrink-0 rounded-button p-1 transition-opacity',
                'focus-visible:opacity-100 group-hover/site:opacity-100',
                site.isFavorite ? 'text-danger opacity-100' : 'text-ink-500 opacity-0 hover:text-danger',
            )}
        >
            {/* The icon set draws outlines; a favourited heart is the same
                glyph filled, which the shared Svg allows because it spreads
                props after its own fill="none". */}
            <HeartIcon size={14} fill={site.isFavorite ? 'currentColor' : 'none'} />
        </button>
    );
}

/**
 * The amber flag for a project mismatch.
 *
 * Informational. The row is not hidden and the button is not disabled — a
 * publisher who does not take the project's topic may still be the right site
 * for a different article, and the catalog is not the right place to decide
 * that on the buyer's behalf.
 */
function WarningFlag({ site }: { site: CatalogRow }) {
    return (
        <Tooltip content={site.warnings.map((warning) => warning.message).join(' ')}>
            <span className="shrink-0 text-warning" aria-label="Does not match this project in some ways">
                <WarningIcon size={13} />
            </span>
        </Tooltip>
    );
}

/**
 * Every route that acts on a site takes its slug.
 *
 * `Website::getRouteKeyName()` is `slug`, so posting a numeric id here binds
 * nothing and 404s — silently, from a button that looks like it worked.
 */
function BlacklistedBadge({ slug }: { slug: string }) {
    return (
        <span className="flex shrink-0 items-center gap-1">
            <span className="rounded-pill bg-sunken px-1.5 py-0.5 text-xs text-ink-500">Blacklisted</span>
            <button
                type="button"
                onClick={(event) => {
                    event.stopPropagation();
                    router.post(
                        `/sites/${slug}/blacklist`,
                        {},
                        { preserveScroll: true, preserveState: true, only: ['sites', 'total', 'facets', 'flash'] },
                    );
                }}
                className="text-xs text-brand hover:underline"
            >
                Remove
            </button>
        </span>
    );
}

/** Spam is a plain number, tinted once it is worth a second look. */
export function SpamCell({ score }: { score: number | null }) {
    if (score === null) return <span className="text-ink-500">—</span>;

    return <span className={cn('num', score > 30 ? 'font-medium text-danger' : 'text-ink-900')}>{score}</span>;
}

/** The price, and what the publisher charges to write the article. */
export function PriceCell({ site, align = 'right' }: { site: CatalogRow; align?: 'right' | 'left' }) {
    return (
        <div className={cn('flex flex-col', align === 'right' ? 'items-end' : 'items-start')}>
            <span className="num font-semibold text-ink-900">{money(site.priceCents)}</span>
            {site.writingFeeCents > 0 && (
                <span className="num text-xs text-ink-500">
                    +{money(site.writingFeeCents).replace(/\.00$/, '')} writing
                </span>
            )}
        </div>
    );
}

export function CategoryPill({ name }: { name: string }) {
    return <span className="rounded-pill bg-sunken px-2 py-0.5 text-xs text-ink-700">{name}</span>;
}
