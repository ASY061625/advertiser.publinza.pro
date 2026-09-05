import { useEffect, useMemo, useRef, useState } from 'react';
import { ExternalLinkIcon, Input, QuantBar, SearchIcon, Select, Skeleton } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { compactNumber, money } from '@shared/lib/format';
import type { CatalogRangeSet, CatalogRow } from '@shared/types/catalog';
import type { PostWizardState, WizardResults, WizardWebsite } from '@shared/types/postWizard';

interface Props {
    state: PostWizardState;
    patch: (changes: Partial<PostWizardState>) => void;
    categories: { id: number; name: string }[];
    chosen: WizardWebsite | null;
}

/** The four filters this step offers, beside search. */
const PRICE_BANDS = [
    { value: '', label: 'Any price' },
    { value: '0-10000', label: 'Under $100' },
    { value: '10000-30000', label: '$100 – $300' },
    { value: '30000-100000', label: '$300 – $1,000' },
    { value: '100000-99999999', label: 'Over $1,000' },
];

const TRAFFIC_BANDS = [
    { value: '', label: 'Any traffic' },
    { value: '1000-99999999', label: '1K+' },
    { value: '10000-99999999', label: '10K+' },
    { value: '50000-99999999', label: '50K+' },
    { value: '250000-99999999', label: '250K+' },
];

const DR_BANDS = [
    { value: '', label: 'Any DR' },
    { value: '20-100', label: 'DR 20+' },
    { value: '40-100', label: 'DR 40+' },
    { value: '60-100', label: 'DR 60+' },
];

/**
 * The catalog, at picker size.
 *
 * Four filters and a search box, not fourteen. This is a step in a modal, not
 * the catalog — somebody who needs the other ten filters needs the catalog
 * itself, and the link at the bottom hands them over with everything they have
 * typed so far already in the query string.
 *
 * The list runs the same SearchCatalog the full catalog runs, on the same
 * filters parsed the same way, so a site found here is findable there.
 */
