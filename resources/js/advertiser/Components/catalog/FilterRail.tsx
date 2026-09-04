import { useEffect, useState } from 'react';
import { Checkbox, Input, RangeSlider, SearchIcon, Switch } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { CatalogFacets, CatalogFilterState, CatalogOptions, CatalogRangeSet } from '@shared/types/catalog';
import { AppliedBadge, FilterSection } from './FilterSection';
import { FacetList } from './FacetList';
import { formatRange, parseRange } from './useCatalogFilters';

interface Props {
    filters: CatalogFilterState;
    facets: CatalogFacets;
    ranges: CatalogRangeSet;
    options: CatalogOptions;
    /** Buying mode only: "not yet used in this project" needs a project. */
    hasProject: boolean;
    apply: (patch: Partial<CatalogFilterState>) => void;
    applyDebounced: (patch: Partial<CatalogFilterState>) => void;
}

/**
 * The fourteen filter groups.
 *
 * Every one writes straight to the URL through `apply`, so the rail has no
 * state of its own except the two text drafts and whichever sections are open.
 * Sliders are the exception that proves it: they hold a local pair while a
 * handle is being dragged, because sending a request per pixel of travel would
 * be a hundred requests for one decision, and commit it on release.
 */
export function FilterRail({ filters, facets, ranges, options, hasProject, apply, applyDebounced }: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const [domain, setDomain] = useState(filters.domain ?? '');

    // The server is the source of truth; adopt what it echoed back — including
    // a value it normalised, which is exactly when the box and the results
    // would otherwise disagree about what was searched for.
    useEffect(() => setSearch(filters.q ?? ''), [filters.q]);
    useEffect(() => setDomain(filters.domain ?? ''), [filters.domain]);

    const priceRange: [number, number] = [
        Math.floor(ranges.price[0] / 100),
        Math.max(1, Math.ceil(ranges.price[1] / 100)),
    ];

    return (
        <div className="flex flex-col">
            <FilterSection id="search" title="Search" defaultOpen badge={<AppliedBadge count={filters.q ? 1 : 0} />}>
                <Input
                    label="Search sites"
                    hideLabel
                    type="search"
                    placeholder="Domain, title or description"
                    leadingIcon={<SearchIcon size={14} />}
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        applyDebounced({ q: event.target.value || undefined });
                    }}
                />
            </FilterSection>

            <FilterSection id="domain" title="Domain" badge={<AppliedBadge count={filters.domain ? 1 : 0} />}>
                <Input
                    label="Exact domain"
                    hideLabel
                    placeholder="example.com"
                    // A separate field from search on purpose: a buyer who
                    // already knows the site wants that site, and a fuzzy
                    // engine match hands back a dozen near misses instead.
                    hint="Finds one site by its exact address."
                    value={domain}
                    onChange={(event) => {
                        setDomain(event.target.value);
                        applyDebounced({ domain: event.target.value || undefined });
                    }}
                />
            </FilterSection>

            <FilterSection
                id="category"
                title="Category"
                defaultOpen
                badge={<AppliedBadge count={filters.categories?.length ?? 0} />}
            >
                <FacetList
                    label="categories"
                    options={facets.categories}
                    selected={filters.categories ?? []}
                    onChange={(categories) => apply({ categories })}
                />
            </FilterSection>

            <FilterSection
                id="country"
                title="Website country"
                badge={<AppliedBadge count={filters.countries?.length ?? 0} />}
            >
                <FacetList
                    label="countries"
                    options={facets.countries}
                    selected={filters.countries ?? []}
                    onChange={(countries) => apply({ countries })}
                    showFlags
                />
            </FilterSection>

            <FilterSection
                id="language"
                title="Website language"
                badge={<AppliedBadge count={filters.languages?.length ?? 0} />}
            >
                <FacetList
                    label="languages"
                    options={facets.languages}
                    selected={filters.languages ?? []}
                    onChange={(languages) => apply({ languages })}
                />
            </FilterSection>

            <FilterSection
                id="price"
                title="Price (USD)"
                defaultOpen
                badge={<AppliedBadge count={filters.price ? 1 : 0} />}
            >
                <DeferredRange
                    label="Price"
                    min={priceRange[0]}
                    max={priceRange[1]}
                    value={parseRange(filters.price, priceRange)}
                    format={(v) => money(v * 100).replace(/\.00$/, '')}
                    histogram={facets.priceHistogram}
                    onCommit={(next) =>
                        apply({
                            // A range covering everything is not a filter. It
                            // leaves the URL, so a stored link stays honest
                            // about what was actually narrowed.
                            price: next[0] <= priceRange[0] && next[1] >= priceRange[1] ? undefined : formatRange(next),
                        })
                    }
                />
            </FilterSection>

            <FilterSection
                id="traffic"
                title="Monthly traffic"
                badge={<AppliedBadge count={filters.traffic ? 1 : 0} />}
            >
                <DeferredRange
                    label="Monthly traffic"
                    min={ranges.traffic[0]}
                    max={Math.max(1, ranges.traffic[1])}
                    value={parseRange(filters.traffic, [ranges.traffic[0], ranges.traffic[1]])}
                    format={shorthand}
                    // Log-scaled: the interesting decisions are between a
                    // thousand and a hundred thousand, and on a linear track
                    // those all sit in the first tenth of the width.
                    scale="log"
                    onCommit={(next) =>
                        apply({
                            traffic:
                                next[0] <= ranges.traffic[0] && next[1] >= ranges.traffic[1]
                                    ? undefined
                                    : formatRange(next),
                        })
                    }
                />
            </FilterSection>

            <FilterSection id="dr" title="Ahrefs DR" badge={<AppliedBadge count={filters.dr ? 1 : 0} />}>
                <DeferredRange
                    label="Domain Rating"
                    min={0}
                    max={100}
                    value={parseRange(filters.dr, [0, 100])}
                    onCommit={(next) => apply({ dr: next[0] <= 0 && next[1] >= 100 ? undefined : formatRange(next) })}
                />
            </FilterSection>

            <FilterSection id="da" title="Moz DA" badge={<AppliedBadge count={filters.da ? 1 : 0} />}>
                <DeferredRange
                    label="Domain Authority"
                    min={0}
                    max={100}
                    value={parseRange(filters.da, [0, 100])}
                    onCommit={(next) => apply({ da: next[0] <= 0 && next[1] >= 100 ? undefined : formatRange(next) })}
                />
            </FilterSection>

            <FilterSection
                id="spam"
                title="Spam score"
                badge={<AppliedBadge count={filters.max_spam === undefined ? 0 : 1} />}
            >
                <MaxOnlySlider
                    value={filters.max_spam ?? 100}
                    onCommit={(value) => apply({ max_spam: value >= 100 ? undefined : value })}
                />
            </FilterSection>

            <FilterSection
                id="speed"
                title="Publication time"
                badge={<AppliedBadge count={filters.speed?.length ?? 0} />}
            >
                <ul className="flex flex-col gap-1.5">
                    {options.speeds.map((speed) => (
                        <li key={speed.value}>
                            <Checkbox
                                label={speed.label}
                                checked={filters.speed?.includes(speed.value) ?? false}
                                onChange={() => {
                                    const current = filters.speed ?? [];

                                    apply({
                                        speed: current.includes(speed.value)
                                            ? current.filter((v) => v !== speed.value)
                                            : [...current, speed.value],
                                    });
                                }}
                            />
                        </li>
                    ))}
                </ul>
            </FilterSection>

            <FilterSection id="link" title="Link type" badge={<AppliedBadge count={filters.link_type ? 1 : 0} />}>
                <ul className="flex flex-col gap-1.5">
                    {(['dofollow', 'nofollow'] as const).map((type) => (
                        <li key={type}>
                            <Checkbox
                                label={type === 'dofollow' ? 'Dofollow' : 'Nofollow'}
                                checked={filters.link_type === type}
                                // Exclusive, but not a radio group: a radio you
                                // cannot untick traps someone who ticked it to
                                // see what happened.
                                onChange={() => apply({ link_type: filters.link_type === type ? undefined : type })}
                            />
                        </li>
                    ))}
                </ul>
            </FilterSection>

            <FilterSection
                id="topics"
                title="Sensitive topics accepted"
                badge={<AppliedBadge count={filters.topics?.length ?? 0} />}
            >
                <div className="flex flex-wrap gap-1.5">
                    {options.topics.map((topic) => {
                        const on = filters.topics?.includes(topic.slug) ?? false;

                        return (
                            <button
                                key={topic.slug}
                                type="button"
                                aria-pressed={on}
                                onClick={() => {
                                    const current = filters.topics ?? [];

                                    apply({
                                        topics: on
                                            ? current.filter((slug) => slug !== topic.slug)
                                            : [...current, topic.slug],
                                    });
                                }}
                                className={
                                    on
                                        ? 'rounded-pill border border-brand bg-brand-subtle px-2.5 py-1 text-xs font-medium text-brand'
                                        : 'rounded-pill border border-subtle bg-card px-2.5 py-1 text-xs text-ink-700 hover:border-strong'
                                }
                            >
                                {topic.name}
                            </button>
                        );
                    })}
                </div>

                <p className="mt-2 text-xs text-ink-500">Shows only sites that accept every topic you pick.</p>
            </FilterSection>

            <FilterSection id="quick" title="Quick filters" defaultOpen>
                <ul className="flex flex-col gap-3">
                    <li>
                        <Switch
                            label="Not in my blacklist"
                            checked={!filters.show_blacklisted}
                            onCheckedChange={() =>
                                apply({ show_blacklisted: filters.show_blacklisted ? undefined : true })
                            }
                        />
                    </li>
                    <li>
                        <Switch
                            label="Only my favorites"
                            checked={filters.favorites ?? false}
                            onCheckedChange={() => apply({ favorites: filters.favorites ? undefined : true })}
                        />
                    </li>
                    <li>
                        <Switch
                            label="Not yet used in this project"
                            // Meaningless without a project, and a toggle that
                            // silently does nothing is worse than one that says
                            // why it cannot.
                            disabled={!hasProject}
                            hint={hasProject ? undefined : 'Choose a project first.'}
                            checked={filters.unused ?? false}
                            onCheckedChange={() => apply({ unused: filters.unused ? undefined : true })}
                        />
                    </li>
                    <li>
                        <Switch
                            label="Has traffic data"
                            checked={filters.has_traffic ?? false}
                            onCheckedChange={() => apply({ has_traffic: filters.has_traffic ? undefined : true })}
                        />
                    </li>
                </ul>
            </FilterSection>
        </div>
    );
}

