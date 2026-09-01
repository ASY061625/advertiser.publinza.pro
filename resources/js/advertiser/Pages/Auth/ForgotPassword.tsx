import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Alert, Button, Input } from '@shared/ui';
import { AuthLayout } from '../../Components/auth/AuthLayout';
import { email as validateEmail } from '../../Components/auth/validation';
import { useState } from 'react';

export default function ForgotPassword({ proofLines }: { proofLines: string[] }) {
    const { props } = usePage<{ flash: { status?: string } }>();
    const form = useForm({ email: '' });
    const [touched, setTouched] = useState<string | null>(null);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/forgot-password');
    }

    return (
        <AuthLayout
            title="Reset your password"
            heading="Reset your password"
            subheading="We will email you a link to choose a new one."
            proofLines={proofLines}
            footer={
                <Link href="/login" className="text-brand underline">
                    Back to sign in
                </Link>
            }
        >
            {/* The same message whether or not the address has an account, so the
                form cannot be used to find out who is a customer. */}
            {props.flash?.status ? (
                <Alert tone="success" title="Check your inbox">
                    {props.flash.status}
                </Alert>
            ) : (
                <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                    <Input
                        id="email"
                        label="Email"
                        type="email"
                        autoComplete="username"
                        autoFocus
                        value={form.data.email}
                        error={form.errors.email ?? touched ?? undefined}
                        onChange={(event) => form.setData('email', event.target.value)}
                        onBlur={() => setTouched(validateEmail(form.data.email))}
                    />

                    <Button type="submit" size="lg" loading={form.processing} className="w-full">
                        Email me a link
                    </Button>
                </form>
            )}
        </AuthLayout>
    );
}
