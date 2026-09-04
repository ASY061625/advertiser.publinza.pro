import { Input, Select, Textarea, WalletIcon } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { BillingDetails, CartWallet } from '@shared/types/cart';

interface Props {
    billing: BillingDetails;
    onChange: (field: keyof BillingDetails, value: string) => void;
    errors: Record<string, string>;
    wallet: CartWallet;
    totalCents: number;
    terms: boolean;
    onTerms: (accepted: boolean) => void;
}

/**
 * Billing, payment and the thing everybody asks support about.
 *
 * The "what happens next" block is not filler. A marketplace that takes several
 * hundred dollars and says nothing about when it leaves the account generates a
 * support ticket per order, and the answer — frozen, not spent, released per
 * verified link — is the single most reassuring fact about how Publinza works.
 * It belongs directly above the button, not in a help centre.
 */
export function ConfirmStep({ billing, onChange, errors, wallet, totalCents, terms, onTerms }: Props) {
    const short = Math.max(0, totalCents - wallet.availableCents);

    return (
        <div className="flex flex-col gap-6">
            <section className="rounded-card border border-subtle bg-card p-5">
                <h2 className="mb-1 font-sora text-md font-semibold text-ink-900">Billing details</h2>
                <p className="mb-4 text-sm text-ink-500">
                    Prefilled from your profile. Changing them here changes this invoice only — the invoice
                    keeps what it was issued with.
                </p>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Input
                        label="Name"
                        required
                        value={billing.name ?? ''}
                        error={errors['billing.name']}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                    <Input
                        label="Company"
                        value={billing.company ?? ''}
                        error={errors['billing.company']}
                        onChange={(event) => onChange('company', event.target.value)}
                    />
                    <Input
                        label="Email for the invoice"
                        type="email"
                        required
                        value={billing.email ?? ''}
                        error={errors['billing.email']}
                        onChange={(event) => onChange('email', event.target.value)}
                    />
                    <Input
                        label="VAT number"
                        value={billing.vat_no ?? ''}
                        error={errors['billing.vat_no']}
                        onChange={(event) => onChange('vat_no', event.target.value)}
                    />
                    <Input
                        label="Country"
                        value={billing.country ?? ''}
                        error={errors['billing.country']}
                        onChange={(event) => onChange('country', event.target.value)}
                    />
                    <span className="sm:col-span-2">
                        <Textarea
                            label="Address"
                            rows={2}
                            value={billing.address ?? ''}
                            error={errors['billing.address']}
                            onChange={(event) => onChange('address', event.target.value)}
                        />
                    </span>
                </div>
            </section>

            <section className="rounded-card border border-subtle bg-card p-5">
                <h2 className="mb-4 font-sora text-md font-semibold text-ink-900">Payment</h2>

                <div className="flex items-center gap-3 rounded-card border border-brand bg-brand-subtle px-4 py-3">
                    <WalletIcon size={18} className="text-brand" />
                    <span className="min-w-0 flex-1">
                        <span className="block font-medium text-ink-900">Publinza balance</span>
                        <span className="num block text-sm text-ink-500">
                            {money(wallet.availableCents)} available
                        </span>
                    </span>
                    <span className="num font-sora text-md font-semibold text-ink-900">
                        {money(Math.min(totalCents, wallet.availableCents))}
                    </span>
                </div>

                {short > 0 && (
                    <div className="mt-3 flex flex-col gap-2">
                        <p className="num rounded-card bg-warning-bg px-3 py-2 text-sm font-medium text-warning">
                            {money(short)} of this order is not covered by your balance.
                        </p>

                        {/* Card and PayPal cover the shortfall rather than the
                            whole order: the balance is already committed to it,
                            and charging a card for the full amount would leave
                            the wallet holding money nobody asked it to hold. */}
                        <Select
                            label="Pay the rest with"
                            options={[
                                { value: 'card', label: 'Card ending 4242' },
                                { value: 'paypal', label: 'PayPal' },
                            ]}
                            hint="Charged when you place the order."
                        />
                    </div>
                )}
            </section>

            <section className="rounded-card border border-subtle bg-sunken p-5">
                <h2 className="mb-2 font-sora text-md font-semibold text-ink-900">What happens next</h2>

                <ol className="num flex list-inside list-decimal flex-col gap-2 text-base text-ink-700 marker:font-semibold marker:text-ink-500">
                    <li>
                        <span className="num font-semibold text-ink-900">{money(totalCents)}</span> is frozen in
                        your balance. It is <span className="font-medium">not spent</span> — it is held against
                        this order and it is still yours.
                    </li>
                    <li>Each publisher writes or receives the article and publishes it.</li>
                    <li>
                        We verify the link is live. Only then is that placement’s share released to the
                        publisher.
                    </li>
                    <li>
                        A placement that falls through is refunded to your balance in full — you are never out
                        of pocket for a link that did not run.
                    </li>
                </ol>
            </section>

            <label className="flex cursor-pointer items-start gap-3 text-base text-ink-700">
                <input
                    type="checkbox"
                    checked={terms}
                    onChange={(event) => onTerms(event.target.checked)}
                    className="mt-1 size-4 shrink-0 accent-[color:var(--brand-blue)]"
                />
                <span>
                    I agree to the{' '}
                    <a href="/terms" target="_blank" rel="noopener noreferrer" className="text-brand underline">
                        terms of service
                    </a>{' '}
                    and understand that funds are frozen until each link is verified.
                    {errors.terms && <span className="mt-1 block text-sm text-danger">{errors.terms}</span>}
                </span>
            </label>
        </div>
    );
}
