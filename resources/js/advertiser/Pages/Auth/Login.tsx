import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@shared/ui';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/login');
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-canvas px-6">
            <Head title="Sign in" />

            <form onSubmit={submit} className="card w-full max-w-sm p-6">
                <h1 className="font-sora text-lg font-semibold text-ink-900">Sign in to Publinza</h1>

                <label className="mt-6 block text-sm text-ink-500" htmlFor="email">
                    Email
                </label>
                <input
                    id="email"
                    type="email"
                    autoComplete="email"
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    className="mt-1 h-9 w-full rounded-input border border-ink-300 px-3 text-base"
                />
                {form.errors.email && <p className="mt-1 text-sm text-danger">{form.errors.email}</p>}

                <label className="mt-4 block text-sm text-ink-500" htmlFor="password">
                    Password
                </label>
                <input
                    id="password"
                    type="password"
                    autoComplete="current-password"
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    className="mt-1 h-9 w-full rounded-input border border-ink-300 px-3 text-base"
                />

                <Button type="submit" disabled={form.processing} className="mt-6 w-full">
                    Sign in
                </Button>
            </form>
        </div>
    );
}
