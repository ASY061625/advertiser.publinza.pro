import { router } from '@inertiajs/react';
import { Button, CartIcon, CheckIcon, Dropdown, IconButton, MoreIcon, Tooltip } from '@shared/ui';
import type { CatalogRow } from '@shared/types/catalog';

interface Props {
    site: CatalogRow;
    projectId: number | null;
    onOpenDetail: (site: CatalogRow) => void;
    size?: 'sm' | 'md';
}

/** The props the catalog re-reads after any list or cart change. */
const RESULT_PROPS = ['sites', 'total', 'facets', 'flash'];

/**
 * The buy control and the overflow menu, shared by the table and the cards.
 *
 * In browse mode the button is disabled and says why on hover rather than being
 * hidden. A missing button reads as "this site cannot be bought"; a disabled one
 * that says "Choose a project first" reads as "not yet, and here is the step".
 */
export function SiteActions({ site, projectId, onOpenDetail, size = 'sm' }: Props) {
    const inCart = site.cartItemId !== null;

    function addToCart() {
        router.post(
            `/cart/${site.slug}`,
            {
                service_type: 'article_placement',
                // The publisher writes it only if they offer to; otherwise the
                // advertiser does. Either way it is changeable in the cart —
                // this is the sane default, not a commitment.
                content_mode: site.writingFeeCents > 0 ? 'publisher_writes' : 'advertiser_provides',
                project_id: projectId,
            },
            { preserveScroll: true, preserveState: true, only: RESULT_PROPS },
        );
    }

    function removeFromCart() {
        if (site.cartItemId === null) return;

        router.delete(`/cart/${site.cartItemId}`, {
            preserveScroll: true,
            preserveState: true,
            only: RESULT_PROPS,
        });
    }

    function post(path: string) {
        router.post(path, {}, { preserveScroll: true, preserveState: true, only: RESULT_PROPS });
    }

    return (
        <div className="flex items-center justify-end gap-1">
            {inCart ? (
                <span className="flex items-center gap-1 whitespace-nowrap">
                    <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-button bg-success-bg px-2 py-1 text-xs font-medium text-success">
                        <CheckIcon size={12} />
                        In cart
                    </span>
                    <button
                        type="button"
                        onClick={removeFromCart}
                        className="text-xs text-ink-500 underline-offset-2 hover:text-danger hover:underline"
                    >
                        Remove
                    </button>
                </span>
            ) : projectId === null ? (
                <Tooltip content="Choose a project first">
                    {/* A span around the disabled button: a disabled control
                        fires no pointer events, so the tooltip explaining why
                        it is disabled would never open on the one element that
                        needs to explain itself. */}
                    <span className="inline-block">
                        <Button size={size} disabled>
                            <CartIcon size={14} />
                            Add to cart
                        </Button>
                    </span>
                </Tooltip>
            ) : (
                <Button size={size} onClick={addToCart}>
                    <CartIcon size={14} />
                    Add to cart
                </Button>
            )}

            <Dropdown
                trigger={
                    <IconButton
                        label={`More actions for ${site.domain}`}
                        variant="ghost"
                        size="sm"
                        icon={<MoreIcon size={16} />}
                    />
                }
                items={[
                    { id: 'view', label: 'View details', onSelect: () => onOpenDetail(site) },
                    {
                        id: 'favorite',
                        label: site.isFavorite ? 'Remove from favorites' : 'Add to favorites',
                        onSelect: () => post(`/sites/${site.slug}/favorite`),
                    },
                    {
                        id: 'wishlist',
                        label: 'Add to wishlist',
                        onSelect: () => post(`/sites/${site.slug}/wishlist`),
                    },
                    {
                        id: 'blacklist',
                        label: site.isBlacklisted ? 'Remove from blacklist' : 'Add to blacklist',
                        destructive: !site.isBlacklisted,
                        onSelect: () => post(`/sites/${site.slug}/blacklist`),
                    },
                    {
                        id: 'message',
                        label: 'Message about this site',
                        onSelect: () => router.visit(`/conversations?website=${site.id}`),
                    },
                ]}
            />
        </div>
    );
}
