import { useState } from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { number } from '@shared/lib/format';
import { Badge, type StatusKey } from '@shared/ui';
import type { StatusSlice } from '@shared/types/dashboard';

interface Props {
    slices: StatusSlice[];
    projectId: number | null;
}

/**
 * One stacked bar plus a legend that is also the navigation.
 *
 * On colour: the segment hues are the product's fixed status colours, which
 * were chosen for badges — small chips read in isolation — not for a palette
 * whose members sit edge to edge. Validated as a categorical palette they fail
 * outright: `new` (#1d4ed8) and `content_review` (#7e22ce) separate by only
 * ΔE 2.0 under deuteranopia, and `draft` and `frozen` are two greys. Changing
 * them is not on the table — a "Posted" chip is the same colour everywhere in
 * the product — so the bar does not ask colour to carry identity:
 *
 *   - every segment's status, count and share are written out in the legend;
 *   - segments are separated by a 2px surface gap, so adjacent ones are
 *     distinct shapes even when they are indistinguishable hues;
 *   - hovering or focusing a legend row isolates its segment and dims the
 *     rest, which maps row to segment without reference to colour at all.
 *
 * Each row is named by its own status rather than by its badge: Completed and
 * Posted share a colour, so a chip left to its default text would print
 * "Posted" twice with two different counts beside it.
 *
 * The bar is the proportion; the legend is the data.
 */
export function PostsByStatus({ slices, projectId }: Props) {
    const [active, setActive] = useState<string | null>(null);
    const total = slices.reduce((sum, slice) => sum + slice.count, 0);

    function open(status: string) {
        const params = new URLSearchParams({ status });
        if (projectId !== null) params.set('project', String(projectId));

        router.visit(`/posts?${params.toString()}`);
    }

    if (total === 0) {
        return <p className="py-8 text-center text-sm text-ink-500">No posts were created in this range.</p>;
    }

    return (
        <div>
            <div
                className="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-pill"
                role="img"
                aria-label={slices.map((slice) => `${slice.label}: ${slice.count}`).join(', ')}
            >
                {slices.map((slice) => (
                    <span
                        key={slice.status}
                        className={cn(
                            'h-full first:rounded-l-pill last:rounded-r-pill',
                            'transition-opacity duration-fast ease-standard',
                            active !== null && active !== slice.status && 'opacity-25',
                        )}
                        style={{
                            width: `${slice.pct}%`,
                            backgroundColor: `var(--status-${cssKey(slice.badge)}-fg)`,
                        }}
                    />
                ))}
            </div>

            <ul className="mt-4 flex flex-col">
                {slices.map((slice) => (
                    <li key={slice.status}>
                        <button
                            type="button"
                            onClick={() => open(slice.status)}
                            onMouseEnter={() => setActive(slice.status)}
                            onMouseLeave={() => setActive(null)}
                            onFocus={() => setActive(slice.status)}
                            onBlur={() => setActive(null)}
                            className={cn(
                                'flex w-full items-center gap-3 rounded-card px-2 py-2 text-left',
                                'transition-colors duration-fast ease-standard hover:bg-row-hover',
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className="size-2.5 shrink-0 rounded-pill"
                                style={{ backgroundColor: `var(--status-${cssKey(slice.badge)}-fg)` }}
                            />
                            <Badge status={slice.badge as StatusKey} label={slice.label} />
                            <span className="num ml-auto text-sm font-medium text-ink-900">{number(slice.count)}</span>
                            <span className="num w-12 text-right text-sm text-ink-500">{slice.pct.toFixed(0)}%</span>
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Badge keys are snake_case; two of the CSS tokens use a shorter word. */
function cssKey(badge: string): string {
    return badge === 'in_progress' ? 'progress' : badge === 'content_review' ? 'review' : badge;
}
