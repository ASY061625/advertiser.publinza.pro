import { Link } from '@inertiajs/react';
import { Button } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { Recommendation } from '@shared/types/competitors';

interface Props {
    projectId: number;
    recommendations: Recommendation[];
}

/**
 * What the comparison suggests doing about itself.
 *
 * Every card is a gap that was measured, not a prompt that was written: the
 * count is that competitor's referring domains in a category minus yours, and
 * the category only appears because Publinza has sites in it — so the button
 * always lands on a catalog with something in it.
 *
 * Prominent but not pushy is a layout decision made here: the strip sits below
 * the charts, in one plain card, with the finding in ink and one button per
 * card. No badges, no urgency, nothing that dismisses. It is the answer to
 * "so what do I do about this?" for a reader who has just seen the answer to
 * "how do I compare?" — and it says nothing at all when there is no gap.
 */
export function RecommendationStrip({ projectId, recommendations }: Props) {
    if (recommendations.length === 0) return null;

    return (
        <section
            aria-labelledby="competitor-suggestions"
            className="rounded-card border border-subtle bg-card p-5 shadow-card"
        >
            <h3 id="competitor-suggestions" className="font-sora text-md font-semibold text-ink-900">
                Where they are published and you are not
            </h3>
            <p className="mt-0.5 max-w-prose text-sm text-ink-500">
                Counted from the sites linking to each competitor that also sit in our catalog — so every one of these
                is a placement you can order.
            </p>

            <ul className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {recommendations.map((suggestion) => (
                    <li
                        key={`${suggestion.competitorId}-${suggestion.category}`}
                        className="flex flex-col justify-between gap-3 rounded-card border border-subtle bg-sunken p-4"
                    >
                        <p className="text-base text-ink-900">
                            <span className="font-medium">{suggestion.competitor}</span> has{' '}
                            <span className="num font-medium">{number(suggestion.count)}</span> links from{' '}
                            <span className="font-medium">{suggestion.category}</span> sites you don’t.
                        </p>

                        <Link href={catalogHref(projectId, suggestion)} className="self-start">
                            <Button variant="secondary" size="sm">
                                Find sites in {suggestion.category}
                            </Button>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * The catalog, already filtered to the category the card is about.
 *
 * `project` so the sites are priced and matched against this project, and
 * `categories[]` because that is the shape CatalogFilters reads. A card whose
 * button dropped the reader into an unfiltered catalog would be asking them to
 * redo the analysis by hand.
 */
function catalogHref(projectId: number, suggestion: Recommendation): string {
    const query = new URLSearchParams({ project: String(projectId) });

    if (suggestion.categoryId !== null) {
        query.append('categories[]', String(suggestion.categoryId));
    }

    return `/catalog?${query.toString()}`;
}
