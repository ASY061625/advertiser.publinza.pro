import { Link } from '@inertiajs/react';
import { Badge, DownloadIcon, ExternalLinkIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { compactNumber, date, money, number } from '@shared/lib/format';
import type { PostContext, ThreadContext, WebsiteContext } from '@shared/types/conversations';

/**
 * The far-right panel: what the conversation is about.
 *
 * A thread tied to a post shows the placement, because that is what the
 * messages keep referring back to — the anchor, what it cost, where it got to.
 * A thread about a site shows the site. Never both: two panels of facts is a
 * reference book beside a conversation, and nobody reads it.
 */
export function contextTitle(context: ThreadContext): string {
    return context.kind === 'post' ? 'This placement' : 'This website';
}

export function ContextPanel({ context }: { context: ThreadContext }) {
    return (
        <aside className="flex h-full min-h-0 w-full flex-col overflow-y-auto border-subtle bg-card lg:border-l">
            <div className="border-b border-subtle px-4 py-3">
                <h2 className="font-sora text-base font-semibold text-ink-900">{contextTitle(context)}</h2>
            </div>

            <ContextBody context={context} />
        </aside>
    );
}

/**
 * The panel's contents without its own chrome.
 *
 * Below lg there is no room for a third column, so the same facts are shown in
 * a Drawer — which brings its own header, close button and focus trap. Two
 * presentations of one body, rather than two components that will drift.
 */
export function ContextBody({ context }: { context: ThreadContext }) {
    return (
        <div className="flex flex-col gap-5 px-4 py-4">
            {context.kind === 'post' ? <PostPanel post={context} /> : <WebsitePanel site={context} />}
        </div>
    );
}

function WebsitePanel({ site }: { site: WebsiteContext }) {
    return (
        <>
            <div>
                <Link
                    href={`/catalog/website/${site.slug}`}
                    className="flex items-center gap-1 font-sora text-base font-medium text-ink-900 hover:text-brand"
                >
                    {site.domain}
                    <ExternalLinkIcon size={13} className="text-ink-500" />
                </Link>

                {site.title !== null && <p className="mt-0.5 text-sm text-ink-500">{site.title}</p>}

                <p className="mt-2 flex flex-wrap gap-1.5">
                    {[site.category, site.country, site.language].filter(Boolean).map((chip) => (
                        <span key={chip} className="rounded-pill bg-sunken px-2 py-0.5 text-xs text-ink-700">
                            {chip}
                        </span>
                    ))}
                </p>
            </div>

            <Section title="Metrics">
                <dl className="grid grid-cols-2 gap-3">
                    {site.metrics.map((metric) => (
                        <div key={metric.label}>
                            <dt className="text-xs text-ink-500">{metric.label}</dt>
                            <dd className="num font-sora text-base font-medium text-ink-900">
                                {/* A dash, not a zero. A site nobody has crawled
                                    and a site with no traffic are opposite
                                    answers for anyone deciding whether to buy. */}
                                {metric.value === null
                                    ? '—'
                                    : metric.format === 'compact'
                                      ? compactNumber(metric.value)
                                      : number(metric.value)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </Section>

            <Section title="Terms">
                <Rows
                    rows={[
                        ['Publication', site.terms.publicationLabel],
                        ['Link type', site.terms.linkType === 'dofollow' ? 'Dofollow' : 'Nofollow'],
                        ['Links allowed', String(site.terms.maxLinks)],
                        ['Minimum words', number(site.terms.minWords)],
                        ['Marked sponsored', site.terms.marksSponsored ? 'Yes' : 'No'],
                        [
                            'Link guarantee',
                            site.terms.linkGuaranteeMonths === 0
                                ? 'None'
                                : `${site.terms.linkGuaranteeMonths} months`,
                        ],
                    ]}
                />
            </Section>
        </>
    );
}

function PostPanel({ post }: { post: PostContext }) {
    return (
        <>
            <div>
                <p className="flex items-center gap-2">
                    <span className="num font-sora text-base font-medium text-ink-900">Post #{post.id}</span>
                    <Badge status={post.badge} label={post.statusLabel} />
                </p>

                {post.domain !== null && <p className="mt-0.5 text-sm text-ink-500">{post.domain}</p>}
            </div>

            <Section title="Placement">
                <Rows
                    rows={[
                        ['Anchor text', post.anchorText ?? '—'],
                        [
                            'Target URL',
                            post.targetUrl === null ? (
                                '—'
                            ) : (
                                <a
                                    href={post.targetUrl}
                                    target="_blank"
                                    rel="noopener noreferrer nofollow"
                                    className="break-all text-brand hover:underline"
                                >
                                    {post.targetUrl}
                                </a>
                            ),
                        ],
                        ['Price', money(post.priceCents)],
                        [
                            'Published',
                            post.publishedUrl === null ? (
                                'Not yet'
                            ) : (
                                <a
                                    href={post.publishedUrl}
                                    target="_blank"
                                    rel="noopener noreferrer nofollow"
                                    className="break-all text-brand hover:underline"
                                >
                                    View the article
                                </a>
                            ),
                        ],
                    ]}
                />
            </Section>

            {post.timeline.length > 0 && (
                <Section title="Status">
                    <ol className="flex flex-col">
                        {post.timeline.map((entry, index) => (
                            <li key={entry.id} className="flex gap-2.5">
                                <span className="flex flex-col items-center">
                                    <span
                                        className={cn(
                                            'mt-1.5 size-2 shrink-0 rounded-pill',
                                            index === post.timeline.length - 1 ? 'bg-brand' : 'bg-ink-300',
                                        )}
                                    />
                                    {index < post.timeline.length - 1 && (
                                        <span className="w-px flex-1 bg-ink-300" />
                                    )}
                                </span>

                                <span className="pb-3 text-sm">
                                    <span className="block text-ink-900">{label(entry.to)}</span>
                                    {entry.at !== null && (
                                        <span className="num block text-xs text-ink-500">{date(entry.at)}</span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ol>
                </Section>
            )}

            {post.hasArticle && (
                <form method="post" action="/posts/bulk">
                    {/* The same endpoint the grid's bulk download uses, with one
                        id. A second download path would be a second place for
                        the article's filename and format to drift. */}
                    <input type="hidden" name="_token" value={csrf()} />
                    <input type="hidden" name="action" value="download" />
                    <input type="hidden" name="ids[]" value={post.id} />

                    <button
                        type="submit"
                        className="flex w-full items-center justify-center gap-2 rounded-button border border-subtle bg-card px-3 py-2 text-base text-ink-900 transition-colors duration-fast hover:bg-sunken"
                    >
                        <DownloadIcon size={14} />
                        Download the article
                    </button>
                </form>
            )}
        </>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section>
            <h3 className="mb-2 text-xs font-medium uppercase tracking-wide text-ink-500">{title}</h3>
            {children}
        </section>
    );
}

function Rows({ rows }: { rows: [string, React.ReactNode][] }) {
    return (
        <dl className="flex flex-col gap-2">
            {rows.map(([term, value]) => (
                <div key={term} className="flex items-baseline justify-between gap-3">
                    <dt className="shrink-0 text-sm text-ink-500">{term}</dt>
                    <dd className="min-w-0 text-right text-sm text-ink-900">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

function label(status: string): string {
    return status.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
}

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