export function StepWebsite({ state, patch, categories, chosen }: Props) {
    const [results, setResults] = useState<WizardResults | null>(null);
    const [loading, setLoading] = useState(true);

    // Each fetch carries a sequence number so a slow "DR 60+" cannot land after
    // a fast "any DR" and repaint the list with the wrong filter's answer.
    const sequence = useRef(0);

    const query = useMemo(() => {
        const params = new URLSearchParams();

        if (state.projectId !== '') params.set('project', state.projectId);
        if (state.search !== '') params.set('q', state.search);
        if (state.categoryId !== '') params.append('categories[]', state.categoryId);
        if (state.price !== '') params.set('price', state.price);
        if (state.traffic !== '') params.set('traffic', state.traffic);
        if (state.dr !== '') params.set('dr', state.dr);

        return params.toString();
    }, [state.categoryId, state.dr, state.price, state.projectId, state.search, state.traffic]);

    useEffect(() => {
        const id = ++sequence.current;
        setLoading(true);

        // Typing is debounced; the selects are not, because a select cannot be
        // half-changed and waiting on one feels broken.
        const delay = state.search === '' ? 0 : 300;
        const timer = window.setTimeout(() => {
            void fetch(`/posts/wizard/websites?${query}`, { headers: { Accept: 'application/json' } })
                .then((response) => (response.ok ? response.json() : null))
                .then((body: WizardResults | null) => {
                    if (id !== sequence.current) return;

                    setResults(body);

                    // The project's category, surfaced into the control that
                    // shows it. Left implicit on the server it would silently
                    // stop applying the moment anything else was filtered —
                    // adding a filter would widen the list, which is the
                    // opposite of what a filter does.
                    const seeded = body?.query.categories;

                    if (state.categoryId === '' && Array.isArray(seeded) && seeded.length === 1) {
                        patch({ categoryId: String(seeded[0]) });
                    }
                })
                .catch(() => undefined)
                .finally(() => {
                    if (id === sequence.current) setLoading(false);
                });
        }, delay);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const sites = results?.sites ?? [];
    const ranges = results?.ranges ?? null;

    return (
        <div className="flex flex-col gap-3">
            <Input
                label="Search sites"
                hideLabel
                leadingIcon={<SearchIcon size={16} />}
                value={state.search}
                onChange={(event) => patch({ search: event.target.value })}
                placeholder="Domain, title or description"
            />

            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <Select
                    label="Category"
                    hideLabel
                    value={state.categoryId}
                    onChange={(event) => patch({ categoryId: event.target.value })}
                    options={[
                        { value: '', label: 'Any category' },
                        ...categories.map((category) => ({
                            value: String(category.id),
                            label: category.name,
                        })),
                    ]}
                />
                <Select
                    label="Price"
                    hideLabel
                    value={state.price}
                    onChange={(event) => patch({ price: event.target.value })}
                    options={PRICE_BANDS}
                />
                <Select
                    label="Traffic"
                    hideLabel
                    value={state.traffic}
                    onChange={(event) => patch({ traffic: event.target.value })}
                    options={TRAFFIC_BANDS}
                />
                <Select
                    label="Domain Rating"
                    hideLabel
                    value={state.dr}
                    onChange={(event) => patch({ dr: event.target.value })}
                    options={DR_BANDS}
                />
            </div>

            {chosen !== null && <SummaryStrip site={chosen} serviceType={state.serviceType} />}

            <fieldset>
                <legend className="sr-only">Choose a website</legend>

                <div className="max-h-[19rem] overflow-y-auto rounded-card border border-subtle">
                    {loading && sites.length === 0 ? (
                        <ul className="divide-y divide-subtle">
                            {Array.from({ length: 4 }, (_, row) => (
                                <li key={row} className="p-3">
                                    <Skeleton className="h-9 w-full" />
                                </li>
                            ))}
                        </ul>
                    ) : sites.length === 0 ? (
                        <p className="p-6 text-center text-base text-ink-500">
                            No site matches that. Widen a filter, or open the full catalog below.
                        </p>
                    ) : (
                        <ul className="divide-y divide-subtle">
                            {sites.map((site) => (
                                <SiteRow
                                    key={site.id}
                                    site={site}
                                    ranges={ranges}
                                    selected={state.websiteId === String(site.id)}
                                    onSelect={() =>
                                        patch({
                                            websiteId: String(site.id),
                                            // The slug, because every route
                                            // that acts on a site takes one —
                                            // the id is only for the submit.
                                            websiteSlug: site.slug,
                                            // The chosen site may not offer the
                                            // service the last one did, so the
                                            // service resets with it.
                                            serviceType: 'article_placement',
                                            express: false,
                                        })
                                    }
                                />
                            ))}
                        </ul>
                    )}
                </div>
            </fieldset>

            <p className="flex flex-wrap items-center justify-between gap-2 text-sm text-ink-500">
                <span className="num">
                    {results === null
                        ? ' '
                        : `Showing ${sites.length} of ${results.total} ${results.total === 1 ? 'site' : 'sites'}`}
                </span>

                {/* The hand-off carries the query the picker is actually
                    showing, so the catalog opens on the same results rather
                    than on everything. */}
                <a
                    href={`/catalog?${query}`}
                    className="inline-flex items-center gap-1 font-medium text-brand hover:underline"
                >
                    Open the full catalog
                    <ExternalLinkIcon size={12} />
                </a>
            </p>
        </div>
    );
}

function SiteRow({
    site,
    ranges,
    selected,
    onSelect,
}: {
    site: CatalogRow;
    ranges: CatalogRangeSet | null;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <li>
            <label
                className={cn(
                    'flex cursor-pointer items-center gap-3 p-3',
                    selected ? 'bg-brand-subtle' : 'hover:bg-sunken',
                )}
            >
                <input
                    type="radio"
                    name="wizard-website"
                    checked={selected}
                    onChange={onSelect}
                    className="size-4 shrink-0 accent-[color:var(--brand-blue)]"
                />

                <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium text-ink-900">{site.domain}</span>
                    <span className="block truncate text-sm text-ink-500">
                        {site.category} · {site.publicationLabel}
                    </span>
                </span>

                <span className="num hidden w-20 shrink-0 text-sm text-ink-700 sm:block">
                    {site.traffic === null ? '—' : compactNumber(site.traffic)}
                </span>

                <span className="hidden w-16 shrink-0 sm:block">
                    {site.domainRating === null || ranges === null ? (
                        <span className="num text-sm text-ink-500">—</span>
                    ) : (
                        <QuantBar value={site.domainRating} range={ranges.domainRating} format={String} />
                    )}
                </span>

                <span className="num w-20 shrink-0 text-right font-medium text-ink-900">
                    {money(site.priceCents)}
                </span>
            </label>
        </li>
    );
}

/** What was chosen, and the terms that come with it. */
function SummaryStrip({ site, serviceType }: { site: WizardWebsite; serviceType: string }) {
    const service = site.services.find((entry) => entry.type === serviceType) ?? site.services[0];

    return (
        <dl className="flex flex-wrap items-baseline gap-x-5 gap-y-1 rounded-card border border-brand bg-brand-subtle px-4 py-3">
            <div className="flex items-baseline gap-2">
                <dt className="sr-only">Site</dt>
                <dd className="font-sora text-base font-semibold text-ink-900">{site.domain}</dd>
            </div>

            <div className="flex items-baseline gap-2">
                <dt className="text-sm text-ink-500">Price</dt>
                <dd className="num font-medium text-ink-900">{money(service?.priceCents ?? 0)}</dd>
            </div>

            <div className="flex items-baseline gap-2">
                <dt className="text-sm text-ink-500">Published in</dt>
                <dd className="num text-ink-900">{hours(site.publicationHours)}</dd>
            </div>

            <div className="flex items-baseline gap-2">
                <dt className="text-sm text-ink-500">Links</dt>
                <dd className="text-ink-900">
                    {site.linksAllowed === site.maxLinks
                        ? site.linksAllowed
                        : `${site.linksAllowed}–${site.maxLinks}`}
                    , {site.linkType === 'dofollow' ? 'dofollow' : 'nofollow'}
                </dd>
            </div>
        </dl>
    );
}

function hours(value: number): string {
    if (value <= 0) return 'Not stated';
    if (value < 48) return `${value} hours`;

    return `${Math.round(value / 24)} days`;
}
