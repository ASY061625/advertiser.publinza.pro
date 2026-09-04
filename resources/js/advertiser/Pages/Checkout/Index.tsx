import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppShell } from '../../Layouts/AppShell';
import { Button, ChevronLeftIcon, ChevronRightIcon } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { BillingDetails, CartPayload, CartWallet, CheckoutContent } from '@shared/types/cart';
import { ConfirmStep } from '../../Components/checkout/ConfirmStep';
import { ContentStep } from '../../Components/checkout/ContentStep';
import { ReviewStep } from '../../Components/checkout/ReviewStep';
import { StepIndicator } from '../../Components/checkout/StepIndicator';

interface Props {
    step: string;
    steps: string[];
    cart: CartPayload;
    content: CheckoutContent;
    wallet: CartWallet;
    billing: BillingDetails;
}

/**
 * Checkout, in three steps over one URL.
 *
 * The step is a query parameter rather than component state, so a refresh, the
 * back button and a link pasted between two of the buyer's own devices all land
 * where they were. Nothing is written outside the cart until the last step —
 * the articles staged on step two live on the cart line, so abandoning here
 * leaves a cart with the work already in it rather than losing it.
 */
export default function CheckoutIndex({ step, steps, cart, content, wallet, billing }: Props) {
    const [details, setDetails] = useState<BillingDetails>(billing);
    const [terms, setTerms] = useState(false);

    const [errors, setErrors] = useState<Record<string, string>>({});
    const [placing, setPlacing] = useState(false);
    const index = steps.indexOf(step);
    const outstanding = content.needed - content.ready;

    function go(target: string) {
        router.visit(`/checkout?step=${target}`);
    }

    function place() {
        setPlacing(true);

        router.post(
            '/checkout',
            { billing: { ...details }, terms },
            {
                preserveScroll: true,
                onError: setErrors,
                onFinish: () => setPlacing(false),
            },
        );
    }

    return (
        <AppShell
            title="Checkout"
            crumbs={[{ label: 'Cart', href: '/cart' }, { label: 'Checkout' }]}
        >
            <Head title="Checkout" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <StepIndicator steps={steps} current={step} furthest={steps.length - 1} />

                {step === 'review' && <ReviewStep cart={cart} />}
                {step === 'content' && <ContentStep content={content} />}
                {step === 'confirm' && (
                    <ConfirmStep
                        billing={details}
                        onChange={(field, value) => setDetails((current) => ({ ...current, [field]: value }))}
                        errors={errors}
                        wallet={wallet}
                        totalCents={cart.totals.totalCents}
                        terms={terms}
                        onTerms={setTerms}
                    />
                )}

                <footer className="sticky bottom-0 -mx-6 flex flex-wrap items-center gap-3 border-t border-subtle bg-card px-6 py-3 shadow-card">
                    {index === 0 ? (
                        <Link
                            href="/cart"
                            className="flex items-center gap-1 text-sm text-ink-500 hover:text-ink-700"
                        >
                            <ChevronLeftIcon size={14} />
                            Back to the cart
                        </Link>
                    ) : (
                        <button
                            type="button"
                            onClick={() => go(steps[index - 1]!)}
                            className="flex items-center gap-1 text-sm text-ink-500 hover:text-ink-700"
                        >
                            <ChevronLeftIcon size={14} />
                            Back
                        </button>
                    )}

                    <span className="num ml-auto flex items-baseline gap-2">
                        <span className="text-sm text-ink-500">Total</span>
                        <span className="font-sora text-md font-semibold text-ink-900">
                            {money(cart.totals.totalCents)}
                        </span>
                    </span>

                    {step === 'confirm' ? (
                        <Button size="lg" onClick={place} loading={placing} disabled={!terms}>
                            Place order
                        </Button>
                    ) : (
                        // One button forward, always enabled. On the content
                        // step with work outstanding it says what continuing
                        // actually does — a disabled Continue beside a "do this
                        // later" escape hatch is two controls for one decision.
                        <Button size="lg" onClick={() => go(steps[index + 1]!)}>
                            {step === 'content' && outstanding > 0 ? 'Do this later' : 'Continue'}
                            <ChevronRightIcon size={14} />
                        </Button>
                    )}
                </footer>

                {step === 'content' && outstanding > 0 && (
                    <p className="text-sm text-ink-500">
                        <span className="num font-medium text-ink-900">{outstanding}</span>{' '}
                        {outstanding === 1 ? 'placement is' : 'placements are'} still waiting on an article.
                        Choosing <span className="font-medium">Do this later</span> places the order and leaves{' '}
                        {outstanding === 1 ? 'it' : 'them'} as {outstanding === 1 ? 'a draft' : 'drafts'} until
                        you submit the copy.
                    </p>
                )}
            </div>
        </AppShell>
    );
}
