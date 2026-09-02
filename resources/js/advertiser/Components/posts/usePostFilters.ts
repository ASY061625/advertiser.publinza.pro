import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { PostFilterState } from '@shared/types/posts';

/**
 * Inertia takes an index-signature payload; PostFilterState is an interface, so
 * TypeScript will not widen it to one implicitly. Spreading into a fresh object
 * is enough to satisfy it, and doing that here keeps the workaround in one
 * place rather than at every call site that sends filters.
 */
export function asPayload(value: object): RequestPayload {
    return { ...value };
}

/**
 * The filter state, with the URL as the single source of truth.
 *
 * Everything the grid is showing lives in the query string, so a filtered view
 * is a link someone can send to a colleague and survives a refresh, a back
 * button and a bookmark. Keeping a second copy in component state and syncing
 * the two is where that guarantee usually breaks, so this hook holds one draft
 * — the search box, which needs to feel instant — and reads everything else
 * back off the server's own echo of the filters.
 */
export function usePostFilters(initial: PostFilterState) {
    const [filters, setFilters] = useState<PostFilterState>(initial);

    // The server echoes the filters it actually applied. Adopting them keeps
    // the UI honest about what is on screen — including a value it clamped or
    // discarded, which is exactly when the two would otherwise disagree.
    useEffect(() => setFilters(initial), [initial]);

    const visit = useCallback((next: PostFilterState, options: { replace?: boolean } = {}) => {
        setFilters(next);

        router.get('/posts', asPayload(next), {
            preserveState: true,
            preserveScroll: true,
            replace: options.replace ?? false,
            only: ['posts', 'tabCounts', 'filters', 'isFiltering'],
        });
    }, []);

    /** Any filter change resets to page one: page 7 of a new filter is nowhere. */
    const set = useCallback(
        (patch: Partial<PostFilterState>) => {
            const next = { ...filters, ...patch };

            for (const [key, value] of Object.entries(patch)) {
                const empty =
                    value === undefined ||
                    value === null ||
                    value === '' ||
                    (Array.isArray(value) && value.length === 0);

                if (empty) delete next[key as keyof PostFilterState];
            }

            visit(next);
        },
        [filters, visit],
    );

    const clearAll = useCallback(() => {
        // Sort order and page size are how the person likes to read the grid,
        // not a filter they set. "Clear all filters" leaves them alone.
        const { sort, direction, per_page: perPage } = filters;

        visit({ ...(sort && { sort }), ...(direction && { direction }), ...(perPage && { per_page: perPage }) });
    }, [filters, visit]);

    return { filters, set, clearAll, visit };
}

/**
 * Debounces the search box by 300ms.
 *
 * The input is uncontrolled by the round trip: typing updates local state
 * immediately and only the request waits, so the caret never jumps and a slow
 * response cannot swallow a keystroke.
 */
export function useDebouncedSearch(value: string, onCommit: (value: string) => void, delay = 300) {
    const [draft, setDraft] = useState(value);
    const committed = useRef(value);

    // Adopt a value that changed elsewhere — a cleared chip, a restored saved
    // view — without clobbering what is being typed right now.
    useEffect(() => {
        if (value !== committed.current) {
            committed.current = value;
            setDraft(value);
        }
    }, [value]);

    useEffect(() => {
        if (draft === committed.current) return;

        const timer = window.setTimeout(() => {
            committed.current = draft;
            onCommit(draft);
        }, delay);

        return () => window.clearTimeout(timer);
        // onCommit is rebuilt on every filter change; depending on it here
        // would restart the timer mid-word.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draft, delay]);

    return [draft, setDraft] as const;
}
