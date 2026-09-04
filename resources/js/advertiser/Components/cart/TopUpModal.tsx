import { useForm } from '@inertiajs/react';
import { Button, Input, Modal } from '@shared/ui';
import { money } from '@shared/lib/format';

interface Props {
    open: boolean;
    onClose: () => void;
    /** Prefills the field with exactly what is missing. */
    suggestedCents: number;
}

const PRESETS = [10_000, 25_000, 50_000, 100_000];

/**
 * Topping up without leaving the cart.
 *
 * The amount is prefilled with the shortfall, rounded up to whole dollars,
 * because that is the number the buyer came here for. Sending them to the
 * billing page to work it out themselves is how a cart becomes an abandoned
 * cart.
 */
export function TopUpModal({ open, onClose, suggestedCents }: Props) {
    const suggested = Math.ceil(suggestedCents / 100);
    const form = useForm({ amount: suggested > 0 ? String(suggested) : '', reference: 'cart' });

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="sm"
            title="Top up your balance"
            description="Added to your available balance straight away. Nothing is charged to a placement until you check out."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        loading={form.processing}
                        disabled={form.data.amount === ''}
                        onClick={() =>
                            form.post('/billing/top-up', {
                                preserveScroll: true,
                                preserveState: false,
                                onSuccess: onClose,
                            })
                        }
                    >
                        Add funds
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-3">
                <Input
                    label="Amount (USD)"
                    type="number"
                    min={1}
                    step="0.01"
                    value={form.data.amount}
                    error={form.errors.amount}
                    onChange={(event) => form.setData('amount', event.target.value)}
                    hint={
                        suggestedCents > 0
                            ? `${money(suggestedCents)} covers what this order is short.`
                            : undefined
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {PRESETS.map((preset) => (
                        <button
                            key={preset}
                            type="button"
                            onClick={() => form.setData('amount', String(preset / 100))}
                            className="num rounded-pill border border-subtle px-3 py-1 text-sm text-ink-700 hover:border-strong hover:bg-sunken"
                        >
                            {money(preset)}
                        </button>
                    ))}
                </div>
            </div>
        </Modal>
    );
}
