import { router } from '@inertiajs/react';
import { useCallback, useRef, type ReactNode } from 'react';
import { Button, DownloadIcon, Input, SearchIcon, Select } from '@shared/ui';
import type { ListFilters } from '@shared/types/lists';

interface Props {
    filters: ListFilters;
    categories: { id: number; name: string }[];
    sorts: { value: string; label: string }[];
    apply: (changes: Partial<Record<string, unknown>>) => void;
    exportHref: string;
    /** Anything the tab wants beside the export button. */
    children?: ReactNode;
}

/**
 * Search, category, sort and export — the same four on every tab.
 *
 * Shared rather than repeated because the three lists are the same kind of
 * thing seen three ways, and a search box that behaved differently on the
 * blacklist would be a small lie about that.
 */
export function ListToolbar({ filters, categories, sorts, apply, exportHref, children }: Props) {
    return (
        <div className="flex flex-wrap items-end gap-3">
            <span className="min-w-[200px] flex-1">
                <Input
                    label="Search this list"
                    hideLabel
                    leadingIcon={<SearchIcon size={16} />}
                    defaultValue={filters.q ?? ''}
                    onChange={(event) => apply({ q: event.target.value || undefined })}
                    placeholder="Domain or title"
                />
            </span>

            <Select
                label="Category"
                hideLabel
                value={filters.category === null ? '' : String(filters.category)}
                onChange={(event) => apply({ category: event.target.value || undefined })}
                options={[
                    { value: '', label: 'Any category' },
                    ...categories.map((category) => ({ value: String(category.id), label: category.name })),
                ]}
            />

            <Select
                label="Sort"
                hideLabel
                value={filters.sort}
                onChange={(event) => apply({ sort: event.target.value })}
                options={sorts}
            />

            {children}

            {/* A plain link, not an Inertia visit: this is a download, and
                Inertia would try to render the CSV as a page. */}
            <a href={exportHref}>
                <Button variant="secondary">
                    <DownloadIcon size={14} />
                    Export CSV
                </Button>
            </a>
        </div>
    );
}

/**
 * Query-string state, debounced for typing and immediate for everything else.
 *
 * The URL is the whole view here as it is in the catalog, so a filtered list is
 * a link somebody can send.
 */
export function useListQuery(base: Record<string, unknown>) {
    // The timer lives in a ref, not a closure variable: a per-render variable
    // would be a new timer every keystroke and would never actually debounce.
    const timer = useRef<number>();
    const latest = useRef(base);
    latest.current = base;

    return useCallback((changes: Record<string, unknown>) => {
        const next = clean({ ...latest.current, ...changes });
        const typing = 'q' in changes;

        window.clearTimeout(timer.current);

        const go = () =>
            router.get('/lists', next, { preserveState: true, preserveScroll: true, replace: true });

        if (typing) {
            timer.current = window.setTimeout(go, 300);
        } else {
            go();
        }
    }, []);
}

/** Drops empty values, so the URL says only what was actually chosen. */
function clean(query: Record<string, unknown>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(query)
            .filter(([, value]) => value !== undefined && value !== null && value !== '' && value !== false)
            .map(([key, value]) => [key, String(value)]),
    );
}
