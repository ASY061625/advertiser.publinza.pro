import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { Alert, Badge, Button, CheckIcon, CopyIcon, Input, Modal, WarningIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { dateTime, relativeTime } from '@shared/lib/format';
import type { ActiveSession, LoginRow, SecurityLogRow, SecurityState } from '@shared/types/profile';
import { PasswordStrength } from './PasswordStrength';
import { Section } from './AccountTab';

export function SecurityTab({ security }: { security: SecurityState }) {
    return (
        <div className="flex min-w-0 flex-col gap-6">
            <PasswordSection />
            <TwoFactorSection state={security.twoFactor} />
            <SessionsSection sessions={security.sessions} />
            <LoginHistorySection history={security.loginHistory} />
            <LogSection rows={security.log} />
        </div>
    );
}

// ------------------------------------------------------------------ password

function PasswordSection() {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <Section
            title="Password"
            body="Changing it signs out every other browser you are signed in on, and emails you."
        >
            <div className="flex max-w-md flex-col gap-4">
                <Input
                    label="Current password"
                    type="password"
                    autoComplete="current-password"
                    value={form.data.current_password}
                    error={form.errors.current_password}
                    onChange={(event) => form.setData('current_password', event.target.value)}
                />

                <div>
                    <Input
                        label="New password"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password}
                        error={form.errors.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                    />
                    <PasswordStrength value={form.data.password} />
                </div>

                <Input
                    label="Confirm new password"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    error={
                        form.data.password_confirmation !== '' &&
                        form.data.password_confirmation !== form.data.password
                            ? 'These do not match.'
                            : undefined
                    }
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                />

                <div className="flex justify-end">
                    <Button
                        loading={form.processing}
                        disabled={form.data.current_password === '' || form.data.password === ''}
                        onClick={() =>
                            form.patch('/profile/password', {
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            })
                        }
                    >
                        Change password
                    </Button>
                </div>
            </div>
        </Section>
    );
}

// ---------------------------------------------------------------- two-factor

