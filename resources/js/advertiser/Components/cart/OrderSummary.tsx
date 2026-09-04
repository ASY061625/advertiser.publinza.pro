import { router, useForm } from '@inertiajs/react';
import { Button, Input, XIcon } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { CartPromo, CartTotals } from '@shared/types/cart';

interface Props {
    totals: CartTotals;
    promo: CartPromo | null;
}

/**
 * What the order comes to, line by line.
 *
 * The subtotal is base prices only, so the two fee lines beneath it read as
 * additions rather than as a breakdown of a number that already contains them.
 * That is the difference between a summary a buyer can check against the cart
 * above it and one they have to take on trust.
 */
export function OrderSummary({ totals, promo }: Props) {
    return (
        <div className="flex flex-col gap-3">
            <h2 className="font-sora text-md font-semibold text-ink-900">Order summary</h2>

            <dl className="flex flex-col gap-2 text-base">
                <Row label="Placements" value={totals.subtotalCents} />

                {totals.writingFeesCents > 0 && (
                    <Row label="Writing fees" value={totals.writingFeesCents} prefix="+" muted />
                )}

                {totals.expressFeesCents > 0 && (
                    <Row label="Express delivery" value={totals.expressFeesCents} prefix="+" muted />
                )}

                {totals.discountCents > 0 && (
                    <div className="flex items-baseline justify-between gap-3">
                        <dt className="text-success">{promo ? `Discount (${promo.code})` : 'Discount'}</dt>
                        <dd className="num font-medium text-success">−{money(totals.discountCents)}</dd>
                    </div>
                )}
            </dl>

            <PromoField promo={promo} />

            <div className="flex items-baseline justify-between gap-3 border-t border-subtle pt-3">
                <span className="font-sora text-md font-semibold text-ink-900">Total</span>
                <span className="num font-sora text-xl font-semibold text-ink-900">
                    {money(totals.totalCents)}
                </span>
            </div>
        </div>
    );
}

function Row({
    label,
    value,
    prefix = '',
    muted = false,
}: {
    label: string;
    value: number;
    prefix?: string;
    muted?: boolean;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className={muted ? 'text-ink-500' : 'text-ink-700'}>{label}</dt>
            <dd className={muted ? 'num text-ink-500' : 'num text-ink-900'}>
                {prefix}
                {money(value)}
            </dd>
        </div>
    );
}

/**
 * The promo field, with its answer inline.
 *
 * Every rejection names its own reason — the code does not exist, it expired
 * last Tuesday, it needs a larger order. "Invalid code" covers six situations,
 * four of which the buyer could fix in ten seconds if anybody told them which
 * one they were in.
 */
function PromoField({ promo }: { promo: CartPromo | null }) {
    const form = useForm({ code: '' });

    if (promo !== null) {
        return (
            <div className="flex flex-col gap-1">
                <div className="flex items-center gap-2 rounded-card border border-teal bg-teal-subtle px-3 py-2">
                    <span className="min-w-0 flex-1 text-sm">
                        <span className="font-medium text-success">{promo.code}</span>
                        {promo.description && <span className="ml-2 text-ink-500">{promo.description}</span>}
                    </span>

                    <button
                        type="button"
                        aria-label={`Remove ${promo.code}`}
                        onClick={() =>
                            router.delete('/cart/promo', { preserveScroll: true, preserveState: false })
                        }
                        className="shrink-0 rounded-button p-1 text-ink-500 hover:bg-card hover:text-ink-700"
                    >
                        <XIcon size={14} />
                    </button>
                </div>

                {/* A code can stop being redeemable while it sits on a cart. The
                    total already reflects that; this says why it moved. */}
                {promo.expired && (
                    <p className="text-sm text-warning">
                        This code is no longer redeemable, so it is not being applied.
                    </p>
                )}

                {promo.belowMinimum && (
                    <p className="num text-sm text-warning">
                        Needs an order of {money(promo.minimumSpendCents)} to apply.
                    </p>
                )}
            </div>
        );
    }

    return (
        <form
            className="flex items-end gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                // preserveState must stay true: the rejection comes back as
                // a validation error on this form, and remounting the
                // component throws it away before anybody reads it. Inertia
                // replaces the page props either way, so the totals still
                // refresh.
                form.post('/cart/promo', {
                    preserveScroll: true,
                    onSuccess: () => form.reset('code'),
                });
            }}
        >
            <span className="min-w-0 flex-1">
                <Input
                    label="Promo code"
                    value={form.data.code}
                    error={form.errors.code}
                    onChange={(event) => form.setData('code', event.target.value)}
                    placeholder="SPRING20"
                    autoComplete="off"
                />
            </span>

            <Button type="submit" variant="secondary" loading={form.processing} disabled={form.data.code === ''}>
                Apply
            </Button>
        </form>
    );
}
