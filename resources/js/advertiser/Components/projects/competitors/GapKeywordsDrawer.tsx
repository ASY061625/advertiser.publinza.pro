import { useEffect, useState } from 'react';
import { Drawer, Skeleton } from '@shared/ui';
import { date, number } from '@shared/lib/format';
import type { GapKeyword } from '@shared/types/competitors';

interface Props {
    projectId: number;
    /** The competitor whose gap list to show, or null when the drawer is shut. */
    competitor: { id: number; domain: string; label: string | null } | null;
    limit: number;
    onClose: () => void;
}

interface Payload {
    domain: string;
    label: string | null;
    updatedAt: string | null;
    keywords: GapKeyword[];
}

/**
 * What they rank for and you do not.
 *
 * Fetched when the drawer opens rather than shipped with the tab: a hundred
 * keywords for each of ten competitors is most of a megabyte, and on most
 * visits nobody opens one.
 */
export function GapKeywordsDrawer({ projectId, competitor, limit, onClose }: Props) {
    const [payload, setPayload] = useState<Payload | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (competitor === null) return;

        // Cleared first, so an open drawer never shows the previous
        // competitor's keywords under this one's name while the fetch runs.
        setPayload(null);
        setError(null);

        const controller = new AbortController();

        fetch(`/projects/${projectId}/competitors/${competitor.id}/gap-keywords`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
            .then((data: Payload) => setPayload(data))
            .catch((reason: unknown) => {
                if (reason instanceof DOMException && reason.name === 'AbortError') return;

                setError('These keywords could not be loaded. Close the drawer and try again.');
            });

        return () => controller.abort();
    }, [competitor, projectId]);

    if (competitor === null) return null;

    return (
        <Drawer
            open
            onClose={onClose}
            title={`Gap keywords · ${competitor.domain}`}
            // One short line: the Drawer truncates its description to a single
            // row, and a date appended here was being cut off mid-word. It
            // goes above the table instead, where it has room.
            description={`Their top ${limit} keywords you don’t rank for`}
        >
            {error !== null ? (
                <p className="text-base text-danger">{error}</p>
            ) : payload === null ? (
                <div className="flex flex-col gap-2">
                    {Array.from({ length: 8 }, (_, i) => (
                        <Skeleton key={i} className="h-8 w-full" />
                    ))}
                </div>
            ) : payload.keywords.length === 0 ? (
                <p className="text-base text-ink-500">
                    Nothing here — either this competitor ranks for nothing you don’t, or the provider returned no
                    comparison for them.
                </p>
            ) : (
                <>
                    {payload.updatedAt && (
                        <p className="mb-3 text-sm text-ink-500">Measured {date(payload.updatedAt)}</p>
                    )}

                    {/* The panel is 480px and the ranking URLs are long. The
                        table scrolls inside it rather than clipping the
                        difficulty column off the right edge. */}
                    <div className="-mx-1 overflow-x-auto px-1">
                        <table className="w-full min-w-[420px] border-collapse text-left text-sm">
                            <caption className="sr-only">
                                Keywords {payload.domain} ranks for and your site does not
                            </caption>
                            <thead>
                                <tr className="border-b border-subtle">
                                    <th scope="col" className="py-2 pr-2 font-medium text-ink-500">
                                        Keyword
                                    </th>
                                    <th scope="col" className="py-2 pr-2 text-right font-medium text-ink-500">
                                        Pos.
                                    </th>
                                    <th scope="col" className="py-2 pr-2 text-right font-medium text-ink-500">
                                        Volume
                                    </th>
                                    <th scope="col" className="py-2 text-right font-medium text-ink-500">
                                        Difficulty
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {payload.keywords.map((keyword, index) => (
                                    <tr
                                        key={`${keyword.keyword}-${index}`}
                                        className="border-b border-subtle last:border-0"
                                    >
                                        <td className="max-w-[220px] py-2 pr-2">
                                            <p className="text-ink-900">{keyword.keyword}</p>
                                            {keyword.url && (
                                                <a
                                                    href={keyword.url}
                                                    target="_blank"
                                                    // The ranking page belongs to a
                                                    // competitor. noreferrer as well as
                                                    // noopener: their analytics should
                                                    // not learn that this advertiser is
                                                    // studying them.
                                                    rel="noopener noreferrer nofollow"
                                                    className="mt-0.5 block truncate text-xs text-brand hover:underline"
                                                >
                                                    {keyword.url}
                                                </a>
                                            )}
                                        </td>
                                        <td className="num py-2 pr-2 text-right text-ink-900">{keyword.position}</td>
                                        <td className="num py-2 pr-2 text-right text-ink-900">
                                            {number(keyword.volume)}
                                        </td>
                                        <td className="num py-2 text-right text-ink-900">{keyword.difficulty}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </Drawer>
    );
}
