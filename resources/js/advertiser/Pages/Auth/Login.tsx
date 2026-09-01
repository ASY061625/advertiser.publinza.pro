import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Alert, Button, Checkbox, Input } from '@shared/ui';
import { AuthLayout } from '../../Components/auth/AuthLayout';
import { PasswordField } from '../../Components/auth/PasswordField';
import { email as validateEmail, required } from '../../Components/auth/validation';

export default function Login({ proofLines }: { proofLines: string[] }) {
    const { props } = usePage<{ flash: { status?: string } }>();
    const form = useForm({ email: '', password: '', remember: false as boolean });
    const [touched, setTouched] = useState<Record<string, string | null>>({});

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    }

    return (
        <AuthLayout
            title="Sign in"
            heading="Sign in"
            subheading="Advertiser accounts only."
            proofLines={proofLines}
            footer={
                <>
                    New to Publinza?{' '}
                    <Link href="/signup" className="text-brand underline">
                        Create an account
                    </Link>
                </>
            }
        >
            {props.flash?.status && (
                <div className="mb-6">
                    <Alert tone="success" title={props.flash.status} />
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <Input
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    value={form.data.email}
                    error={form.errors.email ?? touched.email ?? undefined}
                    onChange={(event) => form.setData('email', event.target.value)}
                    onBlur={() => setTouched((c) => ({ ...c, email: validateEmail(form.data.email) }))}
                />

                <PasswordField
                    label="Password"
                    plain
                    autoComplete="current-password"
                    value={form.data.password}
                    error={form.errors.password ?? touched.password ?? undefined}
                    onChange={(value) => form.setData('password', value)}
                    onBlur={() =>
                        setTouched((c) => ({ ...c, password: required('your password')(form.data.password) }))
                    }
                />

                <div className="flex items-center justify-between">
                    <Checkbox
                        id="remember"
                        label="Remember me"
                        checked={form.data.remember}
                        onChange={(event) => form.setData('remember', event.target.checked)}
                    />
                    <Link href="/forgot-password" className="text-base text-brand underline">
                        Forgot password?
                    </Link>
                </div>

                <Button type="submit" size="lg" loading={form.processing} className="w-full">
                    Sign in
                </Button>
            </form>
        </AuthLayout>
    );
}
