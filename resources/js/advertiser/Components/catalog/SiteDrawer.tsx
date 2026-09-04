import { useEffect, useState } from 'react';
import { Alert, Drawer, QuantBar, Skeleton } from '@shared/ui';
import { money, number } from '@shared/lib/format';
import type { CatalogRangeSet, CatalogRow, CatalogSiteDetail } from '@shared/types/catalog';
import { flagFor } from './flags';
import { SiteActions } from './SiteActions';

interface Props {
    /** The row that was clicked, or null when the drawer is shut. */
    site: CatalogRow | null;
    projectId: number | null;
    ranges: CatalogRangeSet;
    onClose: () => void;
}

/**
 * One site, opened from a row.
 *
 * The row is already on the client, so the drawer paints its headline
 * immediately and fills in the rest — guidelines, the service list, the sample
 * post — when the fetch lands. Waiting for the whole thing before showing
 * anything would put a spinner over facts the page already had.
 */
export function SiteDrawer({ site, projectId, ranges, onClose }: Props) {
    const [detail, setDetail] = useState<CatalogSiteDetail | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (site === null) return;

        setDetail(null);
        setFailed(false);

        const controller = new AbortController();
        const query = projectId === null ? '' : `?project=${projectId}`;

        fetch(`/catalog/${site.slug}${query}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
            .then((data: CatalogSiteDetail) => setDetail(data))
            .catch((reason: unknown) => {
                if (reason instanceof DOMException && reason.name === 'AbortError') return;

                setFailed(true);
            });

        return () => controller.abort();
    }, [projectId, site]);

    if (site === null) return null;

    return (
        <Drawer
            open
            onClose={onClose}
            title={site.domain}
            description={site.title}
            footer={
                <SiteActions site={detail ?? site} projectId={projectId} onOpenDetail={() => undefined} size="md" />
            }
        >
            <div className="flex flex-col gap-5">
                {site.warnings.length > 0 && (
                    <Alert tone="warning" title="Worth checking against this project">
                        <ul className="flex list-inside list-disc flex-col gap-0.5">
                            {site.warnings.map((warning) => (
                                <li key={warning.kind}>{warning.message}</li>
                            ))}
                        </ul>
                    </Alert>
                )}

                <dl className="grid grid-cols-2 gap-3">
                    <Fact label="Category" value={site.category} />
                    <Fact label="Language" value={site.language.name} />
                    <Fact
                        label="Country"
                        value={`${site.country.code ? `${flagFor(site.country.code)} ` : ''}${site.country.name}`}
                    />
                    <Fact label="Published in" value={site.publicationLabel} />
                    <Fact label="Link type" value={site.linkType === 'dofollow' ? 'Dofollow' : 'Nofollow'} />
                    <Fact
                        label="Spam score"
                        value={site.spamScore === null ? 'Not measured' : String(site.spamScore)}
                    />
                </dl>

                <section>
                    <h3 className="mb-2 font-sora text-sm font-semibold text-ink-900">Metrics</h3>
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

                {failed ? (
                    <p className="text-base text-danger">
                        The rest of this site’s details could not be loaded. Close the drawer and try again.
                    </p>
                ) : detail === null ? (
                    <div className="flex flex-col gap-2">
                        {Array.from({ length: 5 }, (_, index) => (
                            <Skeleton key={index} className="h-4 w-full" />
                        ))}
                    </div>
                ) : (
                    <>
                        <section>
                            <h3 className="mb-2 font-sora text-sm font-semibold text-ink-900">Services</h3>
                            <ul className="flex flex-col gap-1.5">
                                {detail.services.map((service) => (
                                    <li key={service.type} className="flex items-baseline justify-between gap-3">
                                        <span className="text-base text-ink-700">{service.label}</span>
                                        <span className="num text-base text-ink-900">
                                            {money(service.priceCents)}
                                            {service.writingFeeCents > 0 && (
                                                <span className="ml-1 text-xs text-ink-500">
                                                    +{money(service.writingFeeCents).replace(/\.00$/, '')} writing
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>

                        <dl className="grid grid-cols-2 gap-3">
                            <Fact label="Minimum words" value={number(detail.minWords)} />
                            <Fact label="Links allowed" value={`${detail.linksAllowed} of ${detail.maxLinks} max`} />
                        </dl>

                        {detail.description && (
                            <section>
                                <h3 className="mb-1 font-sora text-sm font-semibold text-ink-900">About the site</h3>
                                <p className="text-base text-ink-700">{detail.description}</p>
                            </section>
                        )}

                        {detail.guidelines && (
                            <section>
                                <h3 className="mb-1 font-sora text-sm font-semibold text-ink-900">
                                    Publisher guidelines
                                </h3>
                                <p className="whitespace-pre-line text-base text-ink-700">{detail.guidelines}</p>
                            </section>
                        )}

                        {detail.sampleUrl && (
                            <a
                                href={detail.sampleUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-base font-medium text-brand hover:underline"
                            >
                                See an example post
                            </a>
                        )}
                    </>
                )}
            </div>
        </Drawer>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-ink-500">{label}</dt>
            <dd className="truncate text-base text-ink-900">{value}</dd>
        </div>
    );
}
