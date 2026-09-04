import { Head, Link } from '@inertiajs/react';
import { AppShell } from '../../Layouts/AppShell';
import { Badge, Button, CartIcon, CheckIcon, DownloadIcon, ListIcon, type StatusKey } from '@shared/ui';
import { date, money } from '@shared/lib/format';
import type { OrderSummaryPost } from '@shared/types/cart';

interface Props {
    order: {
        number: string;
        subtotalCents: number;
        discountCents: number;
        totalCents: number;
        placedAt: string | null;
        invoiceNumber: string;
    };
    posts: OrderSummaryPost[];
}

/**
 * The receipt.
 *
 * The publication window per site is the thing this page exists for. An order
 * number confirms something happened; "1–2 days" against each domain answers
 * the question the buyer actually has, which is when they can expect to see
 * anything. It is a window rather than a date because a publication period is a
 * promise about a range, and printing one day implies a precision nobody
 * offered.
 */
export default function CheckoutSuccess({ order, posts }: Props) {
    const drafts = posts.filter((post) => post.isDraft);

    return (
        <AppShell title="Order placed" crumbs={[{ label: 'Cart', href: '/cart' }, { label: order.number }]}>
            <Head title={`Order ${order.number}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <header className="flex flex-col items-center gap-3 rounded-card border border-subtle bg-card px-6 py-8 text-center shadow-card">
                    <span className="flex size-12 items-center justify-center rounded-full bg-success-bg text-success">
                        <CheckIcon size={24} />
                    </span>

                    <h1 className="font-sora text-xl font-semibold text-ink-900">Order placed</h1>

                    <p className="num text-base text-ink-500">
                        {order.number}
                        {order.placedAt && ` · ${date(order.placedAt)}`}
                    </p>

                    <dl className="mt-2 flex flex-wrap items-baseline justify-center gap-x-6 gap-y-1">
                        <div className="flex items-baseline gap-2">
                            <dt className="text-sm text-ink-500">Placements</dt>
                            <dd className="num font-medium text-ink-900">{posts.length}</dd>
                        </div>

                        {order.discountCents > 0 && (
                            <div className="flex items-baseline gap-2">
                                <dt className="text-sm text-ink-500">Discount</dt>
                                <dd className="num font-medium text-success">−{money(order.discountCents)}</dd>
                            </div>
                        )}

                        <div className="flex items-baseline gap-2">
                            <dt className="text-sm text-ink-500">Frozen</dt>
                            <dd className="num font-sora text-md font-semibold text-ink-900">
                                {money(order.totalCents)}
                            </dd>
                        </div>
                    </dl>

                    <p className="max-w-md text-sm text-ink-500">
                        That amount is held against this order, not spent. Each publisher is paid only once
                        their link has been verified as live.
                    </p>
                </header>

                {drafts.length > 0 && (
                    <p className="rounded-card border border-warning bg-warning-bg px-4 py-3 text-base text-ink-700">
                        <span className="num font-semibold text-ink-900">{drafts.length}</span>{' '}
                        {drafts.length === 1 ? 'placement is' : 'placements are'} waiting on your article and{' '}
                        {drafts.length === 1 ? 'stays a draft' : 'stay drafts'} until you submit it. Nothing
                        starts on {drafts.length === 1 ? 'it' : 'them'} until then.
                    </p>
                )}

                <section className="overflow-hidden rounded-card border border-subtle bg-card shadow-card">
                    <h2 className="border-b border-subtle px-4 py-3 font-sora text-md font-semibold text-ink-900">
                        Expected publication
                    </h2>

                    <ul className="divide-y divide-subtle">
                        {posts.map((post) => (
                            <li key={post.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3">
                                <span className="min-w-0 flex-1">
                                    <span className="block font-medium text-ink-900">{post.domain}</span>
                                    {post.project && (
                                        <span className="block text-sm text-ink-500">{post.project}</span>
                                    )}
                                </span>

                                <Badge status={post.status as StatusKey} label={post.statusLabel} />

                                <span className="w-28 shrink-0 text-right text-sm text-ink-700">
                                    {post.isDraft ? 'Waiting on you' : post.window}
                                </span>

                                <span className="num w-20 shrink-0 text-right text-ink-900">
                                    {money(post.priceCents)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                <div className="flex flex-wrap gap-3">
                    <Link href="/posts">
                        <Button size="lg">
                            <ListIcon size={16} />
                            View posts
                        </Button>
                    </Link>

                    <Link href="/catalog">
                        <Button size="lg" variant="secondary">
                            <CartIcon size={16} />
                            Back to catalog
                        </Button>
                    </Link>

                    {/* A plain link, not an Inertia visit: this is a file
                        download and Inertia would try to render it as a page. */}
                    <a href={`/checkout/${order.number}/invoice`}>
                        <Button size="lg" variant="secondary">
                            <DownloadIcon size={16} />
                            Download invoice
                        </Button>
                    </a>
                </div>
            </div>
        </AppShell>
    );
}
