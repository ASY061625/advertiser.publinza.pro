import { router, useForm } from '@inertiajs/react';
import { useMemo, useRef } from 'react';
import { Alert, Button, Input, Select, Textarea } from '@shared/ui';
import { useUnsavedGuard } from '@shared/lib/useUnsavedGuard';
import type { CompanyDetails } from '@shared/types/profile';
import { SaveBar } from './SaveBar';
import { Section } from './AccountTab';

interface Props {
    company: CompanyDetails;
    countries: { value: string; label: string }[];
    vatCountries: string[];
}

export function CompanyTab({ company, countries, vatCountries }: Props) {
    const initial = useMemo(
        () => ({
            company: company.company ?? '',
            registration_no: company.registrationNo ?? '',
            vat_no: company.vatNo ?? '',
            country: company.country ?? '',
            billing_address: company.billingAddress ?? '',
            billing_email: company.billingEmail ?? '',
        }),
        [company],
    );

    const form = useForm(initial);
    const dirty = JSON.stringify(form.data) !== JSON.stringify(initial);

    useUnsavedGuard(dirty);

    // The example follows the country picker live, not the saved value — the
    // whole point is to show the rule before somebody types the wrong thing.
    const knowsVat = vatCountries.includes(form.data.country);

    return (
        <div className="flex min-w-0 flex-col gap-6">
            {/* Before the fields, not after the save. Somebody correcting a VAT
                number is usually trying to fix an invoice they have already
                been sent, and needs to know this is not how to do that. */}
            <Alert tone="info" title="These appear on every invoice">
                Invoices already issued keep the details they were issued with — a company that moves does not
                get to rewrite last quarter's receipts. If one of those is wrong, message us and we will reissue
                it.
            </Alert>

            <Section title="The company">
                <div className="grid gap-4 md:grid-cols-2">
                    <Input
                        label="Company name"
                        value={form.data.company}
                        error={form.errors.company}
                        onChange={(event) => form.setData('company', event.target.value)}
                    />

                    <Input
                        label="Registration number"
                        hint="Companies House, Handelsregister, or your local equivalent."
                        value={form.data.registration_no}
                        error={form.errors.registration_no}
                        onChange={(event) => form.setData('registration_no', event.target.value)}
                    />

                    <Select
                        label="Country"
                        value={form.data.country}
                        error={form.errors.country}
                        onChange={(event) => form.setData('country', event.target.value)}
                        options={[{ value: '', label: 'Choose a country…' }, ...countries]}
                    />

                    <Input
                        label="VAT number"
                        value={form.data.vat_no}
                        error={form.errors.vat_no}
                        hint={
                            form.data.country === ''
                                ? 'Choose a country and we will show you the format.'
                                : knowsVat
                                  ? `${form.data.country} numbers look like ${exampleFor(form.data.country)}.`
                                  : 'We have no format rule for that country, so we only check it starts with a country code.'
                        }
                        onChange={(event) => form.setData('vat_no', event.target.value.toUpperCase())}
                    />
                </div>
            </Section>

            <Section title="Where invoices go">
                <div className="flex flex-col gap-4">
                    <Textarea
                        label="Billing address"
                        rows={4}
                        value={form.data.billing_address}
                        error={form.errors.billing_address}
                        onChange={(event) => form.setData('billing_address', event.target.value)}
                    />

                    <div className="max-w-md">
                        <Input
                            label="Billing email"
                            type="email"
                            hint={`Empty means we use your account address, ${company.accountEmail}.`}
                            value={form.data.billing_email}
                            error={form.errors.billing_email}
                            onChange={(event) => form.setData('billing_email', event.target.value)}
                        />
                    </div>
                </div>
            </Section>

            <Section title="Logo" body="Printed on your invoices beside our own details.">
                <LogoField url={company.logoUrl} />
            </Section>

            <SaveBar
                dirty={dirty}
                processing={form.processing}
                onReset={() => form.setData(initial)}
                onSave={() => form.patch('/profile/company', { preserveScroll: true })}
            />
        </div>
    );
}

function LogoField({ url }: { url: string | null }) {
    const input = useRef<HTMLInputElement>(null);

    return (
        <div className="flex flex-wrap items-center gap-4">
            <span className="flex h-16 w-32 items-center justify-center overflow-hidden rounded-card border border-subtle bg-sunken">
                {url === null ? (
                    <span className="text-sm text-ink-500">No logo</span>
                ) : (
                    <img src={url} alt="Company logo" className="max-h-full max-w-full object-contain" />
                )}
            </span>

            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap gap-2">
                    <input
                        ref={input}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        className="hidden"
                        tabIndex={-1}
                        aria-hidden="true"
                        onChange={(event) => {
                            const file = event.target.files?.[0];
                            event.target.value = '';

                            if (file === undefined) return;

                            router.post('/profile/company-logo', { logo: file }, {
                                forceFormData: true,
                                preserveScroll: true,
                            });
                        }}
                    />

                    <Button variant="secondary" onClick={() => input.current?.click()}>
                        {url === null ? 'Upload a logo' : 'Replace logo'}
                    </Button>

                    {url !== null && (
                        <Button
                            variant="ghost"
                            onClick={() => router.delete('/profile/company-logo', { preserveScroll: true })}
                        >
                            Remove
                        </Button>
                    )}
                </div>

                <p className="text-sm text-ink-500">PNG or JPEG, up to 2 MB. A wide logo reproduces best.</p>
            </div>
        </div>
    );
}

/**
 * Mirrors VatNumber::exampleFor on the server.
 *
 * Only the countries whose format the server actually enforces — showing an
 * example for a country the server will not check is a promise the form cannot
 * keep.
 */
function exampleFor(country: string): string {
    const examples: Record<string, string> = {
        AT: 'ATU12345678', BE: 'BE0123456789', BG: 'BG123456789', CY: 'CY12345678L',
        CZ: 'CZ12345678', DE: 'DE123456789', DK: 'DK12345678', EE: 'EE123456789',
        ES: 'ESX12345678', FI: 'FI12345678', FR: 'FRXX123456789', GB: 'GB123456789',
        GR: 'EL123456789', HR: 'HR12345678901', HU: 'HU12345678', IE: 'IE1234567A',
        IT: 'IT12345678901', LT: 'LT123456789', LU: 'LU12345678', LV: 'LV12345678901',
        MT: 'MT12345678', NL: 'NL123456789B01', PL: 'PL1234567890', PT: 'PT123456789',
        RO: 'RO1234567890', SE: 'SE123456789001', SI: 'SI12345678', SK: 'SK1234567890',
        CH: 'CHE123456789MWST', NO: 'NO123456789MVA',
    };

    return examples[country] ?? 'XX123456789';
}
