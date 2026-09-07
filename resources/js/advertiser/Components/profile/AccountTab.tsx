import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Alert, Button, Input, Modal, Select, Switch } from '@shared/ui';
import { useUnsavedGuard } from '@shared/lib/useUnsavedGuard';
import type { DateFormatName, NumberFormatName } from '@shared/lib/format';
import { money } from '@shared/lib/format';
import type { AccountDetails, DeletionState, FormatOption } from '@shared/types/profile';
import { AvatarField } from './AvatarField';
import { SaveBar } from './SaveBar';

interface Props {
    account: AccountDetails;
    countries: { value: string; label: string }[];
    dateFormats: FormatOption[];
    numberFormats: FormatOption[];
    deletion: DeletionState;
}

const LOCALES = [
    { value: 'en', label: 'English' },
    { value: 'de', label: 'Deutsch' },
    { value: 'es', label: 'Español' },
    { value: 'fr', label: 'Français' },
    { value: 'it', label: 'Italiano' },
    { value: 'nl', label: 'Nederlands' },
];

export function AccountTab({ account, countries, dateFormats, numberFormats, deletion }: Props) {
    /*
     * A change already requested is the address in flight, so it — not the one
     * the account still signs in with — is what the field shows and what
     * "changed" is measured against. Baselining on account.email instead would
     * leave the form permanently dirty the moment a change was requested: the
     * save succeeded, but the server can only answer with the old address.
     */
    const emailBaseline = account.pendingEmail ?? account.email;

    const initial = useMemo(
        () => ({
            name: account.name,
            display_name: account.displayName ?? '',
            email: emailBaseline,
            phone: account.phone ?? '',
            phone_country: account.phoneCountry ?? '',
            timezone: account.timezone,
            locale: account.locale,
            date_format: account.dateFormat,
            number_format: account.numberFormat,
        }),
        [account, emailBaseline],
    );

    const form = useForm(initial);
    const dirty = JSON.stringify(form.data) !== JSON.stringify(initial);

    useUnsavedGuard(dirty);

    const typedEmail = form.data.email.trim().toLowerCase();
    const emailChanging =
        typedEmail !== account.email.toLowerCase() && typedEmail !== (account.pendingEmail ?? '').toLowerCase();

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <Section title="Photo">
                <AvatarField url={account.avatarUrl} name={account.displayNameFallback} />
            </Section>

            <Section title="Who you are">
                <div className="grid gap-4 md:grid-cols-2">
                    <Input
                        label="Full name"
                        hint="Appears on invoices and contracts."
                        value={form.data.name}
                        error={form.errors.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                    />

                    <Input
                        label="Display name"
                        hint={`What we call you in the app. Empty means “${account.displayNameFallback}”.`}
                        value={form.data.display_name}
                        error={form.errors.display_name}
                        onChange={(event) => form.setData('display_name', event.target.value)}
                    />
                </div>
            </Section>

            <Section title="How we reach you">
                <div className="flex flex-col gap-4">
                    <div className="max-w-md">
                        <Input
                            label="Email address"
                            type="email"
                            value={form.data.email}
                            error={form.errors.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                    </div>

                    {/* Said before they save, not after. Somebody changing this
                        expects it to take effect, and finding out afterwards
                        that it did not is how a typo becomes a support ticket. */}
                    {emailChanging && (
                        <Alert tone="info" title="You will need to confirm the new address">
                            We send a link to {form.data.email.trim() || 'the new address'}. Until you open it,
                            you keep signing in with {account.email} — so a typo here cannot lock you out.
                        </Alert>
                    )}

                    {account.pendingEmail !== null && (
                        <Alert tone="warning" title={`Waiting on ${account.pendingEmail}`}>
                            <span className="block">
                                We sent a confirmation link there. Your address is still {account.email} until it
                                is opened.
                            </span>
                            <span className="mt-2 flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => router.post('/profile/email/resend', {}, { preserveScroll: true })}
                                >
                                    Send it again
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        router.delete('/profile/email', {
                                            preserveScroll: true,
                                            // Put the field back on the live address:
                                            // the baseline moves with it, and a form
                                            // left showing an abandoned address would
                                            // read as unsaved work.
                                            onSuccess: () => form.setData('email', account.email),
                                        })
                                    }
                                >
                                    Cancel the change
                                </Button>
                            </span>
                        </Alert>
                    )}

                    <div className="grid gap-4 md:grid-cols-[180px_minmax(0,1fr)] md:max-w-2xl">
                        <Select
                            label="Country code"
                            value={form.data.phone_country}
                            error={form.errors.phone_country}
                            onChange={(event) => form.setData('phone_country', event.target.value)}
                            options={[{ value: '', label: '—' }, ...countries]}
                        />

                        <Input
                            label="Phone"
                            type="tel"
                            value={form.data.phone}
                            error={form.errors.phone}
                            onChange={(event) => form.setData('phone', event.target.value)}
                        />
                    </div>
                </div>
            </Section>

            <Section
                title="How things are shown"
                body="These change every date and number across Publinza, not just on this page."
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Select
                        label="Time zone"
                        value={form.data.timezone}
                        error={form.errors.timezone}
                        onChange={(event) => form.setData('timezone', event.target.value)}
                        options={TIMEZONES.map((zone) => ({ value: zone, label: zone.replace(/_/g, ' ') }))}
                    />

                    <Select
                        label="Language"
                        value={form.data.locale}
                        error={form.errors.locale}
                        onChange={(event) => form.setData('locale', event.target.value)}
                        options={LOCALES}
                    />

                    {/* The option labels *are* the examples — "d MMM y" means
                        nothing next to "6 Sep 2026". */}
                    <Select
                        label="Date format"
                        value={form.data.date_format}
                        error={form.errors.date_format}
                        onChange={(event) =>
                            form.setData('date_format', event.target.value as DateFormatName)
                        }
                        options={dateFormats.map((option) => ({ value: option.value, label: option.label }))}
                    />

                    <Select
                        label="Number format"
                        value={form.data.number_format}
                        error={form.errors.number_format}
                        onChange={(event) =>
                            form.setData('number_format', event.target.value as NumberFormatName)
                        }
                        options={numberFormats.map((option) => ({ value: option.value, label: option.label }))}
                    />
                </div>
            </Section>

            <DangerZone deletion={deletion} />

            <SaveBar
                dirty={dirty}
                processing={form.processing}
                onReset={() => form.setData(initial)}
                onSave={() => form.patch('/profile/account', { preserveScroll: true })}
            />
        </div>
    );
}

