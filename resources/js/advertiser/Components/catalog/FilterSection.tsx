import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { ChevronDownIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';

const STORAGE_KEY = 'catalog:filter-sections';

interface Props {
    id: string;
    title: string;
    /** Shown beside the title when the section is narrowing the results. */
    badge?: ReactNode;
    defaultOpen?: boolean;
    children: ReactNode;
}

/**
 * One collapsible group in the filter rail, remembering whether it was open.
 *
 * Fourteen sections do not fit on a screen, and a rail that reopens all of them
 * on every visit makes the buyer re-collapse the twelve they do not use every
 * time. The state is per-viewer convenience — which is exactly what
 * localStorage is for — and every read and write is guarded, because a private
 * window or a browser set to block site data throws on access rather than
 * returning empty.
 */
export function FilterSection({ id, title, badge, defaultOpen = false, children }: Props) {
    const [open, setOpen] = useState(defaultOpen);

    useEffect(() => {
        const stored = readState()[id];

        if (typeof stored === 'boolean') setOpen(stored);
    }, [id]);

    const toggle = useCallback(() => {
        setOpen((current) => {
            writeState(id, !current);

            return !current;
        });
    }, [id]);

    return (
        <section className="border-b border-subtle last:border-0">
            <h3>
                <button
                    type="button"
                    onClick={toggle}
                    aria-expanded={open}
                    aria-controls={`filter-${id}`}
                    className="flex w-full items-center justify-between gap-2 py-3 text-left text-sm font-medium text-ink-900 hover:text-brand"
                >
                    <span className="flex min-w-0 items-center gap-2">
                        <span className="truncate">{title}</span>
                        {badge}
                    </span>
                    <ChevronDownIcon
                        size={14}
                        className={cn('shrink-0 text-ink-500 transition-transform', open && 'rotate-180')}
                    />
                </button>
            </h3>

            {/* Unmounted rather than hidden: a closed section holding a mounted
                slider still measures and still answers queries, and there are
                thirteen of them. */}
            {open && (
                <div id={`filter-${id}`} className="pb-4">
                    {children}
                </div>
            )}
        </section>
    );
}

/** The count on a section header, so a collapsed filter is never invisible. */
export function AppliedBadge({ count }: { count: number }) {
    if (count === 0) return null;

    return (
        <span className="num shrink-0 rounded-pill bg-brand-subtle px-1.5 py-0.5 text-xs font-medium text-brand">
            {count}
        </span>
    );
}

function readState(): Record<string, boolean> {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        return raw ? (JSON.parse(raw) as Record<string, boolean>) : {};
    } catch {
        return {};
    }
}

function writeState(id: string, open: boolean): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...readState(), [id]: open }));
    } catch {
        // A remembered accordion is not worth an exception. The section still
        // opens; it just will not be open again tomorrow.
    }
}