/**
 * A range that reports on release rather than on every pixel of travel.
 *
 * Dragging a handle across a track fires a change per pixel. Each one is a
 * request, a re-render of two hundred rows and a new set of facet counts, so
 * the handle holds a local value while it moves and commits once — which is
 * also the only way the number under the cursor keeps up with the cursor.
 */
function DeferredRange({
    label,
    min,
    max,
    value,
    format,
    histogram,
    scale,
    onCommit,
}: {
    label: string;
    min: number;
    max: number;
    value: [number, number];
    format?: (value: number) => string;
    histogram?: number[];
    scale?: 'linear' | 'log';
    onCommit: (value: [number, number]) => void;
}) {
    const [draft, setDraft] = useState<[number, number]>(value);
    const [low, high] = value;

    // Adopt whatever the server applied. Keyed on the two numbers rather than
    // the array: the prop is rebuilt on every render, so depending on the
    // array itself would reset the draft mid-drag on every keystroke elsewhere.
    useEffect(() => setDraft([low, high]), [low, high]);

    return (
        <div
            onPointerUp={() => onCommit(draft)}
            onKeyUp={(event) => {
                if (event.key.startsWith('Arrow') || event.key === 'Home' || event.key === 'End') onCommit(draft);
            }}
            onBlur={() => onCommit(draft)}
        >
            <RangeSlider
                label={label}
                min={min}
                max={max}
                value={draft}
                onChange={setDraft}
                format={format}
                histogram={histogram}
                scale={scale}
            />
        </div>
    );
}

