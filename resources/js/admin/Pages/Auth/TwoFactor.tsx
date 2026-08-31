import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@shared/components/Button';

export default function TwoFactor() {
    const form = useForm({ code: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/asylogin/two-factor');
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-ink-900 px-6">
            <Head title="Two-factor code" />

            <form onSubmit={submit} className="w-full max-w-sm rounded-card bg-surface-card p-6 shadow-card">
                <h1 className="font-sora text-lg font-semibold text-ink-900">Enter your two-factor code</h1>
                <p className="mt-2 text-base text-ink-500">Open your authenticator app and enter the current code.</p>

                <input
                    aria-label="Two-factor code"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    value={form.data.code}
                    onChange={(event) => form.setData('code', event.target.value)}
                    className="tabular mt-5 h-9 w-full rounded-input border border-ink-300 px-3 text-base"
                />
                {form.errors.code && <p className="mt-1 text-sm text-danger">{form.errors.code}</p>}

                <Button type="submit" disabled={form.processing} className="mt-6 w-full">
                    Verify code
                </Button>
            </form>
        </div>
    );
}
