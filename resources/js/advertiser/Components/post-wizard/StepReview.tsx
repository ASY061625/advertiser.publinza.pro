import { Switch, WalletIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { money, number } from '@shared/lib/format';
import type { PostWizardState, WizardProject, WizardWebsite } from '@shared/types/postWizard';

interface Props {
    state: PostWizardState;
    patch: (changes: Partial<PostWizardState>) => void;
    project: WizardProject | null;
    site: WizardWebsite | null;
    wallet: { availableCents: number; frozenCents: number };
    article: File | null;
}

/**
 * Everything, once, before anything is bought.
 *
 * The wallet impact is the part that is not just a recap: balance before,
 * amount frozen, balance after. An advertiser deciding whether to place this
 * now is deciding against a number they cannot see from here otherwise, and
 * "insufficient funds" after the fact is the worst place to learn it.
 */
export function StepReview({ state, patch, project, site, wallet, article }: Props) {
    const service = site?.services.find((entry) => entry.type === state.serviceType) ?? site?.services[0];
    const base = service?.priceCents ?? 0;
    const writing = state.contentMode === 'publisher_writes' ? (service?.writingFeeCents ?? 0) : 0;
    const express = state.express ? (service?.expressFeeCents ?? 0) : 0;
    const total = base + writing + express;

    const after = wallet.availableCents - total;
    const short = Math.max(0, -after);

    const folder = project?.folders.find((entry) => String(entry.id) === state.folderId) ?? null;

    return (
        <div className="flex flex-col gap-4">
            <dl className="divide-y divide-subtle rounded-card border border-subtle">
                <Row label="Project" value={project?.name ?? '—'} />
                <Row label="Folder" value={folder?.name ?? 'No folder'} />
                <Row label="Website" value={site?.domain ?? '—'} />
                <Row label="Service" value={service?.label ?? '—'} />
                <Row label="Anchor" value={state.anchorText === '' ? '—' : `“${state.anchorText}”`} />
                <Row label="Target URL" value={state.targetUrl === '' ? '—' : state.targetUrl} wrap />
                <Row
                    label="Article"
                    value={
                        state.contentMode === 'publisher_writes'
                            ? 'The publisher writes it'
                            : article !== null
                              ? article.name
                              : state.body === ''
                                ? 'You’ll add it later'
                                : `${number(countWords(state.body))} words, pasted`
                    }
                />
            </dl>

            {(service?.expressFeeCents ?? 0) > 0 && (
                <Switch
                    label="Express delivery"
                    hint={`+${money(service?.expressFeeCents ?? 0)} — moves this to the front of the publisher’s queue.`}
                    checked={state.express}
                    onCheckedChange={(next) => patch({ express: next })}
                />
            )}

            <div className="rounded-card border border-subtle bg-card p-4">
                <dl className="flex flex-col gap-2">
                    <Line label="Placement" value={base} />
                    {writing > 0 && <Line label="Publisher writes it" value={writing} prefix="+" muted />}
                    {express > 0 && <Line label="Express delivery" value={express} prefix="+" muted />}

                    <div className="flex items-baseline justify-between gap-3 border-t border-subtle pt-2">
                        <dt className="font-sora text-md font-semibold text-ink-900">Total</dt>
                        <dd className="num font-sora text-lg font-semibold text-ink-900">{money(total)}</dd>
                    </div>
                </dl>
            </div>

            <div className="flex flex-col gap-2 rounded-card border border-subtle bg-sunken p-4">
                <p className="flex items-center gap-1.5 font-sora text-base font-semibold text-ink-900">
                    <WalletIcon size={14} />
                    Your balance
                </p>

                <dl className="flex flex-col gap-1 text-base">
                    <Line label="Now" value={wallet.availableCents} />
                    <Line label="Frozen by this order" value={total} prefix="−" muted />

                    <div className="flex items-baseline justify-between gap-3 border-t border-subtle pt-1.5">
                        <dt className="text-ink-700">After</dt>
                        <dd className={cn('num font-medium', after < 0 ? 'text-warning' : 'text-ink-900')}>
                            {money(Math.max(0, after))}
                        </dd>
                    </div>
                </dl>

                {short > 0 ? (
                    <p className="num text-sm text-warning">
                        {money(short)} short. You can still add it to your cart and top up before checking
                        out.
                    </p>
                ) : (
                    <p className="text-sm text-ink-500">
                        Frozen, not spent. It is released to the publisher only once the link is verified as
                        live.
                    </p>
                )}
            </div>
        </div>
    );
}

function Row({ label, value, wrap = false }: { label: string; value: string; wrap?: boolean }) {
    return (
        <div className="flex items-baseline justify-between gap-4 px-3 py-2">
            <dt className="shrink-0 text-sm text-ink-500">{label}</dt>
            <dd className={cn('text-right text-base text-ink-900', wrap ? 'break-all' : 'truncate')}>
                {value}
            </dd>
        </div>
    );
}

function Line({
    label,
    value,
    prefix = '',
    muted = false,
}: {
    label: string;
    value: number;
    prefix?: string;
    muted?: boolean;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className={muted ? 'text-ink-500' : 'text-ink-700'}>{label}</dt>
            <dd className={muted ? 'num text-ink-500' : 'num text-ink-900'}>
                {prefix}
                {money(value)}
            </dd>
        </div>
    );
}

function countWords(text: string): number {
    const plain = text.replace(/\s+/g, ' ').trim();

    return plain === '' ? 0 : plain.split(' ').length;
}
