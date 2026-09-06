import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Button, CheckIcon, CopyIcon, Input, RadioGroup, WarningIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { money } from '@shared/lib/format';
import type { AdvertiserSharedProps } from '@shared/types';
import type { PendingTransfer, SavedCard, TopUpConfig, TopUpMethodValue } from '@shared/types/balance';

interface Props {
    config: TopUpConfig;
    cards: SavedCard[];
    availableCents: number;
    pendingTransfers: PendingTransfer[];
    /** Prefilled from ?amount=, so the cart can hand over a shortfall. */
    initialAmount: string;
    /** The reference of a transfer just started — its details stay on screen. */
    highlightTransfer: string | null;
}

export function TopUpTab({
    config,
    cards,
    availableCents,
    pendingTransfers,
    initialAmount,
    highlightTransfer,
}: Props) {
    const usableCards = cards.filter((card) => !card.expired);

    const form = useForm({
        amount: initialAmount,
        method: (usableCards.length > 0 ? 'saved_card' : 'new_card') as TopUpMethodValue,
        payment_method_id: usableCards.find((card) => card.isDefault)?.id
            ? String(usableCards.find((card) => card.isDefault)!.id)
            : usableCards[0]
              ? String(usableCards[0].id)
              : '',
        token: '',
    });

    const cents = toCents(form.data.amount);
    const belowMinimum = cents > 0 && cents < config.minimumCents;

    // The bonus shown here is the client's own arithmetic over the same tiers
    // the server holds; the server recomputes it before it credits anything.
    // The summary is a preview, never the source.
    const bonus = useMemo(() => bonusFor(cents, config.tiers), [cents, config.tiers]);
    const nextTier = useMemo(() => nextTierFor(cents, config.tiers), [cents, config.tiers]);

    const transfer = pendingTransfers.find((row) => row.reference === highlightTransfer)
        ?? (form.data.method === 'bank_transfer' ? null : null);

    return (
        <div className="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div className="flex min-w-0 flex-col gap-5">
                <AmountCard form={form} config={config} cents={cents} belowMinimum={belowMinimum} nextTier={nextTier} />

                <MethodCard form={form} cards={cards} config={config} />

                {form.data.method === 'bank_transfer' && (
                    <BankDetails config={config} transfer={transfer} cents={cents} />
                )}
            </div>

            <Summary
                form={form}
                cents={cents}
                bonusCents={bonus}
                availableCents={availableCents}
                belowMinimum={belowMinimum}
                minimumCents={config.minimumCents}
            />
        </div>
    );
}

// ------------------------------------------------------------------- amount

function AmountCard({
    form,
    config,
    cents,
    belowMinimum,
    nextTier,
}: {
    form: ReturnType<typeof useForm<{ amount: string; method: TopUpMethodValue; payment_method_id: string; token: string }>>;
    config: TopUpConfig;
    cents: number;
    belowMinimum: boolean;
    nextTier: { addCents: number; bonusCents: number } | null;
}) {
    return (
        <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <h2 className="font-sora text-md font-semibold text-ink-900">How much?</h2>

            <div className="mt-3 flex flex-wrap gap-2">
                {config.quickAmountsCents.map((amount) => {
                    const selected = cents === amount;

                    return (
                        <button
                            key={amount}
                            type="button"
                            aria-pressed={selected}
                            onClick={() => form.setData('amount', String(amount / 100))}
                            className={cn(
                                'num rounded-pill border px-4 py-2 text-base transition-colors duration-fast',
                                selected
                                    ? 'border-brand bg-brand-subtle font-medium text-brand'
                                    : 'border-subtle text-ink-700 hover:border-strong hover:bg-sunken',
                            )}
                        >
                            {/* Whole dollars on a chip. The cents are noise on
                                a round number nobody typed. */}
                            {money(amount).replace('.00', '')}
                        </button>
                    );
                })}
            </div>

            <div className="mt-4 max-w-[220px]">
                <Input
                    label="Or another amount"
                    type="number"
                    min={config.minimumCents / 100}
                    step="0.01"
                    value={form.data.amount}
                    error={form.errors.amount ?? (belowMinimum ? `The smallest top-up is ${money(config.minimumCents)}.` : undefined)}
                    onChange={(event) => form.setData('amount', event.target.value)}
                />
            </div>

            {/* The nudge, only when it is a nudge. Nobody adding $100 wants to
                hear about $10,000. */}
            {nextTier && (
                <p className="mt-3 rounded-card bg-teal-subtle px-4 py-3 text-base text-ink-700">
                    Add{' '}
                    <button
                        type="button"
                        onClick={() => form.setData('amount', String((cents + nextTier.addCents) / 100))}
                        className="num font-medium text-teal underline underline-offset-2"
                    >
                        {money(cents + nextTier.addCents)}
                    </button>{' '}
                    instead and get <span className="num font-medium">{money(nextTier.bonusCents)}</span> credit.
                </p>
            )}
        </section>
    );
}