function DangerZone({ deletion }: { deletion: DeletionState }) {
    const [open, setOpen] = useState(false);

    if (deletion.requestedAt !== null) {
        return (
            <Alert tone="danger" title="This account is scheduled for deletion">
                <span className="block">
                    It will be deleted {deletion.retentionDays} days after you asked. Signing in is not enough to
                    stop it — use the button below.
                </span>
                <span className="mt-3 block">
                    <Button
                        variant="secondary"
                        onClick={() => router.delete('/profile/delete', { preserveScroll: true })}
                    >
                        Keep my account
                    </Button>
                </span>
            </Alert>
        );
    }

    return (
        <>
            <div className="border-t border-subtle pt-5">
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="text-base text-danger underline underline-offset-2 hover:text-danger-pressed"
                >
                    Delete account
                </button>
            </div>

            {open && <DeleteDialog deletion={deletion} onClose={() => setOpen(false)} />}
        </>
    );
}

function DeleteDialog({ deletion, onClose }: { deletion: DeletionState; onClose: () => void }) {
    const form = useForm({ password: '', confirm: false });

    return (
        <Modal
            open
            onClose={onClose}
            size="md"
            title="Delete your account"
            description="Read this before you confirm — some of it cannot be undone."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Keep my account
                    </Button>
                    <Button
                        variant="danger"
                        loading={form.processing}
                        disabled={deletion.blocked}
                        onClick={() => form.post('/profile/delete', { preserveScroll: true, onSuccess: onClose })}
                    >
                        Delete my account
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                {/* The blockers first, because if there are any, nothing below
                    matters and this dialog is only here to explain why. */}
                {deletion.blocked && (
                    <Alert tone="warning" title="Not yet — you have work in flight">
                        <ul className="mt-1 flex list-inside list-disc flex-col gap-1">
                            {deletion.activePosts > 0 && (
                                <li>
                                    {deletion.activePosts} {deletion.activePosts === 1 ? 'post is' : 'posts are'}{' '}
                                    still active. A publisher is part-way through them; cancel them or let them
                                    finish.
                                </li>
                            )}
                            {deletion.frozenCents > 0 && (
                                <li>
                                    <span className="num">{money(deletion.frozenCents)}</span> is frozen against
                                    open orders. That money is yours — it has to be released or spent before the
                                    account can close.
                                </li>
                            )}
                        </ul>
                    </Alert>
                )}

                <div className="rounded-card bg-sunken px-4 py-3 text-base text-ink-700">
                    <p className="font-medium text-ink-900">What happens</p>
                    <ol className="num mt-2 flex list-inside list-decimal flex-col gap-1.5">
                        <li>You are signed out everywhere and cannot sign back in.</li>
                        <li>
                            We keep your data for {deletion.retentionDays} days so the deletion can be reversed if
                            it was a mistake. Ask support within that window and we can bring it back.
                        </li>
                        <li>
                            After {deletion.retentionDays} days it is gone for good. Invoices we are required to
                            keep for tax purposes are retained without your account attached to them.
                        </li>
                    </ol>
                </div>

                <Input
                    label="Your password"
                    type="password"
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                />

                <Switch
                    label={`I understand this deletes my account after ${deletion.retentionDays} days`}
                    checked={form.data.confirm}
                    onCheckedChange={(next) => form.setData('confirm', next)}
                />
                {form.errors.confirm !== undefined && (
                    <p className="text-sm text-danger">{form.errors.confirm}</p>
                )}
            </div>
        </Modal>
    );
}