function TwoFactorSection({ state }: { state: SecurityState['twoFactor'] }) {
    const [confirming, setConfirming] = useState<'disable' | 'regenerate' | null>(null);

    if (state.enabled) {
        return (
            <Section title="Two-factor authentication" body="On. You are asked for a code when you sign in.">
                <div className="flex flex-col gap-4">
                    <p className="flex items-center gap-2 text-base text-ink-700">
                        <CheckIcon size={16} className="text-teal" />
                        {state.recoveryCodesLeft} recovery {state.recoveryCodesLeft === 1 ? 'code' : 'codes'}{' '}
                        remaining.
                    </p>

                    {state.recoveryCodesLeft <= 2 && (
                        <Alert tone="warning" title="You are running low on recovery codes">
                            These are what get you in if you lose your phone. Generate a fresh set — the old ones
                            stop working when you do.
                        </Alert>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => setConfirming('regenerate')}>
                            Regenerate recovery codes
                        </Button>

                        <Button variant="ghost" onClick={() => setConfirming('disable')}>
                            Turn off
                        </Button>
                    </div>
                </div>

                {confirming !== null && (
                    <ConfirmPasswordDialog intent={confirming} onClose={() => setConfirming(null)} />
                )}
            </Section>
        );
    }

    if (state.pending && state.secret !== null) {
        return <SetupSection state={state} />;
    }

    return (
        <Section
            title="Two-factor authentication"
            body="Off. A password alone is all anybody needs to sign in as you."
        >
            <Button onClick={() => router.post('/settings/two-factor', {}, { preserveScroll: true })}>
                Set up two-factor
            </Button>
        </Section>
    );
}

function SetupSection({ state }: { state: SecurityState['twoFactor'] }) {
    const [svg, setSvg] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const form = useForm({ code: '' });

    // Rendered in the browser: the provisioning URI contains the shared secret,
    // and putting it through an image service would hand that secret to a third
    // party in a query string.
    useEffect(() => {
        if (state.provisioningUri === null) return;

        let live = true;

        void QRCode.toString(state.provisioningUri, {
            type: 'svg',
            margin: 0,
            width: 176,
            color: { dark: '#0B1B33', light: '#FFFFFF' },
        })
            .then((markup) => live && setSvg(markup))
            .catch(() => undefined);

        return () => {
            live = false;
        };
    }, [state.provisioningUri]);

    return (
        <Section title="Set up two-factor" body="Scan this with your authenticator, then type the code it shows.">
            <div className="flex flex-col gap-5 md:flex-row md:items-start md:gap-8">
                <div className="shrink-0">
                    {svg === null ? (
                        <span className="flex size-44 items-center justify-center rounded-card border border-subtle bg-sunken text-sm text-ink-500">
                            Loading…
                        </span>
                    ) : (
                        <span
                            className="block rounded-card border border-subtle bg-white p-3"
                             
                            dangerouslySetInnerHTML={{ __html: svg }}
                        />
                    )}
                </div>

                <div className="flex min-w-0 flex-1 flex-col gap-4">
                    <div>
                        <p className="text-sm text-ink-500">Can't scan it? Type this key in instead.</p>
                        <p className="num mt-1 flex flex-wrap items-center gap-2 break-all text-base text-ink-900">
                            {state.secret}
                            <button
                                type="button"
                                aria-label="Copy setup key"
                                onClick={() => {
                                    void navigator.clipboard
                                        ?.writeText(state.secret ?? '')
                                        .then(() => setCopied(true))
                                        .catch(() => undefined);
                                }}
                                className="flex size-7 items-center justify-center rounded-button text-ink-500 hover:bg-sunken"
                            >
                                {copied ? <CheckIcon size={14} className="text-teal" /> : <CopyIcon size={14} />}
                            </button>
                        </p>
                    </div>

                    <div className="max-w-[220px]">
                        <Input
                            label="Code from your app"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            placeholder="123456"
                            value={form.data.code}
                            error={form.errors.code}
                            onChange={(event) => form.setData('code', event.target.value)}
                        />
                    </div>

                    <div className="flex gap-2">
                        <Button
                            loading={form.processing}
                            disabled={form.data.code.length < 6}
                            onClick={() => form.post('/settings/two-factor/confirm', { preserveScroll: true })}
                        >
                            Turn on two-factor
                        </Button>

                        {/* No password: nothing is protecting anything until a
                            code has been confirmed, and asking for one to
                            abandon a half-finished setup is a dead end. */}
                        <Button
                            variant="ghost"
                            onClick={() => router.delete('/settings/two-factor', { preserveScroll: true })}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            </div>
        </Section>
    );
}

/**
 * Re-authentication for the two actions that weaken the second factor.
 *
 * Turning it off is the obvious one. Regenerating is the quiet one: a hijacked
 * session that mints a fresh set has given itself eight standing bypasses and
 * silently voided the set the real owner has on paper.
 */
function ConfirmPasswordDialog({
    intent,
    onClose,
}: {
    intent: 'disable' | 'regenerate';
    onClose: () => void;
}) {
    const form = useForm({ password: '' });
    const disabling = intent === 'disable';

    return (
        <Modal
            open
            onClose={onClose}
            size="sm"
            title={disabling ? 'Turn off two-factor?' : 'Generate new recovery codes?'}
            description={
                disabling
                    ? 'Your account goes back to being protected by a password alone.'
                    : 'The eight codes you have now stop working the moment the new ones appear.'
            }
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        {disabling ? 'Keep it on' : 'Cancel'}
                    </Button>
                    <Button
                        variant={disabling ? 'danger' : 'primary'}
                        loading={form.processing}
                        onClick={() =>
                            disabling
                                ? form.delete('/settings/two-factor', {
                                      preserveScroll: true,
                                      onSuccess: onClose,
                                  })
                                : form.post('/settings/two-factor/recovery-codes', {
                                      preserveScroll: true,
                                      onSuccess: onClose,
                                  })
                        }
                    >
                        {disabling ? 'Turn off' : 'Generate'}
                    </Button>
                </>
            }
        >
            <Input
                label="Your password"
                type="password"
                autoComplete="current-password"
                value={form.data.password}
                error={form.errors.password}
                onChange={(event) => form.setData('password', event.target.value)}
            />
        </Modal>
    );
}

// ------------------------------------------------------------------ sessions

function SessionsSection({ sessions }: { sessions: SecurityState['sessions'] }) {
    if (!sessions.supported) {
        return (
            <Section title="Active sessions">
                <p className="text-base text-ink-500">
                    This deployment stores sessions somewhere they cannot be listed. Changing your password still
                    signs out every other browser.
                </p>
            </Section>
        );
    }

    const others = sessions.sessions.filter((session) => !session.current).length;

    return (
        <Section title="Active sessions" body="Every browser currently signed in as you.">
            <ul className="flex flex-col gap-2">
                {sessions.sessions.map((session) => (
                    <SessionRow key={session.id} session={session} />
                ))}
            </ul>

            {others > 0 && (
                <div className="mt-4">
                    <Button
                        variant="secondary"
                        onClick={() => router.post('/profile/sessions/revoke-others', {}, { preserveScroll: true })}
                    >
                        Sign out {others === 1 ? 'the other session' : `all ${others} other sessions`}
                    </Button>
                </div>
            )}
        </Section>
    );
}

function SessionRow({ session }: { session: ActiveSession }) {
    return (
        <li
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-1 rounded-card border px-4 py-3',
                session.current ? 'border-brand bg-brand-subtle' : 'border-subtle',
            )}
        >
            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="text-base font-medium text-ink-900">{session.label}</span>
                    {session.current && (
                        <span className="rounded-pill bg-brand px-2 py-0.5 text-xs font-medium text-white">
                            This device
                        </span>
                    )}
                </span>

                <span className="num mt-0.5 block text-sm text-ink-500">
                    {session.ip ?? 'Unknown IP'} ·{' '}
                    {session.lastActiveAt === null ? 'Unknown' : `active ${relativeTime(session.lastActiveAt)}`}
                </span>
            </span>

            {!session.current && (
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        router.post('/profile/sessions/revoke', { id: session.id }, { preserveScroll: true })
                    }
                >
                    Revoke
                </Button>
            )}
        </li>
    );
}

