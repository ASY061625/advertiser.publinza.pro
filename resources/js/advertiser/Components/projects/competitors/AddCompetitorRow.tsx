import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button, Input, PlusIcon } from '@shared/ui';

interface Props {
    projectId: number;
    used: number;
    limit: number;
    /** Focused on mount for the empty state, where it is the only thing to do. */
    autoFocus?: boolean;
    disabled?: boolean;
}

/**
 * The one place a rival is added.
 *
 * Validation is in two layers on purpose. What is typed is normalised and
 * checked here as it is typed, so "https://Example.com/pricing" is shown back
 * as "example.com" before anything is submitted; and the server checks it
 * again, because the browser is not where a limit is enforced.
 */
export function AddCompetitorRow({ projectId, used, limit, autoFocus = false, disabled = false }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ domain: '', label: '' });
    const [hint, setHint] = useState<string | null>(null);
    const domainRef = useRef<HTMLInputElement>(null);

    const full = used >= limit;

    useEffect(() => {
        if (autoFocus) domainRef.current?.focus();
    }, [autoFocus]);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        post(`/projects/${projectId}/competitors`, {
            preserveScroll: true,
            only: ['competitors', 'flash', 'errors'],
            onSuccess: () => {
                reset();
                setHint(null);
                domainRef.current?.focus();
            },
        });
    }

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-3 rounded-card border border-subtle bg-card p-4 shadow-card sm:flex-row sm:items-start"
        >
            <div className="min-w-0 flex-1">
                <Input
                    ref={domainRef}
                    label="Competitor domain"
                    placeholder="competitor.com"
                    value={data.domain}
                    disabled={disabled || full}
                    error={errors.domain}
                    hint={hint ?? undefined}
                    onChange={(event) => {
                        setData('domain', event.target.value);
                        if (errors.domain) clearErrors('domain');
                        setHint(previewHost(event.target.value));
                    }}
                />
            </div>

            <div className="min-w-0 sm:w-56">
                <Input
                    label="Label (optional)"
                    placeholder="Main rival"
                    value={data.label}
                    disabled={disabled || full}
                    error={errors.label}
                    onChange={(event) => setData('label', event.target.value)}
                />
            </div>

            <div className="flex items-center gap-3 sm:pt-[26px]">
                <Button type="submit" loading={processing} disabled={disabled || full || data.domain.trim() === ''}>
                    <PlusIcon size={14} />
                    Add competitor
                </Button>

                <span className="num whitespace-nowrap text-sm text-ink-500">
                    {used} of {limit} slots used
                </span>
            </div>
        </form>
    );
}

/**
 * What the server will store, shown before it is submitted.
 *
 * Deliberately silent while the input is still obviously mid-typing: a hint
 * that flickers "that is not a website" at someone three characters into
 * typing one is noise, not help.
 */
function previewHost(input: string): string | null {
    const value = input.trim();

    if (value === '' || !value.includes('.')) return null;

    try {
        const url = new URL(/^[a-z][a-z0-9+.-]*:\/\//i.test(value) ? value : `https://${value}`);
        const host = url.hostname.toLowerCase();

        return host === value.toLowerCase() ? null : `Will be tracked as ${host}`;
    } catch {
        return 'That does not look like a website address.';
    }
}
