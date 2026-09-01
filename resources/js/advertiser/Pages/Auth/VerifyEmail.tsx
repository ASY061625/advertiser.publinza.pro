import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import { Alert, Button } from '@shared/ui';
import { AuthLayout } from '../../Components/auth/AuthLayout';

interface VerifyEmailProps {
    email: string;
    proofLines: string[];
    /** Seconds left on the 60-second resend window, 0 when it is available. */
    resendAvailableIn: number;
}

export default function VerifyEmail({ email, proofLines, resendAvailableIn }: VerifyEmailProps) {
    const { props } = usePage<{ flash: { status?: string } }>();
    const form = useForm({});
    const [cooldown, setCooldown] = useState(resendAvailableIn);

    // Counts the window down so the button says when it will work rather than
    // failing silently on a second tap.
    useEffect(() => {
        if (cooldown <= 0) return;

        const timer = window.setInterval(() => setCooldown((current) => Math.max(0, current - 1)), 1000);

        return () => window.clearInterval(timer);
    }, [cooldown]);

    function resend(event: FormEvent) {
        event.preventDefault();
        form.post('/verify-email/resend', { onSuccess: () => setCooldown(60) });
    }

    return (
        <AuthLayout
            title="Confirm your email"
            heading="Confirm your email"
            subheading={
                <>
                    We sent a link to <span className="font-medium text-ink-900">{email}</span>. Open it and your
                    account is ready.
                </>
            }
            proofLines={proofLines}
        >
            {props.flash?.status && (
                <div className="mb-6">
                    <Alert tone="info" title={props.flash.status} />
                </div>
            )}

            <form onSubmit={resend} className="flex flex-col gap-4">
                <Button type="submit" size="lg" disabled={cooldown > 0} loading={form.processing} className="w-full">
                    {cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend the email'}
                </Button>

                <p className="text-sm text-ink-500">
                    Not there? Check your spam folder. The link works for 60 minutes, and you can ask for another once a
                    minute.
                </p>
            </form>
        </AuthLayout>
    );
}
