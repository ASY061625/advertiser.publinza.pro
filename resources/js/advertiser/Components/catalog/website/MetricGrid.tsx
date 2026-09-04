import { compactNumber, number } from '@shared/lib/format';
import { cn } from '@shared/lib/cn';
import type { MetricTile } from '@shared/types/catalog';
import { Sparkline } from './Sparkline';

/** How each vendor is named to a buyer. */
const SOURCES: Record<string, string> = {
    ahrefs: 'Ahrefs',
    moz: 'Moz',
    semrush: 'SEMrush',
    similarweb: 'Similarweb',
    whois: 'WHOIS',
    manual: 'Publinza',
};

/**
 * The nine measures, three across.
 *
 * Each tile carries its own source and date. One date at the foot of the grid
 * would imply the nine were measured together, and they are not — a domain
 * rating is one vendor's crawl and a traffic figure is another vendor's
 * estimate, often weeks apart. A buyer comparing two sites on DR needs to know
 * both scores came from the same place on roughly the same day.
 */
export function MetricGrid({ tiles }: { tiles: MetricTile[] }) {
    return (
        <section aria-labelledby="site-metrics">
            <h3 id="site-metrics" className="mb-3 font-sora text-md font-semibold text-ink-900">
                Metrics
            </h3>

            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {tiles.map((tile) => (
                    <div key={tile.key} className="rounded-card border border-subtle bg-card p-3">
                        <dt className="truncate text-xs text-ink-500">{tile.label}</dt>

                        <dd className="mt-1 flex items-end justify-between gap-2">
                            <span
                                className={cn(
                                    'num text-md font-semibold',
                                    // An unmeasured tile keeps its place in the
                                    // grid and says so. Dropping it would make
                                    // the grid a different shape per site and
                                    // lose the fact that nobody has looked.
                                    tile.value === null ? 'text-ink-500' : 'text-ink-900',
                                )}
                            >
                                {tile.value === null ? '—' : format(tile.value, tile.format)}
                            </span>

                            {tile.sparkline && <Sparkline values={tile.sparkline} label={tile.label} />}
                        </dd>

                        <p className="mt-1.5 truncate text-xs text-ink-500">{provenance(tile)}</p>
                    </div>
                ))}
            </dl>
        </section>
    );
}

function format(value: number, kind: MetricTile['format']): string {
    switch (kind) {
        case 'money':
            return `$${compactNumber(value / 100)}`;
        case 'compact':
            return value < 10_000 ? number(value) : compactNumber(value);
        case 'age':
            return value >= 24
                ? `${Math.floor(value / 12)} years`
                : `${Math.max(1, Math.round(value))} months`;
        default:
            return number(value);
    }
}

/** "Ahrefs · 12 Mar", or what is missing and why. */
function provenance(tile: MetricTile): string {
    if (tile.value === null) return 'Not measured';

    const source = tile.source ? (SOURCES[tile.source] ?? tile.source) : null;
    const when = tile.fetchedAt
        ? new Intl.DateTimeFormat('en-US', { day: 'numeric', month: 'short' }).format(new Date(tile.fetchedAt))
        : null;

    if (source && when) return `${source} · ${when}`;
    if (source) return source;

    return when ?? '';
}
