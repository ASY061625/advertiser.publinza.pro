import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { useToast } from '@shared/ui';

interface FlashProps {
    flash?: { success?: string | null; error?: string | null };
    [key: string]: unknown;
}

/**
 * Raises the server's flash messages as toasts.
 *
 * Every redirect in this app already carries `->with('success', …)` or
 * `->with('error', …)`, and until this existed none of them were rendered
 * anywhere: archiving a project, deleting one, saving a folder — all of it
 * flashed into nothing and the screen just changed silently.
 *
 * Keyed on the Inertia page component and url so the same message shown twice
 * in a row still appears twice, but a partial reload of the page you are
 * already on does not repeat it.
 */
export function useFlashToasts(): void {
    const page = usePage<FlashProps>();
    const { toast } = useToast();
    const lastKey = useRef<string | null>(null);

    const flash = page.props.flash;
    const success = flash?.success ?? null;
    const error = flash?.error ?? null;
    const key = `${page.url}|${success ?? ''}|${error ?? ''}`;

    useEffect(() => {
        if (success === null && error === null) {
            lastKey.current = null;

            return;
        }

        if (lastKey.current === key) return;
        lastKey.current = key;

        if (error !== null) toast({ tone: 'danger', title: error });
        else if (success !== null) toast({ tone: 'success', title: success });
    }, [key, success, error, toast]);
}
