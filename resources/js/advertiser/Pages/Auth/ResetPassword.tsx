import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button, Input } from '@shared/ui';
import { AuthLayout } from '../../Components/auth/AuthLayout';
import { PasswordField } from '../../Components/auth/PasswordField';
import { passwordChecks } from '../../Components/auth/validation';

interface ResetPasswordProps {
    token: string;
    email: string;
    proofLines: string[];
}

export default function ResetPassword({ token, email, proofLines }: ResetPasswordProps) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });
    const [touched, setTouched] = useState<Record<string, string | null>>({});

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/reset-password');
    }

    return (
        <AuthLayout
            title="Choose a new password"
            heading="Choose a new password"
            subheading="Setting it signs you out everywhere else, including trusted devices."
            proofLines={proofLines}
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <Input
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="username"
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                />

                <PasswordField
                    label="New password"
                    value={form.data.password}
                    error={form.errors.password ?? touched.password ?? undefined}
                    onChange={(value) => form.setData('password', value)}
                    onBlur={() =>
                        setTouched((c) => ({
                            ...c,
                            password: passwordChecks(form.data.password).every((check) => check.passed)
                                ? null
                                : 'Your password does not meet the requirements below yet.',
                        }))
                    }
                />

                <PasswordField
                    id="password_confirmation"
                    label="Confirm new password"
                    plain
                    value={form.data.password_confirmation}
                    error={touched.password_confirmation ?? undefined}
                    onChange={(value) => form.setData('password_confirmation', value)}
                    onBlur={() =>
                        setTouched((c) => ({
                            ...c,
                            password_confirmation:
                                form.data.password_confirmation !== form.data.password
                                    ? 'The two passwords do not match. Retype the second one.'
                                    : null,
                        }))
                    }
                />

                <Button type="submit" size="lg" loading={form.processing} className="w-full">
                    Set new password
                </Button>
            </form>
        </AuthLayout>
    );
}
