import { Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button, Checkbox, Input } from '@shared/ui';
import { AuthLayout } from '../../Components/auth/AuthLayout';

interface TwoFactorChallengeProps {
    proofLines: string[];
    recoveryCodesLeft: number;
}

export default function TwoFactorChallenge({ proofLines, recoveryCodesLeft }: TwoFactorChallengeProps) {
    const [useRecovery, setUseRecovery] = useState(false);
    const form = useForm({ code: '', trust_device: false as boolean });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/two-factor-challenge', { onFinish: () => form.reset('code') });
    }

    return (
        <AuthLayout
            title="Two-factor code"
            heading={useRecovery ? 'Use a recovery code' : 'Enter your code'}
            subheading={
                useRecovery
                    ? 'One of the eight codes you saved when you turned two-factor on. Each works once.'
                    : 'Open your authenticator app and enter the six-digit code it is showing.'
            }
            proofLines={proofLines}
            footer={
                <Link href="/login" className="text-brand underline">
                    Sign in as someone else
                </Link>
            }
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <Input
                    id="code"
                    label={useRecovery ? 'Recovery code' : 'Six-digit code'}
                    autoFocus
                    autoComplete="one-time-code"
                    inputMode={useRecovery ? 'text' : 'numeric'}
                    placeholder={useRecovery ? 'ABCDE-FGHIJ' : '123456'}
                    className="num tracking-[0.3em]"
                    value={form.data.code}
                    error={form.errors.code}
                    onChange={(event) => form.setData('code', event.target.value)}
                />

                <Checkbox
                    id="trust_device"
                    label="Trust this device for 30 days"
                    hint="Skip this step on this browser. Do not use it on a shared computer."
                    checked={form.data.trust_device}
                    onChange={(event) => form.setData('trust_device', event.target.checked)}
                />

                <Button type="submit" size="lg" loading={form.processing} className="w-full">
                    {useRecovery ? 'Use recovery code' : 'Verify code'}
                </Button>

                <button
                    type="button"
                    onClick={() => {
                        setUseRecovery((current) => !current);
                        form.reset('code');
                        form.clearErrors();
                    }}
                    className="rounded-button text-base text-brand underline"
                >
                    {useRecovery
                        ? 'Use my authenticator app instead'
                        : `Lost your phone? Use a recovery code (${recoveryCodesLeft} left)`}
                </button>
            </form>
        </AuthLayout>
    );
}
