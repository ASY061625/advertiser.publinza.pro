import { Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button, Checkbox, Combobox, Input, Select } from '@shared/ui';
import { AuthLayout } from '../../Components/auth/AuthLayout';
import { PasswordField } from '../../Components/auth/PasswordField';
import { email as validateEmail, passwordChecks, required } from '../../Components/auth/validation';

interface Option {
    value: string;
    label: string;
}

interface SignupProps {
    countries: Option[];
    referrerSources: Option[];
    proofLines: string[];
}

export default function Signup({ countries, referrerSources, proofLines }: SignupProps) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        company: '',
        country: '',
        referrer_source: '',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        terms: false as boolean,
    });

    // Client-side messages, shown on blur. The server's errors take precedence
    // once it has answered.
    const [touched, setTouched] = useState<Record<string, string | null>>({});

    function validate(field: string, message: string | null) {
        setTouched((current) => ({ ...current, [field]: message }));
    }

    function errorFor(field: keyof typeof form.data): string | undefined {
        return form.errors[field] ?? touched[field] ?? undefined;
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/signup');
    }

    const passwordReady = passwordChecks(form.data.password).every((check) => check.passed);

    return (
        <AuthLayout
            title="Create your account"
            heading="Create your account"
            subheading="Browsing the catalog is free. You only pay when you place."
            proofLines={proofLines}
            footer={
                <>
                    Already have an account?{' '}
                    <Link href="/login" className="text-brand underline">
                        Sign in
                    </Link>
                </>
            }
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <Input
                    id="name"
                    label="Full name"
                    autoComplete="name"
                    value={form.data.name}
                    error={errorFor('name')}
                    onChange={(event) => form.setData('name', event.target.value)}
                    onBlur={() => validate('name', required('your name')(form.data.name))}
                />

                <Input
                    id="email"
                    label="Work email"
                    type="email"
                    autoComplete="email"
                    value={form.data.email}
                    error={errorFor('email')}
                    onChange={(event) => form.setData('email', event.target.value)}
                    onBlur={() => validate('email', validateEmail(form.data.email))}
                />

                <PasswordField
                    label="Password"
                    value={form.data.password}
                    error={errorFor('password')}
                    onChange={(value) => form.setData('password', value)}
                    onBlur={() =>
                        validate(
                            'password',
                            passwordReady ? null : 'Your password does not meet the requirements below yet.',
                        )
                    }
                />

                <PasswordField
                    id="password_confirmation"
                    label="Confirm password"
                    plain
                    value={form.data.password_confirmation}
                    error={errorFor('password_confirmation')}
                    onChange={(value) => form.setData('password_confirmation', value)}
                    onBlur={() =>
                        validate(
                            'password_confirmation',
                            form.data.password_confirmation !== form.data.password
                                ? 'The two passwords do not match. Retype the second one.'
                                : null,
                        )
                    }
                />

                <Input
                    id="company"
                    label="Company"
                    hint="Optional. It appears on your invoices."
                    autoComplete="organization"
                    value={form.data.company}
                    error={errorFor('company')}
                    onChange={(event) => form.setData('company', event.target.value)}
                />

                <Combobox
                    label="Country"
                    options={countries}
                    value={form.data.country || null}
                    error={errorFor('country')}
                    placeholder="Start typing to search"
                    onChange={(value) => form.setData('country', value ?? '')}
                />

                <Select
                    id="referrer_source"
                    label="How did you hear about us?"
                    options={referrerSources}
                    value={form.data.referrer_source}
                    placeholder="Choose one"
                    error={errorFor('referrer_source')}
                    onChange={(event) => form.setData('referrer_source', event.target.value)}
                />

                <Checkbox
                    id="terms"
                    label="I accept the terms of service and privacy policy"
                    checked={form.data.terms}
                    error={errorFor('terms')}
                    onChange={(event) => form.setData('terms', event.target.checked)}
                />

                <p className="-mt-2 text-sm text-ink-500">
                    Read the{' '}
                    <a href="https://publinza.pro/terms" className="text-brand underline">
                        terms
                    </a>{' '}
                    and{' '}
                    <a href="https://publinza.pro/privacy" className="text-brand underline">
                        privacy policy
                    </a>
                    .
                </p>

                <Button type="submit" size="lg" loading={form.processing} className="w-full">
                    Create account
                </Button>
            </form>
        </AuthLayout>
    );
}
