import { money, number } from '@shared/lib/format';
import type { ProjectOverviewStats } from '@shared/types/projects';

/**
 * Four figures about the project, above the grid's tabs.
 *
 * The same numbers the General tab's Deals and Finance panels show, from the
 * same aggregate — a project cannot report one total here and another there.
 * They are the project's totals, not the filtered grid's: the tab counts right
 * below already answer "how many am I looking at", and repeating that would
 * make the strip move every time a filter changed.
 */
export function ProjectSummaryStrip({ stats }: { stats: ProjectOverviewStats }) {
    const pairs: { label: string; value: string; className?: string }[] = [
        { label: 'Posts', value: number(stats.posts.total) },
        { label: 'Spent', value: money(stats.spentCents) },
        { label: 'Frozen', value: money(stats.frozenCents), className: 'text-[color:var(--gold)]' },
        {
            // Null, not zero: nothing has completed yet, and $0.00 would read
            // as "these placements are free".
            label: 'Average price',
            value: stats.averageCents === null ? '—' : money(stats.averageCents),
        },
    ];

    return (
        <dl className="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-card border border-subtle bg-card px-4 py-3 shadow-card">
            {pairs.map((pair, index) => (
                <div key={pair.label} className="flex items-center gap-5">
                    {/* ink-300, which is what border-subtle resolves to —
                        `subtle` is only registered as a border colour, so
                        `bg-subtle` would paint nothing at all. */}
                    {index > 0 && <span aria-hidden="true" className="h-5 w-px bg-ink-300" />}

                    <div className="flex items-baseline gap-2">
                        <dt className="text-sm text-ink-500">{pair.label}</dt>
                        <dd className={`num text-md font-semibold ${pair.className ?? 'text-ink-900'}`}>
                            {pair.value}
                        </dd>
                    </div>
                </div>
            ))}
        </dl>
    );
}
