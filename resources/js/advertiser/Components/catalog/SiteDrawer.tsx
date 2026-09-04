import { useCallback, useEffect, useState } from 'react';
import { Button, CartIcon, ChevronDownIcon, ChevronUpIcon, Drawer, IconButton, Skeleton } from '@shared/ui';
import type { BuyingConfig, CatalogRangeSet, CatalogRow, CatalogSiteDetail } from '@shared/types/catalog';
import { BuyBar } from './website/BuyBar';
import { SiteHeader } from './website/SiteHeader';
import { WebsiteDetail } from './website/WebsiteDetail';

interface Props {
    /** The whole loaded result set, so J and K have somewhere to go. */
    sites: CatalogRow[];
    /** Which of them is open, or null when the drawer is shut. */
    index: number | null;
    projectId: number | null;
    ranges: CatalogRangeSet;
    onNavigate: (index: number) => void;
    onClose: () => void;
}

const EMPTY: BuyingConfig = { folders: [], landingPages: [] };

/**
 * One site, opened from a row.
 *
 * The row is already on the client, so the header paints immediately and the
 * body fills in when the fetch lands. Waiting for the whole payload before
 * showing anything would put a spinner over facts the page already had.
 *
 * J and K move through the result set without closing, because comparing four
 * candidate sites is the actual job here and doing it through open-read-close
 * costs three clicks a site and loses your place in the list every time.
 */
export function SiteDrawer({ sites, index, projectId, ranges, onNavigate, onClose }: Props) {
    const [detail, setDetail] = useState<CatalogSiteDetail | null>(null);
    const [buying, setBuying] = useState<BuyingConfig>(EMPTY);
    const [failed, setFailed] = useState(false);

    const site = index === null ? null : (sites[index] ?? null);
    const slug = site?.slug ?? null;

    // The row wins over the fetched payload wherever they overlap. The payload
    // is fetched once when the drawer opens; the row is re-fetched by Inertia
    // after every favourite, blacklist and cart change on the page behind it —
    // so without this the footer would still offer "Add to cart" for something
    // already in the cart.
    const merged = detail === null || site === null ? null : { ...detail, ...site };

    useEffect(() => {
        if (slug === null) return;

        setDetail(null);
        setFailed(false);

        const controller = new AbortController();
        const query = projectId === null ? '' : `?project=${projectId}`;

        fetch(`/catalog/website/${slug}${query}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
            .then((data: CatalogSiteDetail & { buying: BuyingConfig }) => {
                setDetail(data);
                setBuying(data.buying ?? EMPTY);
            })
            .catch((reason: unknown) => {
                if (reason instanceof DOMException && reason.name === 'AbortError') return;

                setFailed(true);
            });

        return () => controller.abort();
    }, [projectId, slug]);

    const step = useCallback(
        (delta: number) => {
            if (index === null) return;

            const next = index + delta;

            if (next >= 0 && next < sites.length) onNavigate(next);
        },
        [index, onNavigate, sites.length],
    );

    useEffect(() => {
        if (index === null) return;

        function onKeyDown(event: KeyboardEvent) {
            // J and K are letters before they are shortcuts. Inside the report
            // dialog or the buy popover's anchor field they have to stay
            // letters, or typing "jk" navigates away mid-sentence.
            if (event.metaKey || event.ctrlKey || event.altKey || isTyping(event.target)) return;

            if (event.key === 'j' || event.key === 'J') {
                event.preventDefault();
                step(1);
            } else if (event.key === 'k' || event.key === 'K') {
                event.preventDefault();
                step(-1);
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [index, step]);

    if (site === null || index === null) return null;

    return (
        <Drawer
            open
            size="lg"
            onClose={onClose}
            title={site.domain}
            header={
                <SiteHeader
                    site={merged ?? site}
                    aside={
                        sites.length > 1 && (
                            <div className="flex shrink-0 items-center gap-1">
                                <IconButton
                                    label="Previous website (K)"
                                    variant="secondary"
                                    size="sm"
                                    disabled={index === 0}
                                    onClick={() => step(-1)}
                                    icon={<ChevronUpIcon size={14} />}
                                />
                                <span className="num w-10 text-center text-xs text-ink-500">
                                    {index + 1}/{sites.length}
                                </span>
                                <IconButton
                                    label="Next website (J)"
                                    variant="secondary"
                                    size="sm"
                                    disabled={index === sites.length - 1}
                                    onClick={() => step(1)}
                                    icon={<ChevronDownIcon size={14} />}
                                />
                            </div>
                        )
                    }
                />
            }
            footer={
                merged === null ? (
                    <Button disabled loading={!failed}>
                        <CartIcon size={14} />
                        Add to cart
                    </Button>
                ) : (
                    <BuyBar site={merged} projectId={projectId} buying={buying} />
                )
            }
        >
            {failed ? (
                <p className="text-base text-danger">
                    This site’s details could not be loaded.{' '}
                    <a href={`/catalog/website/${site.slug}`} className="font-medium underline">
                        Open it as a page instead
                    </a>
                    .
                </p>
            ) : merged === null ? (
                <div className="flex flex-col gap-3">
                    {Array.from({ length: 8 }, (_, row) => (
                        <Skeleton key={row} className="h-5 w-full" />
                    ))}
                </div>
            ) : (
                <WebsiteDetail site={merged} ranges={ranges} />
            )}
        </Drawer>
    );
}

/** True while focus is in something that eats letters. */
function isTyping(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) return false;

    return (
        target.isContentEditable ||
        target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement ||
        target instanceof HTMLSelectElement
    );
}