export function Section({
    title,
    body,
    children,
}: {
    title: string;
    body?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <h2 className="font-sora text-md font-semibold text-ink-900">{title}</h2>
            {body !== undefined && <p className="mt-0.5 max-w-prose text-sm text-ink-500">{body}</p>}
            <div className="mt-4">{children}</div>
        </section>
    );
}

/**
 * A short list rather than all 400-odd IANA zones.
 *
 * The full list is a scroll nobody can navigate; these cover where advertisers
 * actually are, and anything missing is a one-line addition here rather than a
 * search control somebody has to learn.
 */
const TIMEZONES = [
    'UTC',
    'Europe/London',
    'Europe/Dublin',
    'Europe/Lisbon',
    'Europe/Madrid',
    'Europe/Paris',
    'Europe/Amsterdam',
    'Europe/Brussels',
    'Europe/Berlin',
    'Europe/Zurich',
    'Europe/Rome',
    'Europe/Vienna',
    'Europe/Prague',
    'Europe/Warsaw',
    'Europe/Stockholm',
    'Europe/Oslo',
    'Europe/Copenhagen',
    'Europe/Helsinki',
    'Europe/Tallinn',
    'Europe/Riga',
    'Europe/Vilnius',
    'Europe/Athens',
    'Europe/Bucharest',
    'Europe/Sofia',
    'Europe/Istanbul',
    'Europe/Kyiv',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Toronto',
    'America/Vancouver',
    'America/Sao_Paulo',
    'America/Mexico_City',
    'Asia/Jerusalem',
    'Asia/Dubai',
    'Asia/Karachi',
    'Asia/Kolkata',
    'Asia/Bangkok',
    'Asia/Singapore',
    'Asia/Hong_Kong',
    'Asia/Shanghai',
    'Asia/Tokyo',
    'Asia/Seoul',
    'Australia/Perth',
    'Australia/Sydney',
    'Pacific/Auckland',
];
