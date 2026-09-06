import { Link } from '@inertiajs/react';
import { Button, PlusIcon } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { BalanceOverview } from '@shared/types/balance';

/**
 * The three figures, largest type on the page.
 *
 * Available leads because it answers "can I buy something right now". Frozen is
 * second and in gold because it is the number people query — it is theirs, it
 * is not spendable, and it is not gone. Total spent is last and quietest: it is
 * history, not a decision.
 */
export function BalanceCards({ overview, onTopUp }: { overview: BalanceOverview; onTopUp: () => void }) {
    return (
        <div className="grid gap-4 md:grid-cols-3">
            <Card
                label="Available"
                value={money(overview.availableCents)}
                tone="ink"
                body="Ready to spend"
                action={
                    <Button onClick={onTopUp}>
                        <PlusIcon size={14} />
                        Top up
                    </Button>
                }
            />

            <Card
                label="Frozen"
                value={money(overview.frozenCents)}
                tone="gold"
                body="Held against active posts. Released when links are verified."
                action={
                    overview.frozenPosts.count > 0 ? (
                        <Link
                            href={overview.frozenPosts.href}
                            className="text-base font-medium text-brand hover:underline"
                        >
                            {overview.frozenPosts.count === 1
                                ? 'See the 1 post holding it'
                                : `See the ${overview.frozenPosts.count} posts holding it`}
                        </Link>
                    ) : null
                }
            />

            <Card
                label="Total spent"
                value={money(overview.spent.lifetimeCents)}
                tone="muted"
                body={`${money(overview.spent.yearCents)} of it in ${overview.spent.year}`}
            />
        </div>
    );
}

function Card({
    label,
    value,
    tone,
    body,
    action,
}: {
    label: string;
    value: string;
    tone: 'ink' | 'gold' | 'muted';
    body: string;
    action?: React.ReactNode;
}) {
    const colour = tone === 'gold' ? 'text-gold' : tone === 'muted' ? 'text-ink-700' : 'text-ink-900';

    return (
        <section className="flex flex-col rounded-card border border-subtle bg-card p-5 shadow-card">
            <h2 className="text-sm font-medium text-ink-500">{label}</h2>

            {/* tabular-nums via .num, so three cards side by side do not jitter
                as the digits change. */}
            <p className={`num mt-1 font-sora text-xl font-semibold ${colour}`}>{value}</p>

            <p className="mt-2 flex-1 text-base text-ink-500">{body}</p>

            {action && <div className="mt-4">{action}</div>}
        </section>
    );
}
