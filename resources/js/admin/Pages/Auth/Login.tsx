import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@shared/ui';

export default function AdminLogin() {
    const form = useForm({ email: '', password: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/asylogin/login');
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-ink-900 px-6">
            <Head title="Admin sign in" />

            <form onSubmit={submit} className="w-full max-w-sm rounded-card bg-card p-6 shadow-card">
                <h1 className="font-sora text-lg font-semibold text-ink-900">Admin sign in</h1>

                <label className="mt-6 block text-sm text-ink-500" htmlFor="email">
                    Email
                </label>
                <input
                    id="email"
                    type="email"
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
