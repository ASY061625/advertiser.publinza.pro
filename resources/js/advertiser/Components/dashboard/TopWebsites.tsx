import { money, number } from '@shared/lib/format';
import { GlobeIcon } from '@shared/ui';
import type { TopWebsite } from '@shared/types/dashboard';

interface Props {
    websites: TopWebsite[];
}

/**
 * The five sites that took the most of this advertiser's money in the range.
 *
 * Each row carries a proportional rail behind the domain. It is a rail, not a
 * QuantBar — QuantBar is the catalog's signature and stays there. This one is
 * plain brand blue at a low opacity, sized against the top spender rather than
 * against a quartile, and it never carries the top-quartile teal.
 */
export function TopWebsites({ websites }: Props) {
    const max = Math.max(...websites.map((site) => site.totalCents), 1);

    return (
        <ol className="flex flex-col gap-1">
            {websites.map((site, index) => (
                <li key={site.domain} className="relative overflow-hidden rounded-card px-3 py-2.5">
                    <span
                        aria-hidden="true"
                        className="absolute inset-y-0 left-0 bg-brand-subtle"
                        style={{ width: `${Math.max(4, (site.totalCents / max) * 100)}%` }}
                    />

                    <span className="relative flex items-center gap-3">
                        <span className="num w-4 shrink-0 text-sm text-ink-500">{index + 1}</span>
                        <GlobeIcon size={16} className="shrink-0 text-ink-500" />
                        <span className="truncate text-sm font-medium text-ink-900">{site.domain}</span>
                        <span className="num ml-auto shrink-0 whitespace-nowrap text-xs text-ink-500">
                            {number(site.placements)} {site.placements === 1 ? 'placement' : 'placements'}
                        </span>
                        <span className="num w-28 shrink-0 text-right text-sm font-medium text-ink-900">
                            {money(site.totalCents)}
                        </span>
                    </span>
                </li>
            ))}
        </ol>
    );
}
