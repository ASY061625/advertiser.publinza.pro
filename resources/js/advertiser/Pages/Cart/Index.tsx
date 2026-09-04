import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AppShell } from '../../Layouts/AppShell';
import { Button, CartIcon, EmptyState, Tooltip } from '@shared/ui';
import type { CartLine, CartPayload, CartProject, CartWallet } from '@shared/types/cart';
import { BulkBar } from '../../Components/cart/BulkBar';
import { CartGroup } from '../../Components/cart/CartGroup';
import { EditItemModal } from '../../Components/cart/EditItemModal';
import { OrderSummary } from '../../Components/cart/OrderSummary';
import { TopUpModal } from '../../Components/cart/TopUpModal';
import { WalletPanel } from '../../Components/cart/WalletPanel';

interface Props {
    cart: CartPayload;
    wallet: CartWallet;
    projects: CartProject[];
}

/**
 * The cart: lines on the left, the money on the right.
 *
 * The summary is sticky because the two columns answer each other. Every change
 * on the left moves a number on the right, and a total that scrolls out of view
 * turns "should I add one more site" into a question you have to scroll to
 * answer.
 */
export default function CartIndex({ cart, wallet, projects }: Props) {
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [editing, setEditing] = useState<CartLine | null>(null);
    const [topUpOpen, setTopUpOpen] = useState(false);

    const allIds = useMemo(
        () => cart.groups.flatMap((group) => group.items.map((item) => item.id)),
        [cart.groups],
    );

    // Two reasons the button cannot be pressed, and they need different
    // sentences: an empty cart is a different problem from an empty wallet.
    const short = Math.max(0, cart.totals.totalCents - wallet.availableCents);
    const unavailable = cart.groups.some((group) =>
        group.items.some((item) => item.warnings.some((warning) => warning.kind === 'unavailable')),
    );

    const blocker =
        cart.itemCount === 0
            ? 'Your cart is empty.'
            : unavailable
              ? 'One of these sites has withdrawn its service. Remove that line to continue.'
              : short > 0
                ? 'Your balance does not cover this order yet.'
                : null;

    function select(id: number, next: boolean) {
        setSelected((current) => {
            const updated = new Set(current);

            if (next) {
                updated.add(id);
            } else {
                updated.delete(id);
            }

            return updated;
        });
    }

    function selectMany(ids: number[], next: boolean) {
        setSelected((current) => {
            const updated = new Set(current);
            for (const id of ids) {
                if (next) {
                    updated.add(id);
                } else {
                    updated.delete(id);
                }
            }

            return updated;
        });
    }

    return (
        <AppShell title="Cart" crumbs={[{ label: 'Cart' }]}>
            <Head title="Your cart" />

            {cart.itemCount === 0 ? (
                <EmptyState
                    illustration={<CartIcon size={26} />}
                    direction="Your cart is empty"
                    body="Sites you add from the catalog wait here until you check out — across sessions, browsers and devices. Nothing expires."
                    action={
                        <Link href="/catalog">
                            <Button size="lg">Browse the catalog</Button>
                        </Link>
                    }
                />
            ) : (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <div className="flex flex-col gap-4 lg:col-span-8">
                        <BulkBar
                            total={allIds.length}
                            selected={selected}
                            projects={projects}
                            onSelectAll={(next) => selectMany(allIds, next)}
                        />

                        {cart.groups.map((group) => (
                            <CartGroup
                                key={group.id}
                                group={group}
                                selected={selected}
                                onSelect={select}
                                onSelectGroup={selectMany}
                                onEdit={setEditing}
                            />
                        ))}
                    </div>

                    <aside className="lg:col-span-4">
                        <div className="sticky top-[calc(theme(spacing.header)+1rem)] flex flex-col gap-4 rounded-card border border-subtle bg-card p-5 shadow-card">
                            <OrderSummary totals={cart.totals} promo={cart.promo} />

                            <WalletPanel
                                wallet={wallet}
                                totalCents={cart.totals.totalCents}
                                onTopUp={() => setTopUpOpen(true)}
                            />

                            {blocker === null ? (
                                <Button size="lg" onClick={() => router.visit('/checkout')}>
                                    Proceed to checkout
                                </Button>
                            ) : (
                                <Tooltip content={blocker}>
                                    {/* A disabled button fires no pointer
                                        events, so the explanation goes on a
                                        wrapper — otherwise the one control that
                                        needs to explain itself is the one that
                                        cannot. */}
                                    <span className="block">
                                        <Button size="lg" disabled className="w-full">
                                            Proceed to checkout
                                        </Button>
                                    </span>
                                </Tooltip>
                            )}

                            <p className="text-xs text-ink-500">
                                Checking out freezes this amount against your order. It is released to each
                                publisher only after their link is verified as live.
                            </p>
                        </div>
                    </aside>
                </div>
            )}

            <EditItemModal item={editing} projects={projects} onClose={() => setEditing(null)} />

            <TopUpModal open={topUpOpen} onClose={() => setTopUpOpen(false)} suggestedCents={short} />
        </AppShell>
    );
}
