import { useForm } from '@inertiajs/react';
import { Alert, Button, NumberInput, Select, Switch } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { AutoTopUpState, SavedCard } from '@shared/types/balance';

interface Props {
    state: AutoTopUpState;
    cards: SavedCard[];
    minimumCents: number;
}

/**
 * "Keep my balance topped up."
 *
 * The copy has one job: say exactly when a charge happens, in the same sentence
 * as the two numbers that decide it. A rule that silently takes money from a
 * card is the single most complained-about feature in any product that has one,
 * and every one of those complaints is really "nobody told me when".
 */
export function AutoTopUpCard({ state, cards, minimumCents }: Props) {
    const usable = cards.filter((card) => !card.expired);

    const form = useForm({
        enabled: state.enabled,
        threshold: state.thresholdCents === null ? '' : String(state.thresholdCents / 100),
        amount: state.amountCents === null ? '' : String(state.amountCents / 100),
        payment_method_id: state.paymentMethodId === null ? '' : String(state.paymentMethodId),
    });

    const threshold = Number(form.data.threshold);
    const amount = Number(form.data.amount);
    const complete = form.data.threshold !== '' && form.data.amount !== '' && form.data.payment_method_id !== '';

    return (
        <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <h2 className="font-sora text-md font-semibold text-ink-900">Auto top-up</h2>
                    <p className="mt-0.5 max-w-prose text-sm text-ink-500">
                        Adds funds automatically so an order never fails for want of balance.
                    </p>
                </div>

                {/* Named for what the control does, not for one of its two
                    states: "On" beside an off switch reads as a claim. */}
                <Switch
                    label="Turn on auto top-up"
                    checked={form.data.enabled}
                    onCheckedChange={(next) => form.setData('enabled', next)}
                />
            </div>

            {usable.length === 0 && (
                <Alert
                    tone="info"
                    title="No saved card yet"
                    className="mt-4"
                >
                    Auto top-up charges a saved card. Add one on the Top up tab and it becomes selectable here.
                </Alert>
            )}

            {state.enabled && !state.armed && (
                <Alert
                    tone="warning"
                    title="This rule cannot run"
                    className="mt-4"
                >
                    It is switched on, but the card it charges has been removed. Choose another card, or switch
                    it off.
                </Alert>
            )}

            <div className="mt-4 grid gap-4 md:grid-cols-3">
                <NumberInput
                    label="When my balance falls below"
                    unit="$"
                    min={0}
                    step={50}
                    value={form.data.threshold === '' ? '' : threshold}
                    error={form.errors.threshold}
                    onValueChange={(value) => form.setData('threshold', value === '' ? '' : String(value))}
                />

                <NumberInput
                    label="Add this much"
                    unit="$"
                    min={minimumCents / 100}
                    step={50}
                    hint={`At least ${money(minimumCents)}.`}
                    value={form.data.amount === '' ? '' : amount}
                    error={form.errors.amount}
                    onValueChange={(value) => form.setData('amount', value === '' ? '' : String(value))}
                />

                <Select
                    label="Charge this card"
                    value={form.data.payment_method_id}
                    error={form.errors.payment_method_id}
                    disabled={usable.length === 0}
                    onChange={(event) => form.setData('payment_method_id', event.target.value)}
                    options={[
                        { value: '', label: usable.length === 0 ? 'No saved card' : 'Choose a card…' },
                        ...usable.map((card) => ({
                            value: String(card.id),
                            label: `${card.brand ?? 'Card'} ending ${card.lastFour ?? '••••'}`,
                        })),
                    ]}
                />
            </div>

            {/* The whole rule as one sentence, in the numbers they just typed.
                Nobody reads three fields and assembles this themselves. */}
            <p className="mt-4 rounded-card bg-sunken px-4 py-3 text-base text-ink-700">
                {form.data.enabled && complete ? (
                    <>
                        We will charge your card{' '}
                        <span className="num font-medium text-ink-900">{money(amount * 100)}</span> the moment your
                        available balance drops below{' '}
                        <span className="num font-medium text-ink-900">{money(threshold * 100)}</span> — which
                        usually happens as an order is placed. At most one charge a day, and we email a receipt
                        every time.
                    </>
                ) : (
                    'While this is off, nothing is ever charged automatically. You top up when you choose to.'
                )}
            </p>

            <div className="mt-4 flex justify-end">
                <Button
                    loading={form.processing}
                    onClick={() => form.patch('/balance/auto-top-up', { preserveScroll: true })}
                >
                    Save
                </Button>
            </div>
        </section>
    );
}
