import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Button, CartIcon, ChatIcon, Tooltip, useDismiss } from '@shared/ui';
import type { BuyingConfig, CatalogSiteDetail } from '@shared/types/catalog';
import { BuyPopover } from './BuyPopover';

interface Props {
    site: CatalogSiteDetail;
    projectId: number | null;
    buying: BuyingConfig;
}

/**
 * The two things to do about a site: buy it, or ask about it.
 *
 * "Add to cart" opens the configuration popover rather than adding straight
 * away, because the cart is where the money is frozen and an unconfigured cart
 * item is an order nobody has specified. In browse mode the button is present
 * and disabled with the reason on hover — a missing button reads as "this site
 * cannot be bought", a disabled one reads as "not yet, and here is the step".
 */
export function BuyBar({ site, projectId, buying }: Props) {
    const [open, setOpen] = useState(false);
    const panel = useRef<HTMLDivElement>(null);
    const anchor = useDismiss<HTMLDivElement>(open, () => setOpen(false), panel);
    const inCart = site.cartItemId !== null;

    return (
        <div className="flex flex-1 items-center justify-between gap-3">
            <Button
                variant="secondary"
                onClick={() => router.visit(`/conversations?website=${site.id}`)}
            >
                <ChatIcon size={14} />
                Ask a question
            </Button>

            <div ref={anchor} className="relative">
                {inCart ? (
                    <Button
                        variant="secondary"
                        onClick={() =>
                            router.delete(`/cart/${site.cartItemId}`, {
                                preserveScroll: true,
                                preserveState: true,
                            })
                        }
                    >
                        Remove from cart
                    </Button>
                ) : projectId === null ? (
                    <Tooltip content="Choose a project first">
                        {/* A disabled button fires no pointer events, so the
                            tooltip explaining why goes on a wrapper. */}
                        <span className="inline-block">
                            <Button disabled>
                                <CartIcon size={14} />
                                Add to cart
                            </Button>
                        </span>
                    </Tooltip>
                ) : (
                    <Button onClick={() => setOpen((value) => !value)} aria-expanded={open}>
                        <CartIcon size={14} />
                        Add to cart
                    </Button>
                )}

                {open && projectId !== null && (
                    <div
                        ref={panel}
                        className="absolute bottom-full right-0 z-20 mb-2 animate-scale-in rounded-card border border-subtle bg-card shadow-card"
                    >
                        <BuyPopover
                            site={site}
                            projectId={projectId}
                            buying={buying}
                            onDone={() => setOpen(false)}
                        />
                    </div>
                )}
            </div>
        </div>
    );
}
