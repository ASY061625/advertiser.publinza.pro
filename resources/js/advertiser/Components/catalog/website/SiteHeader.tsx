import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ExternalLinkIcon, HeartIcon, IconButton } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { money } from '@shared/lib/format';
import type { CatalogRow, CatalogSiteDetail } from '@shared/types/catalog';
import { flagFor } from '../flags';
import { SiteOverflowMenu } from './SiteOverflowMenu';

/**
 * A row is enough to draw the whole header. The drawer already has one when it
 * opens, so the headline paints immediately and the fees fill in behind it,
 * rather than a spinner sitting over facts the page already had.
 */
type HeaderSite = CatalogRow & Partial<Pick<CatalogSiteDetail, 'homepage' | 'services'>>;

interface Props {
    site: HeaderSite;
    /** The page renders it as an h1; inside the drawer it is an h2 under the dialog's own label. */
    heading?: 'h1' | 'h2';
    /** Right of the price. The drawer puts its previous/next pair here. */
    aside?: ReactNode;
}

/**
 * Who this site is, and what it costs.
 *
 * The price sits in the header rather than beside the buy button because it is
 * the thing being decided, not the thing being clicked — a buyer scrolling nine
 * metric tiles is scrolling them against a number, and a number that is only
 * visible next to the footer button is a number they have to scroll back for.
 * The fees are secondary lines under it: they are conditional, and printing
 * them at the same weight would make every site look like it costs three
 * numbers.
 */
export function SiteHeader({ site, heading = 'h2', aside }: Props) {
    const Heading = heading;
    const service = site.services?.[0];
    const homepage = site.homepage ?? `https://${site.domain}`;

    return (
        <div className="flex min-w-0 flex-1 flex-col gap-3">
            <div className="flex min-w-0 items-start gap-3">
                <Favicon domain={site.domain} />

                <div className="min-w-0 flex-1">
                    <div className="flex min-w-0 items-center gap-2">
                        <Heading className="truncate font-sora text-lg font-semibold text-ink-900">
                            {site.domain}
                        </Heading>

                        {/* The homepage, live. Every other fact on this screen
                            is somebody's summary of the site; this is the site. */}
                        <a
                            href={homepage}
                            target="_blank"
                            rel="noopener noreferrer"
                            title={`Open ${site.domain} in a new tab`}
                            className="shrink-0 rounded-button p-1 text-ink-500 hover:bg-sunken hover:text-brand"
                        >
                            <ExternalLinkIcon size={14} />
                            <span className="sr-only">Open {site.domain} in a new tab</span>
                        </a>
                    </div>

                    <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-500">
                        <span className="rounded-pill bg-sunken px-2 py-0.5 text-xs font-medium text-ink-700">
                            {site.category}
                        </span>

                        {/* A language gets its code, not a flag. There is no
                            flag for English, and the regional-indicator pair
                            for "en" is not a country — it renders as an empty
                            box, which is how the catalog table already learned
                            to show the code instead. The country, which is a
                            country, keeps its flag. */}
                        <span className="whitespace-nowrap">
                            <span className="num mr-1 rounded bg-sunken px-1 text-xs uppercase text-ink-700">
                                {site.language.code}
                            </span>
                            {site.language.name}
                        </span>

                        <span aria-hidden="true">·</span>

                        <span className="whitespace-nowrap">
                            <span aria-hidden="true">{flagFor(site.country.code)} </span>
                            {site.country.name}
                        </span>
                    </p>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <FavoriteHeart site={site} />
                    <SiteOverflowMenu site={site} />
                </div>
            </div>

            <div className="flex items-baseline gap-3">
                <div className="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-3 gap-y-0.5">
                    <span className="num font-sora text-xl font-semibold text-ink-900">{money(site.priceCents)}</span>

                    {(service?.writingFeeCents ?? 0) > 0 && (
                        <span className="num text-sm text-ink-500">
                            +{money(service!.writingFeeCents)} if we write it
                        </span>
                    )}

                    {(service?.expressFeeCents ?? 0) > 0 && (
                        <span className="num text-sm text-ink-500">+{money(service!.expressFeeCents)} express</span>
                    )}
                </div>

                {aside}
            </div>
        </div>
    );
}

/**
 * The site's own favicon, with its initial behind it.
 *
 * Fetched from the site rather than from a favicon service: Publinza owns every
 * domain in this catalog, so there is nothing to gain by telling a third party
 * which one is being looked at. When it fails to load — and on a fair number of
 * domains it will — the monogram underneath is already drawn, so nothing moves.
 */
function Favicon({ domain }: { domain: string }) {
    const [failed, setFailed] = useState(false);

    return (
        <span className="flex size-10 shrink-0 items-center justify-center rounded-card border border-subtle bg-sunken font-sora text-md font-semibold uppercase text-ink-500">
            {failed ? (
                domain.charAt(0)
            ) : (
                <img
                    src={`https://${domain}/favicon.ico`}
                    alt=""
                    width={20}
                    height={20}
                    loading="lazy"
                    onError={() => setFailed(true)}
                    className="size-5 object-contain"
                />
            )}
        </span>
    );
}

function FavoriteHeart({ site }: { site: HeaderSite }) {
    return (
        <IconButton
            label={site.isFavorite ? `Remove ${site.domain} from favorites` : `Add ${site.domain} to favorites`}
            variant="ghost"
            size="sm"
            onClick={() =>
                router.post(`/sites/${site.slug}/favorite`, {}, { preserveScroll: true, preserveState: true })
            }
            icon={
                <HeartIcon
                    size={18}
                    className={cn(site.isFavorite && 'fill-current text-danger')}
                />
            }
        />
    );
}
