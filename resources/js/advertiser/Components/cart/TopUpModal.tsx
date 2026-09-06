import { Link } from '@inertiajs/react';
import { useState } from 'react';
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
 * Choosing how much to add, without leaving the cart.
 *
 * The amount is prefilled with the shortfall, rounded up to whole dollars,
 * because that is the number the buyer came here for. Working it out on the
 * balance page is how a cart becomes an abandoned cart.
 *
 * It picks the figure and hands off. It used to post the top-up itself, which
 * meant money moved from a two-field modal that never asked how it was being
 * paid — the amount is a decision that can be made here, but the payment method
 * and the summary that shows the fee and the bonus cannot.
 */
export function TopUpModal({ open, onClose, suggestedCents }: Props) {
    const suggested = Math.ceil(suggestedCents / 100);
    const [amount, setAmount] = useState(suggested > 0 ? String(suggested) : '');

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="sm"
            title="Top up your balance"
            description="Pick an amount and we'll take you to payment. Nothing is charged to a placement until you check out."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Link
                        href={`/balance?tab=top-up${amount === '' ? '' : `&amount=${amount}`}`}
                        onClick={onClose}
                    >
                        <Button disabled={amount === ''}>Continue to payment</Button>
                    </Link>
                </>
            }
        >
            <div className="flex flex-col gap-3">
                <Input
                    label="Amount (USD)"
                    type="number"
                    min={1}
                    step="0.01"
                    value={amount}
                    onChange={(event) => setAmount(event.target.value)}
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
                            onClick={() => setAmount(String(preset / 100))}
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
