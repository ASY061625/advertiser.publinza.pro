import { Link } from '@inertiajs/react';
import { Badge, type StatusKey } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type { CatalogSiteDetail, PlacementTerms, SitePlacement } from '@shared/types/catalog';
import { flagFor } from '../flags';

/** The countries a flag glyph has no name for on its own. */
const REGIONS = new Intl.DisplayNames(['en'], { type: 'region' });

/**
 * Where the traffic comes from.
 *
 * One measure, one hue, bars against a shared 100% — so a country's bar length
 * is comparable between two sites, not just within one. The percentages are of
 * all measured traffic rather than of the eight shown, which is why they do not
 * add to a hundred and why the line underneath says so.
 */
export function TrafficByCountry({ rows }: { rows: { code: string; percent: number }[] }) {
    if (rows.length === 0) return null;

    const shown = rows.reduce((total, row) => total + row.percent, 0);

    return (
        <section aria-labelledby="site-countries">
            <h3 id="site-countries" className="mb-3 font-sora text-md font-semibold text-ink-900">
                Traffic by country
            </h3>

            <ul className="flex flex-col gap-2">
                {rows.map((row) => (
                    <li key={row.code} className="flex items-center gap-3">
                        <span className="flex w-32 shrink-0 items-center gap-1.5 text-sm text-ink-700">
                            <span aria-hidden="true">{flagFor(row.code)}</span>
                            <span className="truncate">{countryName(row.code)}</span>
                        </span>

                        <span className="h-2 min-w-0 flex-1 rounded-pill bg-sunken">
                            <span
                                className="block h-full rounded-pill bg-brand"
                                style={{ width: `${row.percent}%` }}
                            />
                        </span>

                        <span className="num w-12 shrink-0 text-right text-sm text-ink-900">{row.percent}%</span>
                    </li>
                ))}
            </ul>

            {shown < 99 && (
                <p className="num mt-2 text-xs text-ink-500">
                    The remaining {Math.round(100 - shown)}% comes from elsewhere.
                </p>
            )}
        </section>
    );
}

