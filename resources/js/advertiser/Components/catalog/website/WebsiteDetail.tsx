import { Alert, QuantBar } from '@shared/ui';
import type { CatalogRangeSet, CatalogSiteDetail } from '@shared/types/catalog';
import { MetricGrid } from './MetricGrid';
import {
    ContentGuidelines,
    PlacementTermsList,
    SamplePosts,
    TopicChips,
    TrafficByCountry,
    YourHistory,
} from './SiteSections';

interface Props {
    site: CatalogSiteDetail;
    ranges: CatalogRangeSet;
}

/**
 * Everything under the header, in the order a buyer decides in.
 *
 * The same body serves the drawer and the standalone page. They are the same
 * view at two sizes — the drawer is how it is read while scanning results, the
 * page is how it is read when somebody sends you a link — and two
 * implementations would drift until one of them was missing a section.
 *
 * The order is evidence, then terms, then proof. Metrics first because that is
 * what a buyer filtered on and what they are checking the filter against;
 * placement terms next because a site with the right numbers and the wrong
 * link policy is a site to leave; and the samples and the buyer's own history
 * last, as the two things on this screen that are not somebody's estimate.
 */
export function WebsiteDetail({ site, ranges }: Props) {
    return (
        <div className="flex flex-col gap-6">
            {site.warnings.length > 0 && (
                <Alert tone="warning" title="Worth checking against this project">
                    <ul className="flex list-inside list-disc flex-col gap-0.5">
                        {site.warnings.map((warning) => (
                            <li key={warning.kind}>{warning.message}</li>
                        ))}
                    </ul>
                </Alert>
            )}

            {site.description && <p className="text-base text-ink-700">{site.description}</p>}

            {/* Three of the nine measures again, this time against the whole
                catalog. The tiles say what the number is; these say whether it
                is a good one, which is the question a tile cannot answer on its
                own. */}
            <section aria-labelledby="site-standing">
                <h3 id="site-standing" className="mb-3 font-sora text-md font-semibold text-ink-900">
                    How it compares
                </h3>

                <dl className="flex flex-col gap-2">
                    {(
                        [
                            ['Monthly traffic', site.traffic, ranges.traffic, false],
                            ['Domain Rating', site.domainRating, ranges.domainRating, true],
                            ['Domain Authority', site.domainAuthority, ranges.domainAuthority, true],
                        ] as const
                    ).map(([label, value, range, exact]) => (
                        <div key={label} className="flex items-center gap-3">
                            <dt className="w-36 shrink-0 text-sm text-ink-500">{label}</dt>
                            <dd className="min-w-0 flex-1">
                                {value === null ? (
                                    <span className="text-sm text-ink-500">Not measured</span>
                                ) : (
                                    <QuantBar
                                        value={value}
                                        range={range}
                                        format={exact ? String : undefined}
                                        className="!items-start"
                                    />
                                )}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>

            <MetricGrid tiles={site.metrics} />

            <TrafficByCountry rows={site.trafficByCountry} />

            <PlacementTermsList terms={site.terms} />

            <TopicChips topics={site.topics} />

            <ContentGuidelines guidelines={site.guidelines} />

            <SamplePosts posts={site.samplePosts} />

            <YourHistory placements={site.myHistory} />
        </div>
    );
}
