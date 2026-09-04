import { date } from '@shared/lib/format';
import type { CompetitorRow } from '@shared/types/competitors';
import { DomainMark } from './DomainMark';
import { MEASURES, valueOf } from './measures';

/**
 * The project's own site, pinned above the table.
 *
 * Every chip, bar and line below is measured against this row, so it is
 * rendered once, in the brand tint, where it stays in view while the table is
 * read. It has no delta chips of its own — a site is not ahead of itself — and
 * no Refresh: it is refreshed with everything else.
 */
export function YourSiteCard({ row }: { row: CompetitorRow | null }) {
    if (row === null) {
        return (
            <div className="rounded-card border border-subtle bg-sunken p-4 text-sm text-ink-500">
                This project has no readable website address, so there is nothing to compare against yet. Add one in
                Project settings.
            </div>
        );
    }

    return (
        <section
            aria-label="Your site"
            className="rounded-card border border-brand bg-brand-subtle p-4"
            style={{ borderColor: 'var(--brand-blue)' }}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    <DomainMark domain={row.domain} tone="brand" />

                    <div className="min-w-0">
                        <p className="truncate font-sora text-md font-semibold text-ink-900">{row.domain}</p>
                        <p className="text-sm text-ink-500">Your site</p>
                    </div>
                </div>

                {row.state === 'pending' ? (
                    <p className="text-sm text-ink-500">Measuring…</p>
                ) : row.updatedAt ? (
                    <p className="text-sm text-ink-500">Updated {date(row.updatedAt)}</p>
                ) : null}
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4 lg:grid-cols-7">
                {MEASURES.map((measure) => {
                    const value = valueOf(row.metrics, measure.key);

                    return (
                        <div key={measure.key} className="min-w-0">
                            <dt className="truncate text-xs text-ink-500">{measure.header}</dt>
                            <dd className="num mt-0.5 text-md font-semibold text-ink-900">
                                {value === null ? <span className="text-ink-500">—</span> : measure.exact(value)}
                            </dd>
                        </div>
                    );
                })}
            </dl>
        </section>
    );
}