/** What the publisher will and will not do, as a definition list. */
export function PlacementTermsList({ terms }: { terms: PlacementTerms }) {
    const rows: [string, string][] = [
        ['Publication time', terms.publicationLabel],
        ['Link type', terms.linkType === 'dofollow' ? 'Dofollow' : 'Nofollow'],
        [
            'Links per post',
            terms.linksAllowed === terms.maxLinks
                ? `${terms.linksAllowed}`
                : `${terms.linksAllowed}, up to ${terms.maxLinks}`,
        ],
        ['Minimum words', terms.minWords.toLocaleString('en-US')],
        ['Marked as sponsored', terms.marksSponsored ? 'Yes' : 'No'],
        [
            'Link guaranteed for',
            // Zero is not missing data. A publisher who offers no guarantee has
            // answered the question, and printing a dash would read as though
            // nobody asked.
            terms.linkGuaranteeMonths === 0
                ? 'No guarantee'
                : terms.linkGuaranteeMonths >= 24
                  ? `${Math.floor(terms.linkGuaranteeMonths / 12)} years`
                  : `${terms.linkGuaranteeMonths} months`,
        ],
        ['Images', terms.acceptsImages ? 'Accepted' : 'Not accepted'],
        ['Embedded media', terms.acceptsEmbeds ? 'Accepted' : 'Not accepted'],
    ];

    return (
        <section aria-labelledby="site-terms">
            <h3 id="site-terms" className="mb-3 font-sora text-md font-semibold text-ink-900">
                Placement terms
            </h3>

            <dl className="divide-y divide-subtle rounded-card border border-subtle">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex items-baseline justify-between gap-4 px-3 py-2">
                        <dt className="text-sm text-ink-500">{label}</dt>
                        <dd className="text-right text-base text-ink-900">{value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

/**
 * Which sensitive topics this publisher takes, and which it refuses.
 *
 * Both halves are shown. A list of only what is accepted leaves a buyer unable
 * to tell "refuses gambling" from "nobody asked about gambling", and those are
 * opposite answers to the only question that matters here.
 */
export function TopicChips({ topics }: { topics: CatalogSiteDetail['topics'] }) {
    if (topics.accepted.length === 0 && topics.refused.length === 0) return null;

    return (
        <section aria-labelledby="site-topics">
            <h3 id="site-topics" className="mb-3 font-sora text-md font-semibold text-ink-900">
                Accepted topics
            </h3>

            <ul className="flex flex-wrap gap-1.5">
                {topics.accepted.map((topic) => (
                    <li
                        key={topic.slug}
                        className="rounded-pill border border-teal bg-teal-subtle px-2.5 py-1 text-xs font-medium text-success"
                    >
                        {topic.name}
                    </li>
                ))}

                {topics.refused.map((topic) => (
                    <li
                        key={topic.slug}
                        className="rounded-pill border border-subtle px-2.5 py-1 text-xs text-ink-500 line-through"
                    >
                        {topic.name}
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** The publisher's brief, as stored. */
export function ContentGuidelines({ guidelines }: { guidelines: string | null }) {
    if (!guidelines) return null;

    return (
        <section aria-labelledby="site-guidelines">
            <h3 id="site-guidelines" className="mb-2 font-sora text-md font-semibold text-ink-900">
                Content guidelines
            </h3>

            {/* Sanitised on write, by the same path the project brief takes.
                Rendered as stored HTML so a publisher's list stays a list. */}
            <div
                className="prose-publinza text-base text-ink-700"
                dangerouslySetInnerHTML={{ __html: guidelines }}
            />
        </section>
    );
}

export function SamplePosts({ posts }: { posts: CatalogSiteDetail['samplePosts'] }) {
    if (posts.length === 0) return null;

    return (
        <section aria-labelledby="site-samples">
            <h3 id="site-samples" className="mb-3 font-sora text-md font-semibold text-ink-900">
                Sample published posts
            </h3>

            <ul className="flex flex-col gap-2">
                {posts.map((post) => (
                    <li key={post.url}>
                        <a
                            href={post.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="block rounded-card border border-subtle p-3 hover:border-strong"
                        >
                            <p className="text-base font-medium text-ink-900">{post.title}</p>
                            <p className="mt-0.5 flex items-center gap-2 text-xs text-ink-500">
                                {post.publishedAt && (
                                    <span>
                                        {new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(
                                            new Date(post.publishedAt),
                                        )}
                                    </span>
                                )}
                                <span className="truncate text-brand">{post.url}</span>
                            </p>
                        </a>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * What this advertiser has already published here.
 *
 * Shown only when there is something to show, and tinted so it is findable by
 * scrolling rather than by reading: for a returning buyer this outranks every
 * metric above it. A placement that went live and is still live is the one
 * thing on this screen that is not somebody's estimate.
 */
export function YourHistory({ placements }: { placements: SitePlacement[] }) {
    if (placements.length === 0) return null;

    return (
        <section aria-labelledby="site-history" className="rounded-card border border-brand bg-brand-subtle p-4">
            <h3 id="site-history" className="mb-3 font-sora text-md font-semibold text-ink-900">
                Your history with this site
            </h3>

            <ul className="flex flex-col gap-2">
                {placements.map((placement) => (
                    <li key={placement.id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <Badge status={placement.badge as StatusKey} label={placement.statusLabel} />

                        <span className="text-base text-ink-900">{placement.project}</span>

                        {placement.anchorText && (
                            <span className="truncate text-sm text-ink-500">“{placement.anchorText}”</span>
                        )}

                        {placement.publishedUrl ? (
                            <a
                                href={placement.publishedUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={cn('ml-auto shrink-0 text-sm font-medium text-brand hover:underline')}
                            >
                                View the post
                            </a>
                        ) : (
                            // Not published yet, so there is no live URL to
                            // point at — the post itself is the next best
                            // thing, and it is where its status came from.
                            <Link
                                href={`/posts/${placement.id}`}
                                className="ml-auto shrink-0 text-sm text-ink-500 hover:underline"
                            >
                                Open the order
                            </Link>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

function countryName(code: string): string {
    try {
        return REGIONS.of(code) ?? code;
    } catch {
        return code;
    }
}