// ------------------------------------------------------------------- method

function MethodCard({
    form,
    cards,
    config,
}: {
    form: ReturnType<typeof useForm<{ amount: string; method: TopUpMethodValue; payment_method_id: string; token: string }>>;
    cards: SavedCard[];
    config: TopUpConfig;
}) {
    const usable = cards.filter((card) => !card.expired);

    const options: { value: TopUpMethodValue; label: string; hint?: string; disabled?: boolean }[] = [
        {
            value: 'saved_card',
            label: usable.length > 0 ? 'A saved card' : 'A saved card (none yet)',
            disabled: usable.length === 0,
        },
        { value: 'new_card', label: 'A new card' },
        { value: 'paypal', label: 'PayPal' },
        { value: 'bank_transfer', label: 'Bank transfer', hint: 'Best for large amounts — no card limits, no card fee.' },
    ];

    return (
        <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <h2 className="font-sora text-md font-semibold text-ink-900">How would you like to pay?</h2>

            <div className="mt-3">
                <RadioGroup
                    legend="Payment method"
                    name="method"
                    value={form.data.method}
                    onChange={(value: string) => form.setData('method', value as TopUpMethodValue)}
                    options={options.map((option) => ({
                        value: option.value,
                        label: option.label,
                        hint: option.hint,
                        disabled: option.disabled,
                    }))}
                />
            </div>

            {form.data.method === 'saved_card' && usable.length > 0 && (
                <ul className="mt-4 flex flex-col gap-2">
                    {usable.map((card) => (
                        <li key={card.id}>
                            <label
                                className={cn(
                                    'flex cursor-pointer items-center gap-3 rounded-card border px-4 py-3 transition-colors duration-fast',
                                    form.data.payment_method_id === String(card.id)
                                        ? 'border-brand bg-brand-subtle'
                                        : 'border-subtle hover:bg-sunken',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="card"
                                    className="size-4 accent-[var(--brand-blue)]"
                                    checked={form.data.payment_method_id === String(card.id)}
                                    onChange={() => form.setData('payment_method_id', String(card.id))}
                                />

                                <span className="flex-1 text-base text-ink-900">
                                    {card.brand ?? 'Card'} ending {card.lastFour ?? '••••'}
                                </span>

                                <span className="num text-sm text-ink-500">
                                    {card.expMonth}/{String(card.expYear ?? '').slice(-2)}
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
            )}

            {cards.some((card) => card.expired) && (
                <p className="mt-3 flex items-center gap-1.5 text-sm text-ink-500">
                    <WarningIcon size={13} className="shrink-0" />
                    Expired cards are hidden. Add the replacement and it will appear here.
                </p>
            )}

            {form.data.method === 'new_card' && <StripeElements config={config} />}

            {form.data.method === 'paypal' && (
                <p className="mt-4 rounded-card bg-sunken px-4 py-3 text-base text-ink-700">
                    You will be sent to PayPal to approve the payment, and back here when it is done.
                </p>
            )}
        </section>
    );
}

/**
 * Where Stripe Elements mounts.
 *
 * The card fields belong to Stripe's iframe, not to us — that is what keeps
 * this application out of PCI scope, and it is why there is no card number
 * input anywhere in this codebase. Without a publishable key there is nothing
 * to mount, and the panel says so rather than rendering a dead box.
 */
function StripeElements({ config }: { config: TopUpConfig }) {
    if (config.stripeKey === null) {
        return (
            <Alert tone="info" title="Card payments are not configured here" className="mt-4">
                This environment has no Stripe key, so the card form cannot load. Bank transfer works, and a
                saved card works if the account has one.
            </Alert>
        );
    }

    return (
        <div className="mt-4">
            <div
                id="stripe-card-element"
                className="rounded-card border border-subtle bg-canvas px-4 py-3 text-base text-ink-500"
            >
                Card details are entered in Stripe’s own secure field.
            </div>

            <p className="mt-2 text-sm text-ink-500">
                Your card number never reaches Publinza — it goes straight to Stripe, and we store only the last
                four digits.
            </p>
        </div>
    );
}

// -------------------------------------------------------------- bank details

function BankDetails({
    config,
    transfer,
    cents,
}: {
    config: TopUpConfig;
    transfer: PendingTransfer | null;
    cents: number;
}) {
    const rows: [string, string][] = [
        ['Beneficiary', config.bank.beneficiary],
        ['IBAN', config.bank.iban],
        ['BIC / SWIFT', config.bank.bic],
        ['Bank', config.bank.bank_name],
    ];

    return (
        <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <h2 className="font-sora text-md font-semibold text-ink-900">Where to send it</h2>

            <dl className="mt-3 flex flex-col gap-2">
                {rows.map(([term, value]) => (
                    <div key={term} className="flex items-baseline justify-between gap-4">
                        <dt className="text-sm text-ink-500">{term}</dt>
                        <dd className="num flex items-center gap-2 text-base text-ink-900">
                            {value}
                            <Copy value={value} label={term} />
                        </dd>
                    </div>
                ))}
            </dl>

            {transfer !== null ? (
                <div className="mt-4 rounded-card border border-brand bg-brand-subtle px-4 py-3">
                    <p className="text-sm text-ink-500">Your payment reference</p>
                    <p className="num mt-0.5 flex items-center gap-2 font-sora text-lg font-semibold text-ink-900">
                        {transfer.reference}
                        <Copy value={transfer.reference} label="reference" />
                    </p>
                    <p className="mt-2 text-base text-ink-700">
                        Quote this exactly. It is how we match your transfer to your account — a payment without
                        it has to be traced by hand, which takes days rather than hours.
                    </p>
                </div>
            ) : (
                <p className="mt-4 rounded-card bg-sunken px-4 py-3 text-base text-ink-700">
                    You will get a unique payment reference when you confirm{cents > 0 ? ` ${money(cents)}` : ''}.
                    Quote it on the transfer so we can match it to your account.
                </p>
            )}

            <p className="mt-3 text-base text-ink-500">
                Transfers are confirmed by hand once the money lands, usually within one business day. Your
                balance goes up when we confirm it, not when you send it — so if you need funds today, a card or
                PayPal is instant.
            </p>
        </section>
    );
}

function Copy({ value, label }: { value: string; label: string }) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) return;

        const timer = window.setTimeout(() => setCopied(false), 1600);

        return () => window.clearTimeout(timer);
    }, [copied]);

    return (
        <button
            type="button"
            aria-label={`Copy ${label}`}
            onClick={() => {
                void navigator.clipboard?.writeText(value).then(() => setCopied(true)).catch(() => undefined);
            }}
            className="flex size-6 items-center justify-center rounded-button text-ink-500 hover:bg-sunken hover:text-ink-700"
        >
            {copied ? <CheckIcon size={13} className="text-teal" /> : <CopyIcon size={13} />}
        </button>
    );
}

// ------------------------------------------------------------------ summary

function Summary({
    form,
    cents,
    bonusCents,
    availableCents,
    belowMinimum,
    minimumCents,
}: {
    form: ReturnType<typeof useForm<{ amount: string; method: TopUpMethodValue; payment_method_id: string; token: string }>>;
    cents: number;
    bonusCents: number;
    availableCents: number;
    belowMinimum: boolean;
    minimumCents: number;
}) {
    const page = usePage<AdvertiserSharedProps>();
    const decline = page.props.errors?.payment;
    const advice = page.props.errors?.payment_advice;

    const transfer = form.data.method === 'bank_transfer';
    const resulting = availableCents + cents + bonusCents;

    return (
        <aside className="flex flex-col gap-4 lg:sticky lg:top-[calc(theme(spacing.header)+1.25rem)] lg:self-start">
            <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
                <h2 className="font-sora text-md font-semibold text-ink-900">Summary</h2>

                <dl className="mt-3 flex flex-col gap-2">
                    <Row term="Amount" value={money(cents)} />

                    {/* Zero fee, stated. A blank where a fee line should be is
                        the thing people go looking for on the statement. */}
                    <Row term="Card fee" value="None" muted />

                    {bonusCents > 0 && <Row term="Volume bonus" value={`+ ${money(bonusCents)}`} tone="teal" />}

                    <div className="mt-1 flex items-baseline justify-between gap-4 border-t border-subtle pt-3">
                        <dt className="text-base font-medium text-ink-900">
                            {transfer ? 'Balance once confirmed' : 'Balance after'}
                        </dt>
                        <dd className="num font-sora text-lg font-semibold text-ink-900">{money(resulting)}</dd>
                    </div>
                </dl>

                {decline !== undefined && (
                    <Alert tone="danger" title={decline} className="mt-4">
                        {advice}
                    </Alert>
                )}

                <Button
                    className="mt-4 w-full"
                    loading={form.processing}
                    disabled={cents < minimumCents}
                    onClick={() =>
                        form.post('/balance/top-up', {
                            preserveScroll: true,
                            // preserveState, or a declined payment remounts the
                            // form and takes the error with it.
                            preserveState: true,
                        })
                    }
                >
                    {transfer
                        ? `Get transfer details for ${money(cents)}`
                        : cents > 0
                          ? `Add ${money(cents)} to balance`
                          : 'Add to balance'}
                </Button>

                {belowMinimum && (
                    <p className="mt-2 text-center text-sm text-ink-500">
                        The smallest top-up is {money(minimumCents)}.
                    </p>
                )}
            </section>

            <p className="text-sm text-ink-500">
                Your balance is money you hold with us. It is only spent when a placement goes live and its link
                is verified — until then it is yours, and refundable.
            </p>
        </aside>
    );
}

function Row({
    term,
    value,
    muted = false,
    tone,
}: {
    term: string;
    value: string;
    muted?: boolean;
    tone?: 'teal';
}) {
    return (
        <div className="flex items-baseline justify-between gap-4">
            <dt className="text-base text-ink-500">{term}</dt>
            <dd className={cn('num text-base', tone === 'teal' ? 'text-teal' : muted ? 'text-ink-500' : 'text-ink-900')}>
                {value}
            </dd>
        </div>
    );
}

// ----------------------------------------------------------------- arithmetic

function toCents(amount: string): number {
    const parsed = Number(amount);

    return Number.isFinite(parsed) && parsed > 0 ? Math.round(parsed * 100) : 0;
}

/** Mirrors VolumeBonus. The server recomputes before it credits anything. */
function bonusFor(cents: number, tiers: { atCents: number; percent: number }[]): number {
    const match = [...tiers].sort((a, b) => b.atCents - a.atCents).find((tier) => cents >= tier.atCents);

    return match === undefined ? 0 : Math.floor((cents * match.percent) / 100);
}

function nextTierFor(
    cents: number,
    tiers: { atCents: number; percent: number }[],
): { addCents: number; bonusCents: number } | null {
    if (cents <= 0) return null;

    const next = [...tiers].sort((a, b) => a.atCents - b.atCents).find((tier) => cents < tier.atCents);

    if (next === undefined) return null;

    const gap = next.atCents - cents;

    // Within reach means within double what they were already adding, or
    // within $500 — whichever is more generous. Same rule as the server's.
    if (gap > Math.max(cents, 50_000)) return null;

    return { addCents: gap, bonusCents: Math.floor((next.atCents * next.percent) / 100) };
}
