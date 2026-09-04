import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { CatalogFilterState } from '@shared/types/catalog';

/** Everything the results depend on. A change to any of it starts a new read. */
const RESULT_PROPS = ['sites', 'pagination', 'total', 'facets', 'filters', 'isFiltering', 'suggestions'];

/**
 * The catalog's filter state, with the URL as the only copy.
 *
 * Fourteen filter groups is exactly the situation where a component-state copy
 * and a URL copy drift apart, so there is one: every change is a visit, and
 * what comes back is what the page renders. The two things that would feel
 * wrong under that rule get local drafts — the search box and the domain box,
 * which have to echo a keystroke immediately — and both are debounced into the
 * same single source of truth.
 */
export function useCatalogFilters(initial: CatalogFilterState, debounceMs = 300) {
    const [filters, setFilters] = useState<CatalogFilterState>(initial);
    const timer = useRef<number>();

    // The server echoes the filters it actually applied, including any it
    // clamped or dropped. Adopting them keeps the rail honest about what the
    // results were produced by, which is the whole point of one copy.
    useEffect(() => setFilters(initial), [initial]);

    const visit = useCallback((next: CatalogFilterState, replace = false) => {
        setFilters(next);

        router.get('/catalog', toPayload(next), {
            preserveState: true,
            preserveScroll: true,
            replace,
            only: RESULT_PROPS,
        });
    }, []);

    /**
     * A filter change, always from the first page.
     *
     * Keeping the cursor would ask the server to continue a list that no longer
     * exists: the cursor names a row in the old ordering, and the row it names
     * may not be in the new results at all.
     */
    const apply = useCallback(
        (patch: Partial<CatalogFilterState>) => {
            window.clearTimeout(timer.current);
            // The cursor is dropped unless this call is the one setting it.
            // Carrying the old one into a new filter set would ask the server
            // to continue a list that no longer exists.
            visit(clean({ ...filters, cursor: undefined, ...patch }));
        },
        [filters, visit],
    );

    /** The two text fields: echoed instantly, sent once typing settles. */
    const applyDebounced = useCallback(
        (patch: Partial<CatalogFilterState>) => {
            const next = clean({ ...filters, ...patch });

            setFilters(next);
            window.clearTimeout(timer.current);

            // Replaces rather than pushes, so a ten-character search leaves one
            // entry in the history instead of ten to press Back through.
            timer.current = window.setTimeout(() => visit(next, true), debounceMs);
        },
        [debounceMs, filters, visit],
    );

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const clear = useCallback(() => {
        window.clearTimeout(timer.current);

        // The project survives a clear: it is what mode the page is in, not a
        // filter on it, and dropping it would take the buyer out of buying.
        visit(clean({ project: filters.project, view: filters.view, per_page: filters.per_page }));
    }, [filters.per_page, filters.project, filters.view, visit]);

    return { filters, apply, applyDebounced, clear };
}

/** Drops everything at its default, so the URL carries only what was chosen. */
function clean(filters: CatalogFilterState): CatalogFilterState {
    const next: Record<string, unknown> = { ...filters };

    for (const [key, value] of Object.entries(next)) {
        const empty =
            value === undefined ||
            value === null ||
            value === '' ||
            value === false ||
            (Array.isArray(value) && value.length === 0);

        if (empty) delete next[key];
    }

    return next;
}

export function toPayload(filters: CatalogFilterState): RequestPayload {
    return { ...filters };
}

/** "10-250" ⇄ [10, 250], the one place the wire format is understood. */
export function parseRange(value: string | undefined, fallback: [number, number]): [number, number] {
    if (!value) return fallback;

    const [low, high] = value.split('-').map(Number);

    return Number.isFinite(low) && Number.isFinite(high) ? [low!, high!] : fallback;
}

export function formatRange(range: [number, number]): string {
    return `${Math.round(range[0])}-${Math.round(range[1])}`;
}
