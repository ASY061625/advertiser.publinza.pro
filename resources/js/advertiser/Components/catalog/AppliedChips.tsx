import { XIcon } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { CatalogFacets, CatalogFilterState, CatalogOptions } from '@shared/types/catalog';
import { flagFor } from './flags';

interface Props {
    filters: CatalogFilterState;
    facets: CatalogFacets;
    options: CatalogOptions;
    apply: (patch: Partial<CatalogFilterState>) => void;
    clear: () => void;
}

interface Chip {
    key: string;
    label: string;
    remove: Partial<CatalogFilterState>;
}

/**
 * Every applied filter, above the results, each removable on its own.
 *
 * The rail can be collapsed, scrolled past, or replaced by a button on a narrow
 * screen, so it cannot be the only place a filter is visible. These chips are
 * the answer to "why am I only seeing eleven sites" without opening anything —
 * and each one removes exactly itself, which the rail cannot do for a section
 * that is scrolled out of view.
 */
export function AppliedChips({ filters, facets, options, apply, clear }: Props) {
    const chips = build(filters, facets, options);

    if (chips.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-2">
            {chips.map((chip) => (
                <button
                    key={chip.key}
                    type="button"
                    onClick={() => apply(chip.remove)}
                    className="group inline-flex items-center gap-1.5 rounded-pill border border-subtle bg-card py-1 pl-2.5 pr-2 text-xs text-ink-700 hover:border-strong"
                >
                    {chip.label}
                    <XIcon size={12} className="text-ink-500 group-hover:text-ink-900" />
                    <span className="sr-only">Remove this filter</span>
                </button>
            ))}

            <button type="button" onClick={clear} className="text-xs font-medium text-brand hover:underline">
                Clear all
            </button>
        </div>
    );
}

/**
 * One chip per applied filter.
 *
 * A multi-select becomes one chip per value rather than one chip saying "3
 * categories": the buyer wants to drop Finance and keep the other two, and a
 * grouped chip can only be all or nothing.
 */
function build(filters: CatalogFilterState, facets: CatalogFacets, options: CatalogOptions): Chip[] {
    const chips: Chip[] = [];

    if (filters.q) chips.push({ key: 'q', label: `“${filters.q}”`, remove: { q: undefined } });
    if (filters.domain) chips.push({ key: 'domain', label: filters.domain, remove: { domain: undefined } });

    for (const [key, list, prefix, flag] of [
        ['categories', facets.categories, '', false],
        ['countries', facets.countries, '', true],
        ['languages', facets.languages, '', false],
    ] as const) {
        for (const id of filters[key] ?? []) {
            const option = list.find((o) => o.id === id);

            if (!option) continue;

            chips.push({
                key: `${key}-${id}`,
                label: `${flag && option.code ? `${flagFor(option.code)} ` : ''}${prefix}${option.name}`,
                remove: { [key]: (filters[key] ?? []).filter((value) => value !== id) },
            });
        }
    }

    if (filters.price) {
        const [low, high] = filters.price.split('-').map(Number);

        chips.push({
            key: 'price',
            label: `${money((low ?? 0) * 100).replace(/\.00$/, '')} – ${money((high ?? 0) * 100).replace(/\.00$/, '')}`,
            remove: { price: undefined },
        });
    }

    for (const [key, name, format] of [
        ['traffic', 'Traffic', (v: number) => (v >= 1000 ? `${Math.round(v / 100) / 10}K` : String(v))],
        ['dr', 'DR', String],
        ['da', 'DA', String],
    ] as const) {
        const value = filters[key];

        if (!value) continue;

        const [low, high] = value.split('-').map(Number);

        chips.push({
            key,
            label: `${name} ${format(low ?? 0)}–${format(high ?? 0)}`,
            remove: { [key]: undefined },
        });
    }

    if (filters.max_spam !== undefined) {
        chips.push({ key: 'spam', label: `Spam ≤ ${filters.max_spam}`, remove: { max_spam: undefined } });
    }

    for (const value of filters.speed ?? []) {
        const speed = options.speeds.find((s) => s.value === value);

        if (speed) {
            chips.push({
                key: `speed-${value}`,
                label: speed.label,
                remove: { speed: (filters.speed ?? []).filter((v) => v !== value) },
            });
        }
    }

    if (filters.link_type) {
        chips.push({
            key: 'link',
            label: filters.link_type === 'dofollow' ? 'Dofollow' : 'Nofollow',
            remove: { link_type: undefined },
        });
    }

    for (const slug of filters.topics ?? []) {
        const topic = options.topics.find((t) => t.slug === slug);

        if (topic) {
            chips.push({
                key: `topic-${slug}`,
                label: `Accepts ${topic.name}`,
                remove: { topics: (filters.topics ?? []).filter((value) => value !== slug) },
            });
        }
    }

    if (filters.favorites) chips.push({ key: 'fav', label: 'Favorites only', remove: { favorites: undefined } });
    if (filters.unused) chips.push({ key: 'unused', label: 'Not used in project', remove: { unused: undefined } });
    if (filters.has_traffic) {
        chips.push({ key: 'traffic-data', label: 'Has traffic data', remove: { has_traffic: undefined } });
    }

    // Not a narrowing, but it changes what is on screen in a way nothing else
    // signals once the rail is closed — a blacklisted row looks like an
    // ordinary one at a glance.
    if (filters.show_blacklisted) {
        chips.push({
            key: 'blacklist',
            label: 'Including blacklisted',
            remove: { show_blacklisted: undefined },
        });
    }

    return chips;
}
