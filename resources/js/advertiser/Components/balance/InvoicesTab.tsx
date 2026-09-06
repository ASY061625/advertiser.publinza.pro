import { useForm } from '@inertiajs/react';
import { Button, DownloadIcon, Input, Textarea } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { date, money } from '@shared/lib/format';
import type { BillingDetails, InvoiceRow } from '@shared/types/balance';

interface Props {
    invoices: InvoiceRow[];
    billing: BillingDetails;
}

export function InvoicesTab({ invoices, billing }: Props) {
    return (
        <div className="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <section className="flex min-w-0 flex-col gap-3">
                <h2 className="font-sora text-md font-semibold text-ink-900">Invoices</h2>

                {invoices.length === 0 ? (
                    <p className="rounded-card border border-subtle bg-card py-12 text-center text-base text-ink-500">
                        No invoices yet. One is issued for every order you place.
                    </p>
                ) : (
                    <div className="relative overflow-x-auto rounded-card border border-subtle bg-card shadow-card">
                        <table className="w-full border-collapse text-left text-base" style={{ minWidth: '640px' }}>
                            <thead>
                                <tr>
                                    {['Number', 'Date', 'Period', 'Amount', 'Status', ''].map((heading, index) => (
                                        <th
                                            key={heading || index}
                                            scope="col"
                                            className={cn(
                                                'border-b border-subtle bg-sunken px-3 py-3 text-sm font-medium text-ink-500',
                                                index === 3 && 'text-right',
                                            )}
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody>
                                {invoices.map((invoice) => (
                                    <tr key={invoice.id} className="border-b border-subtle last:border-0 hover:bg-row-hover">
                                        <td className="num whitespace-nowrap px-3 py-3 font-medium text-ink-900">
                                            {invoice.number}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-sm text-ink-500">
                                            {invoice.issuedAt === null ? '—' : date(invoice.issuedAt)}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-sm text-ink-500">
                                            {period(invoice)}
                                        </td>
                                        <td className="num whitespace-nowrap px-3 py-3 text-right text-ink-900">
                                            {money(invoice.totalCents)}
                                        </td>
                                        <td className="px-3 py-3">
                                            <StatusPill status={invoice.status} />
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-right">
                                            <a
                                                href={`/balance/invoices/${invoice.id}`}
                                                className="inline-flex items-center gap-1.5 rounded-button border border-subtle px-2.5 py-1.5 text-sm text-ink-700 hover:bg-sunken"
                                            >
                                                <DownloadIcon size={13} />
                                                PDF
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <BillingCard billing={billing} />
        </div>
    );
}

/**
 * Paid, Pending, Refunded — and anything else the column happens to hold.
 *
 * Not the shared Badge: its vocabulary is the *post* lifecycle, and reusing
 * "posted" green for "paid" would tie two unrelated state machines together.
 */
function StatusPill({ status }: { status: string }) {
    const tone =
        status === 'paid'
            ? 'bg-teal-subtle text-teal'
            : status === 'refunded'
              ? 'bg-gold-subtle text-[#B45309]'
              : 'bg-sunken text-ink-700';

    return (
        <span className={cn('inline-flex items-center rounded-pill px-2.5 py-1 text-xs font-medium', tone)}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
}

function BillingCard({ billing }: { billing: BillingDetails }) {
    const form = useForm({
        company: billing.company ?? '',
        billing_address: billing.address ?? '',
        country: billing.country ?? '',
        vat_no: billing.vatNo ?? '',
        billing_email: billing.billingEmail ?? '',
    });

    return (
        <aside className="lg:sticky lg:top-[calc(theme(spacing.header)+1.25rem)] lg:self-start">
            <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
                <h2 className="font-sora text-md font-semibold text-ink-900">Billing details</h2>

                {/* Stated before the fields, not after the save. Somebody
                    correcting a VAT number is usually trying to fix an invoice
                    they have already been sent, and needs to know that this is
                    not how to do it. */}
                <p className="mt-1 text-sm text-ink-500">
                    These appear on invoices issued from now on. Invoices already issued keep the details they
                    were issued with — if one of those is wrong, message us and we will reissue it.
                </p>

                <div className="mt-4 flex flex-col gap-3">
                    <Input
                        label="Company name"
                        value={form.data.company}
                        error={form.errors.company}
                        onChange={(event) => form.setData('company', event.target.value)}
                    />

                    <Textarea
                        label="Address"
                        rows={3}
                        value={form.data.billing_address}
                        error={form.errors.billing_address}
                        onChange={(event) => form.setData('billing_address', event.target.value)}
                    />

                    <Input
                        label="Country code"
                        maxLength={2}
                        placeholder="US"
                        value={form.data.country}
                        error={form.errors.country}
                        onChange={(event) => form.setData('country', event.target.value.toUpperCase())}
                    />

                    <Input
                        label="VAT number"
                        value={form.data.vat_no}
                        error={form.errors.vat_no}
                        hint="Leave empty if you are not VAT-registered."
                        onChange={(event) => form.setData('vat_no', event.target.value)}
                    />

                    <Input
                        label="Billing email"
                        type="email"
                        value={form.data.billing_email}
                        error={form.errors.billing_email}
                        hint={`Empty means we use your account address, ${billing.accountEmail}.`}
                        onChange={(event) => form.setData('billing_email', event.target.value)}
                    />
                </div>

                <div className="mt-4 flex justify-end">
                    <Button
                        loading={form.processing}
                        onClick={() => form.patch('/balance/billing', { preserveScroll: true })}
                    >
                        Save details
                    </Button>
                </div>
            </section>
        </aside>
    );
}

function period(invoice: InvoiceRow): string {
    if (invoice.periodStart === null || invoice.periodEnd === null) {
        // Most invoices are for one order, not a stretch of time. A dash is the
        // honest answer; inventing a period from the issue date is not.
        return '—';
    }

    return `${date(invoice.periodStart)} – ${date(invoice.periodEnd)}`;
}