/**
 * Spam score: a ceiling, not a range.
 *
 * Nobody filters for a *minimum* amount of spam, so the low handle would be a
 * control with one correct position. It defaults to 100 — everything — because
 * a quality filter nobody asked for that silently hides sites is the kind of
 * default that gets blamed on the inventory.
 */
function MaxOnlySlider({ value, onCommit }: { value: number; onCommit: (value: number) => void }) {
    const [draft, setDraft] = useState(value);

    useEffect(() => setDraft(value), [value]);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between">
                <label htmlFor="catalog-spam" className="text-sm font-medium text-ink-700">
                    At most
                </label>
                <span className="num text-sm text-ink-500">{draft}</span>
            </div>

            <input
                id="catalog-spam"
                type="range"
                min={0}
                max={100}
                value={draft}
                onChange={(event) => setDraft(Number(event.target.value))}
                onPointerUp={() => onCommit(draft)}
                onKeyUp={() => onCommit(draft)}
                onBlur={() => onCommit(draft)}
                className="h-6 w-full accent-[color:var(--brand-blue)]"
            />

            <p className="text-xs text-ink-500">Lower is better. Above 30 is worth a second look.</p>
        </div>
    );
}

/** 1K, 10K, 100K, 1M — the shorthand the traffic handles read in. */
function shorthand(value: number): string {
    if (value >= 1_000_000) return `${Math.round(value / 100_000) / 10}M`;
    if (value >= 1_000) return `${Math.round(value / 100) / 10}K`;

    return String(value);
}
