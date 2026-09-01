import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Alert, Button, Card, Input } from '@shared/ui';
import { AppShell } from '../../Layouts/AppShell';

interface TwoFactorProps {
    enabled: boolean;
    pending: boolean;
    secret: string | null;
    provisioningUri: string | null;
    recoveryCodesLeft: number;
}

/** Groups the secret into fours so it can be typed in without losing your place. */
function groupSecret(secret: string): string {
    return (secret.match(/.{1,4}/g) ?? []).join(' ');
}

export default function TwoFactor({ enabled, pending, secret, provisioningUri, recoveryCodesLeft }: TwoFactorProps) {
    const { props } = usePage<{ flash: { status?: string; recoveryCodes?: string[] } }>();
    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });
    const regenerateForm = useForm({});

    const codes = props.flash?.recoveryCodes;

    function confirm(event: FormEvent) {
        event.preventDefault();
        confirmForm.post('/settings/two-factor/confirm');
    }

    function disable(event: FormEvent) {
        event.preventDefault();
        disableForm.delete('/settings/two-factor');
    }

    return (
        <AppShell
            title="Two-factor authentication"
            crumbs={[{ label: 'Settings', href: '/settings/account' }, { label: 'Two-factor authentication' }]}
        >
            <Head title="Two-factor authentication" />

            <div className="flex max-w-2xl flex-col gap-6">
                {props.flash?.status && <Alert tone="success" title={props.flash.status} />}

                {/* Shown once, in the response that created them. There is no
                    way back to this list — the codes are stored hashed. */}
                {codes && codes.length > 0 && (
                    <Card title="Save these recovery codes now">
                        <p className="text-base text-ink-700">
                            Each code works once, if you lose access to your authenticator. This is the only time they
                            are shown — we store them hashed and cannot show them again.
                        </p>
                        <ul className="num mt-4 grid grid-cols-2 gap-2 rounded-card bg-sunken p-4">
                            {codes.map((code) => (
                                <li key={code} className="text-md text-ink-900">
                                    {code}
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                {!enabled && !pending && (
                    <Card title="Two-factor authentication">
                        <p className="text-base text-ink-700">
                            Add a second step to signing in, using an authenticator app. Optional, and you can turn it
                            off again whenever you like.
                        </p>
                        <Button
                            className="mt-5"
                            loading={enableForm.processing}
                            onClick={() => enableForm.post('/settings/two-factor')}
                        >
                            Set up two-factor
                        </Button>
                    </Card>
                )}

                {pending && secret && (
                    <Card title="Add Publinza to your authenticator">
                        <ol className="flex flex-col gap-4 text-base text-ink-700">
                            <li>
                                <span className="font-medium text-ink-900">1. Add the account.</span> On a phone, open
                                this link to jump straight into your app:
                                {provisioningUri && (
                                    <a href={provisioningUri} className="ml-1 break-all text-brand underline">
                                        add to authenticator
                                    </a>
                                )}
                            </li>
                            <li>
                                <span className="font-medium text-ink-900">2. Or enter the key by hand.</span>
                                <code className="num mt-2 block rounded-card bg-sunken px-3 py-2 text-md tracking-wider text-ink-900">
                                    {groupSecret(secret)}
                                </code>
                            </li>
                            <li>
                                <span className="font-medium text-ink-900">3. Enter the code it shows.</span>
                                <form onSubmit={confirm} className="mt-3 flex items-end gap-3">
                                    <div className="w-40">
                                        <Input
                                            id="code"
                                            label="Six-digit code"
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            className="num tracking-[0.3em]"
                                            value={confirmForm.data.code}
                                            error={confirmForm.errors.code}
                                            onChange={(event) => confirmForm.setData('code', event.target.value)}
                                        />
                                    </div>
                                    <Button type="submit" loading={confirmForm.processing}>
                                        Turn on two-factor
                                    </Button>
                                </form>
                            </li>
                        </ol>
                    </Card>
                )}

                {enabled && (
                    <>
                        <Card title="Two-factor is on">
                            <p className="text-base text-ink-700">
                                You have {recoveryCodesLeft} recovery {recoveryCodesLeft === 1 ? 'code' : 'codes'} left.
                                Generating a new set invalidates the old one.
                            </p>
                            <Button
                                variant="secondary"
                                className="mt-5"
                                loading={regenerateForm.processing}
                                onClick={() => regenerateForm.post('/settings/two-factor/recovery-codes')}
                            >
                                Generate new recovery codes
                            </Button>
                        </Card>

                        <Card title="Turn two-factor off">
                            <form onSubmit={disable} className="flex items-end gap-3">
                                <div className="flex-1">
                                    <Input
                                        id="password"
                                        label="Confirm your password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={disableForm.data.password}
                                        error={disableForm.errors.password}
                                        onChange={(event) => disableForm.setData('password', event.target.value)}
                                    />
                                </div>
                                <Button type="submit" variant="danger" loading={disableForm.processing}>
                                    Turn off
                                </Button>
                            </form>
                        </Card>
                    </>
                )}
            </div>
        </AppShell>
    );
}
