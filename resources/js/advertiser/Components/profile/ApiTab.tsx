import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Alert, Button, CheckIcon, CopyIcon, DownloadIcon, ExternalLinkIcon, Input, Modal, WarningIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { date, relativeTime } from '@shared/lib/format';
import type { AdvertiserSharedProps } from '@shared/types';
import type { ApiInfo, TokenRow } from '@shared/types/profile';
import { download } from './RecoveryCodes';
import { Section } from './AccountTab';

interface Props {
    api: ApiInfo;
    tokens: TokenRow[];
}

export function ApiTab({ api, tokens }: Props) {
    const page = usePage<AdvertiserSharedProps & { flash: { newToken?: { name: string; plain: string } | null } }>();
    const [creating, setCreating] = useState(false);
    const [issued, setIssued] = useState<{ name: string; plain: string } | null>(null);

    // Flashed by the response that created it. Null, not absent: the flash bag
    // carries every key on every response and fills the unset ones with null.
    const flashed = page.props.flash.newToken;

    useEffect(() => {
        if (flashed) setIssued(flashed);
    }, [flashed]);

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <Section
                title="Personal access tokens"
                body="For scripts and integrations. A token acts as you, within the scopes you give it."
            >
                <div className="flex flex-col gap-4">
                    {tokens.length === 0 ? (
                        <p className="text-base text-ink-500">No tokens yet.</p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {tokens.map((token) => (
                                <TokenItem key={token.id} token={token} abilities={api.abilities} />
                            ))}
                        </ul>
                    )}

                    <div>
                        <Button onClick={() => setCreating(true)}>Generate a token</Button>
                    </div>
                </div>
            </Section>

            <Section title="Using the API">
                <div className="flex flex-col gap-4">
                    <p className="text-base text-ink-700">
                        Send the token as a bearer header. Every response is JSON.
                    </p>

                    <CurlExample baseUrl={api.baseUrl} />

                    <dl className="flex flex-col gap-2">
                        <div className="flex items-baseline justify-between gap-4">
                            <dt className="text-base text-ink-500">Rate limit</dt>
                            <dd className="num text-base text-ink-900">
                                {api.rateLimit.requests} requests per {api.rateLimit.window}
                            </dd>
                        </div>
                        <div className="flex items-baseline justify-between gap-4">
                            <dt className="text-base text-ink-500">Base URL</dt>
                            <dd className="num break-all text-base text-ink-900">{api.baseUrl}</dd>
                        </div>
                    </dl>

                    <a
                        href={api.docsUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 text-base font-medium text-brand hover:underline"
                    >
                        Read the API documentation
                        <ExternalLinkIcon size={13} />
                    </a>
                </div>
            </Section>

            {creating && <CreateDialog api={api} onClose={() => setCreating(false)} />}
            {issued !== null && <IssuedDialog token={issued} onClose={() => setIssued(null)} />}
        </div>
    );
}

function TokenItem({ token, abilities }: { token: TokenRow; abilities: ApiInfo['abilities'] }) {
    const form = useForm({});
    const names = token.abilities.map(
        (value) => abilities.find((ability) => ability.value === value)?.label ?? value,
    );

    return (
        <li
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-2 rounded-card border px-4 py-3',
                token.expired ? 'border-subtle bg-sunken' : 'border-subtle',
            )}
        >
            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="text-base font-medium text-ink-900">{token.name}</span>
                    {token.expired && (
                        <span className="rounded-pill bg-ink-300/30 px-2 py-0.5 text-xs font-medium text-ink-700">
                            Expired
                        </span>
                    )}
                </span>

                <span className="mt-0.5 block text-sm text-ink-500">{names.join(' · ')}</span>

                <span className="num mt-0.5 block text-sm text-ink-500">
                    {token.createdAt === null ? '' : `Created ${date(token.createdAt)}`}
                    {' · '}
                    {/* "Never used" is the fact somebody is looking for when
                        they are deciding whether a token is safe to revoke. */}
                    {token.lastUsedAt === null ? 'Never used' : `Last used ${relativeTime(token.lastUsedAt)}`}
                    {token.expiresAt === null ? '' : ` · Expires ${date(token.expiresAt)}`}
                </span>
            </span>

            <Button
                size="sm"
                variant="ghost"
                loading={form.processing}
                onClick={() => form.delete(`/profile/tokens/${token.id}`, { preserveScroll: true })}
            >
                Revoke
            </Button>
        </li>
    );
}