// ------------------------------------------------------------- login history

function LoginHistorySection({ history }: { history: SecurityState['loginHistory'] }) {
    return (
        <Section
            title="Recent sign-ins"
            body={
                history.geoAvailable
                    ? 'The last 20 attempts. Anything from a country you have not used before is flagged.'
                    : 'The last 20 attempts. This deployment cannot resolve locations, so country is unknown.'
            }
        >
            {history.rows.length === 0 ? (
                <p className="text-base text-ink-500">Nothing recorded yet.</p>
            ) : (
                <>
                {/* The phone stack. Five columns cannot fit 390px, and the
                    country — the one column this table exists for — is the
                    last of them, so on a narrow screen the flag would sit
                    entirely off-viewport. */}
                <ol className="flex flex-col gap-2 md:hidden">
                    {history.rows.map((row) => (
                        <li
                            key={row.id}
                            className={cn(
                                'rounded-card border border-subtle p-3',
                                row.newCountry && 'border-warning bg-warning-bg',
                            )}
                        >
                            <span className="flex flex-wrap items-center justify-between gap-2">
                                <span className="num text-sm text-ink-500">
                                    {row.at === null ? '—' : dateTime(row.at)}
                                </span>
                                <Badge
                                    status={row.successful ? 'posted' : 'rejected'}
                                    label={row.successful ? 'Signed in' : 'Failed'}
                                />
                            </span>

                            <span className="mt-1 block text-base text-ink-700">{row.label}</span>

                            <span className="num mt-0.5 flex flex-wrap items-center gap-x-2 text-sm text-ink-500">
                                {row.ip ?? '—'}
                                <span aria-hidden>·</span>
                                {row.countryName === null ? (
                                    'Unknown'
                                ) : (
                                    <span
                                        className={cn(
                                            'flex items-center gap-1',
                                            row.newCountry && 'font-medium text-warning',
                                        )}
                                    >
                                        {row.newCountry && <WarningIcon size={13} className="shrink-0" />}
                                        {row.countryName}
                                        {row.newCountry && <span className="text-xs">(new)</span>}
                                    </span>
                                )}
                            </span>
                        </li>
                    ))}
                </ol>

                <div className="relative hidden overflow-x-auto md:block">
                    <table className="w-full border-collapse text-left text-base" style={{ minWidth: '620px' }}>
                        <thead>
                            <tr>
                                {['When', 'Outcome', 'Device', 'IP', 'Location'].map((heading) => (
                                    <th
                                        key={heading}
                                        scope="col"
                                        className="border-b border-subtle px-3 py-2 text-sm font-medium text-ink-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {history.rows.map((row) => (
                                <LoginHistoryRow key={row.id} row={row} />
                            ))}
                        </tbody>
                    </table>
                </div>
                </>
            )}
        </Section>
    );
}

function LoginHistoryRow({ row }: { row: LoginRow }) {
    return (
        <tr className={cn('border-b border-subtle last:border-0', row.newCountry && 'bg-warning-bg')}>
            <td className="whitespace-nowrap px-3 py-2.5 text-sm text-ink-500">
                {row.at === null ? '—' : dateTime(row.at)}
            </td>
            <td className="px-3 py-2.5">
                <Badge
                    status={row.successful ? 'posted' : 'rejected'}
                    label={row.successful ? 'Signed in' : 'Failed'}
                />
            </td>
            <td className="px-3 py-2.5 text-sm text-ink-700">{row.label}</td>
            <td className="num whitespace-nowrap px-3 py-2.5 text-sm text-ink-700">{row.ip ?? '—'}</td>
            <td className="px-3 py-2.5 text-sm">
                {row.countryName === null ? (
                    <span className="text-ink-500">Unknown</span>
                ) : (
                    <span className={cn('flex items-center gap-1.5', row.newCountry && 'font-medium text-warning')}>
                        {row.newCountry && <WarningIcon size={13} className="shrink-0" />}
                        {row.countryName}
                        {row.newCountry && <span className="text-xs">(new)</span>}
                    </span>
                )}
            </td>
        </tr>
    );
}

// --------------------------------------------------------------- change log

function LogSection({ rows }: { rows: SecurityLogRow[] }) {
    if (rows.length === 0) return null;

    return (
        <Section title="Security changes" body="What has been changed on this account, and from where.">
            <ol className="flex flex-col gap-2.5">
                {rows.map((row) => (
                    <li key={row.id} className="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 text-base">
                        <span className="text-ink-900">{row.label}</span>
                        <span className="num text-sm text-ink-500">
                            {row.at === null ? '' : dateTime(row.at)}
                            {row.ip === null ? '' : ` · ${row.ip}`}
                        </span>
                    </li>
                ))}
            </ol>
        </Section>
    );
}
