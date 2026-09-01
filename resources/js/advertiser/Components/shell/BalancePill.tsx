import { useState, type FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Button, Modal, NumberInput, PlusIcon, Tooltip, WalletIcon } from '@shared/ui';
import { money } from '@shared/lib/format';

interface BalancePillProps {
    availableCents: number;
    frozenCents: number;
}

export function BalancePill({ availableCents, frozenCents }: BalancePillProps) {
    const [topUpOpen, setTopUpOpen] = useState(false);
    const form = useForm({ amount: 100 as number | '', reference: 'manual-top-up' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/billing/top-up', { onSuccess: () => setTopUpOpen(false) });
    }

    return (
        <>
            <Tooltip
                content={`${money(availableCents)} available · ${money(frozenCents)} frozen against open orders`}
                side="bottom"
            >
                <span className="flex h-9 items-center gap-2 rounded-pill bg-gold-subtle pl-3 pr-1 text-[#B45309]">
                    <WalletIcon size={15} className="shrink-0" />
                    <span className="num text-base font-medium">{money(availableCents)}</span>

                    <button
                        type="button"
                        onClick={() => setTopUpOpen(true)}
                        aria-label="Top up balance"
                        className="hover:bg-gold/20 flex size-7 items-center justify-center rounded-pill transition-colors duration-fast"
                    >
                        <PlusIcon size={15} />
                    </button>
                </span>
            </Tooltip>

            <Modal
                open={topUpOpen}
                onClose={() => setTopUpOpen(false)}
                title="Top up balance"
                description="Funds sit in your available balance until you place an order."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setTopUpOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submit} loading={form.processing}>
                            Top up balance
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit}>
                    <NumberInput
                        label="Amount"
                        unit="$"
                        min={10}
                        max={100000}
                        step={10}
                        hint="The smallest top-up is $10."
                        value={form.data.amount}
                        error={form.errors.amount}
                        onValueChange={(value) => form.setData('amount', value)}
                    />

                    <p className="mt-4 text-sm text-ink-500">
                        Currently {money(availableCents)} available and {money(frozenCents)} frozen against open orders.
                    </p>
                </form>
            </Modal>
        </>
    );
}