function CreateDialog({ api, onClose }: { api: ApiInfo; onClose: () => void }) {
    const form = useForm({ name: '', abilities: [] as string[], expires_at: '' });

    const toggle = (value: string) => {
        form.setData(
            'abilities',
            form.data.abilities.includes(value)
                ? form.data.abilities.filter((item) => item !== value)
                : [...form.data.abilities, value],
        );
    };

    return (
        <Modal
            open
            onClose={onClose}
            size="md"
            title="Generate a token"
            description="Give it a name you will recognise in six months, and only the scopes it needs."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        loading={form.processing}
                        disabled={form.data.name.trim() === '' || form.data.abilities.length === 0}
                        onClick={() => {
                            form.transform((data) => ({
                                ...data,
                                expires_at: data.expires_at === '' ? null : data.expires_at,
                            }));

                            form.post('/profile/tokens', { preserveScroll: true, onSuccess: onClose });
                        }}
                    >
                        Generate
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <Input
                    label="Name"
                    placeholder="Reporting script"
                    value={form.data.name}
                    error={form.errors.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                />

                <fieldset>
                    <legend className="text-sm font-medium text-ink-700">What it may do</legend>
                    {form.errors.abilities !== undefined && (
                        <p className="mt-1 text-sm text-danger">{form.errors.abilities}</p>
                    )}

                    <ul className="mt-2 flex flex-col gap-2">
                        {api.abilities.map((ability) => (
                            <li key={ability.value}>
                                <label
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-card border px-4 py-3 transition-colors duration-fast',
                                        form.data.abilities.includes(ability.value)
                                            ? 'border-brand bg-brand-subtle'
                                            : 'border-subtle hover:bg-sunken',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.abilities.includes(ability.value)}
                                        onChange={() => toggle(ability.value)}
                                        className="mt-0.5 size-4 accent-[var(--brand-blue)]"
                                    />

                                    <span className="min-w-0">
                                        <span className="flex flex-wrap items-center gap-2 text-base text-ink-900">
                                            {ability.label}
                                            {ability.destructive && (
                                                <span className="flex items-center gap-1 rounded-pill bg-warning-bg px-2 py-0.5 text-xs font-medium text-warning">
                                                    <WarningIcon size={11} />
                                                    Spends money
                                                </span>
                                            )}
                                        </span>
                                        <span className="mt-0.5 block text-sm text-ink-500">
                                            {ability.description}
                                        </span>
                                    </span>
                                </label>
                            </li>
                        ))}
                    </ul>
                </fieldset>

                <div className="max-w-[220px]">
                    <Input
                        label="Expires"
                        type="date"
                        hint="Leave empty for a token that never expires."
                        min={new Date(Date.now() + 86_400_000).toISOString().slice(0, 10)}
                        value={form.data.expires_at}
                        error={form.errors.expires_at}
                        onChange={(event) => form.setData('expires_at', event.target.value)}
                    />
                </div>
            </div>
        </Modal>
    );
}

/**
 * The token, shown once.
 *
 * Same treatment as recovery codes, for the same reason: only its hash is
 * stored, so this dialog is the one and only time it exists in readable form.
 */
function IssuedDialog({ token, onClose }: { token: { name: string; plain: string }; onClose: () => void }) {
    const [copied, setCopied] = useState(false);

    return (
        <Modal
            open
            onClose={() => undefined}
            size="md"
            title="Your new token"
            description={`Copy “${token.name}” now — this is the only time it is shown.`}
            footer={<Button onClick={onClose}>Done</Button>}
        >
            <div className="flex flex-col gap-4">
                <Alert tone="warning" title="Copy it before you close this">
                    We store only a hash of it, so we cannot show it again. If you lose it, revoke this token and
                    generate another.
                </Alert>

                <p className="num break-all rounded-card bg-sunken px-4 py-3 text-base text-ink-900">
                    {token.plain}
                </p>

                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="secondary"
                        onClick={() => {
                            void navigator.clipboard
                                ?.writeText(token.plain)
                                .then(() => setCopied(true))
                                .catch(() => undefined);
                        }}
                    >
                        {copied ? <CheckIcon size={14} /> : <CopyIcon size={14} />}
                        {copied ? 'Copied' : 'Copy token'}
                    </Button>

                    <Button
                        variant="secondary"
                        onClick={() =>
                            download(
                                `publinza-token-${token.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.txt`,
                                `${token.name}\n${token.plain}\n`,
                            )
                        }
                    >
                        <DownloadIcon size={14} />
                        Download
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function CurlExample({ baseUrl }: { baseUrl: string }) {
    const [copied, setCopied] = useState(false);

    const snippet = [
        `curl ${baseUrl}/posts \\`,
        '  -H "Authorization: Bearer pzt_your_token_here" \\',
        '  -H "Accept: application/json"',
    ].join('\n');

    return (
        <div className="relative">
            <pre className="num overflow-x-auto rounded-card bg-ink-900 px-4 py-3 text-sm text-white">
                {snippet}
            </pre>

            <button
                type="button"
                aria-label="Copy example"
                onClick={() => {
                    void navigator.clipboard
                        ?.writeText(snippet)
                        .then(() => setCopied(true))
                        .catch(() => undefined);
                }}
                className="absolute right-2 top-2 flex size-8 items-center justify-center rounded-button text-white/70 hover:bg-white/10 hover:text-white"
            >
                {copied ? <CheckIcon size={14} /> : <CopyIcon size={14} />}
            </button>
        </div>
    );
}
