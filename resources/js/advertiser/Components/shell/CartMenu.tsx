import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { CartIcon, useDismiss } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { Shell } from '@shared/types/shell';
import { HeaderButton } from './HeaderButton';

export function CartMenu({ cart, count }: { cart: Shell['cart']; count: number }) {
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    return (
        <div ref={ref} className="relative">
            <HeaderButton
                label="Cart"
                count={count}
                expanded={open}
                onClick={() => setOpen((v) => !v)}
                icon={<CartIcon size={18} />}
            />

            {open && (
                <div className="absolute right-0 z-50 mt-1 w-80 animate-scale-in overflow-hidden rounded-card border border-subtle bg-card shadow-card">
                    {cart.items.length === 0 ? (
                        <div className="flex flex-col items-center gap-4 px-6 py-10 text-center">
                            <p className="text-base text-ink-700">Your cart is empty. Find sites in the catalog.</p>
                            <Link
                                href="/catalog"
                                onClick={() => setOpen(false)}
                                className="flex h-8 items-center justify-center rounded-button bg-brand px-3 font-sora text-sm font-medium text-white transition-colors duration-fast hover:bg-brand-hover"
                            >
                                Browse the catalog
                            </Link>
                        </div>
                    ) : (
                        <>
                            <ul>
                                {cart.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-start justify-between gap-3 border-b border-subtle px-4 py-3"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-base font-medium text-ink-900">
                                                {item.domain}
                                            </span>
                                            <span className="block truncate text-sm text-ink-500">
                                                {item.project ?? 'No project'}
                                            </span>
                                        </span>
                                        <span className="num shrink-0 text-base text-ink-900">
                                            {money(item.priceCents)}
                                        </span>
                                    </li>
                                ))}
                            </ul>

                            {cart.moreCount > 0 && (
                                <p className="num border-b border-subtle px-4 py-2 text-sm text-ink-500">
                                    and {cart.moreCount} more
                                </p>
                            )}

                            {/* The subtotal is the whole cart, not just the five
                                lines shown above. */}
                            <div className="flex items-center justify-between px-4 py-3">
                                <span className="text-base text-ink-700">Subtotal</span>
                                <span className="num font-sora text-md font-semibold text-ink-900">
                                    {money(cart.subtotalCents)}
                                </span>
                            </div>

                            <div className="flex gap-2 border-t border-subtle p-3">
                                <Link
                                    href="/cart"
                                    onClick={() => setOpen(false)}
                                    className="flex h-8 flex-1 items-center justify-center rounded-button border border-subtle bg-card font-sora text-sm font-medium text-ink-700 hover:bg-sunken"
                                >
                                    View cart
                                </Link>
                                <Link
                                    href="/checkout"
                                    onClick={() => setOpen(false)}
                                    className="flex h-8 flex-1 items-center justify-center rounded-button bg-brand font-sora text-sm font-medium text-white hover:bg-brand-hover"
                                >
                                    Checkout
                                </Link>
                            </div>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
