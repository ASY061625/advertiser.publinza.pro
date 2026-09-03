import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { number } from '@shared/lib/format';
import { Skeleton } from '@shared/ui';

interface Props {
    projectId: number;
    topicIds: number[];
    countryIds: number[];
    languageIds: number[];
}

/**
 * How many sites the targeting on screen would show, answered while it is
 * still being chosen.
 *
 * Targeting is three multi-selects whose effect is invisible until you reach
 * the catalog and find nine sites left. This puts the consequence next to the
 * cause, which is the only place it is useful.
 *
 * The count is of what is *typed*, not what is saved, so "View them" carries
 * the same unsaved targeting into the catalog as filters — otherwise the link
 * would show a different set from the number above it.
 */
export function TargetingMatchCard({ projectId, topicIds, countryIds, languageIds }: Props) {
    const [count, setCount] = useState<number | null>(null);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);

    // Ticking six countries in a row should be one request, not six.
    const key = JSON.stringify([topicIds, countryIds, languageIds]);
    const latest = useRef(0);

    useEffect(() => {
        const request = ++latest.current;
        setLoading(true);
        setFailed(false);

        const timer = window.setTimeout(() => {
            const query = new URLSearchParams();
            topicIds.forEach((id) => query.append('topics[]', String(id)));
            countryIds.forEach((id) => query.append('countries[]', String(id)));
            languageIds.forEach((id) => query.append('languages[]', String(id)));

            void fetch(`/projects/${projectId}/match-count?${query.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) => (response.ok ? (response.json() as Promise<{ count: number }>) : null))
                .then((body) => {
                    // A slow earlier request must not overwrite a faster later
                    // one; only the newest answer is allowed to land.
                    if (request !== latest.current) return;

                    if (body === null) setFailed(true);
                    else setCount(body.count);

                    setLoading(false);
                })
                .catch(() => {
                    if (request !== latest.current) return;

                    setFailed(true);
                    setLoading(false);
                });
        }, 350);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key, projectId]);

    const href = catalogHref(projectId, topicIds, countryIds, languageIds);

    return (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-card bg-sunken px-4 py-3">
            <p className="text-sm text-ink-700">
                {failed ? (
                    'We could not count the matching sites just now. The catalog still knows.'
                ) : loading && count === null ? (
                    <Skeleton width="w-56" height="h-4" />
                ) : (
                    <>
                        <span className="num font-semibold text-ink-900">{number(count ?? 0)}</span>{' '}
                        {count === 1 ? 'site' : 'sites'} in the catalog match this targeting
                        {loading && <span className="ml-2 text-xs text-ink-500">updating…</span>}
                    </>
                )}
            </p>

            <Link href={href} className="shrink-0 text-sm font-medium text-brand hover:underline">
                View them
            </Link>
        </div>
    );
}

/** The catalog, pre-filtered with exactly the targeting shown above. */
function catalogHref(projectId: number, topics: number[], countries: number[], languages: number[]): string {
    const query = new URLSearchParams({ project: String(projectId) });

    topics.forEach((id) => query.append('topics[]', String(id)));
    countries.forEach((id) => query.append('countries[]', String(id)));
    languages.forEach((id) => query.append('languages[]', String(id)));

    return `/catalog?${query.toString()}`;
}
