import { router } from '@inertiajs/react';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@shared/lib/cn';
import { SearchIcon, useFocusTrap } from '@shared/ui';
import type { SearchGroup } from '@shared/types/shell';

/**
 * Cmd/Ctrl+K. Searches projects, websites and posts in one request.
 *
 * Results are flattened into a single ordered list for arrow-key navigation,
 * while still rendering grouped — moving down from the last project should land
 * on the first website, not stop at a group boundary.
 */
export function CommandPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState<SearchGroup[]>([]);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const trapRef = useFocusTrap<HTMLDivElement>(open);
    const inputRef = useRef<HTMLInputElement>(null);

    const flat = groups.flatMap((group) => group.items);

    useEffect(() => {
        if (!open) {
            setQuery('');
            setGroups([]);
            setActive(0);
        }
    }, [open]);

    // Debounced so typing does not fire a request per keystroke.
    useEffect(() => {
        if (!open) return;

        if (query.trim().length < 2) {
            setGroups([]);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);

            void fetch(`/search?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : { groups: [] }))
                .then((data: { groups: SearchGroup[] }) => {
                    setGroups(data.groups);
                    setActive(0);
                })
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 180);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [query, open]);

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((current) => (flat.length === 0 ? 0 : (current + 1) % flat.length));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((current) => (flat.length === 0 ? 0 : (current - 1 + flat.length) % flat.length));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const item = flat[active];

            if (item) {
                onClose();
                router.visit(item.href);
            }
        }
    }

    if (!open) return null;

    let cursor = -1;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[12vh]">
            <button
                type="button"
                aria-label="Close search"
                onClick={onClose}
                className="absolute inset-0 animate-fade-in bg-overlay"
            />

            <div
                ref={trapRef}
                role="dialog"
                aria-modal="true"
                aria-label="Search"
                className="relative w-full max-w-xl animate-scale-in overflow-hidden rounded-card border border-subtle bg-card shadow-card"
            >
                <div className="relative border-b border-subtle">
                    <SearchIcon
                        size={16}
                        className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-500"
                    />
                    <input
                        ref={inputRef}
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="Search projects, websites and posts"
                        aria-label="Search projects, websites and posts"
                        className="h-12 w-full bg-card pl-11 pr-4 text-md text-ink-900 placeholder:text-ink-500"
                    />
                </div>

                <div className="max-h-[52vh] overflow-auto py-2">
                    {query.trim().length < 2 && (
                        <p className="px-4 py-6 text-center text-base text-ink-500">
                            Type at least two characters to search.
                        </p>
                    )}

                    {query.trim().length >= 2 && !loading && flat.length === 0 && (
                        <p className="px-4 py-6 text-center text-base text-ink-500">
                            Nothing matches that. Try a shorter search.
                        </p>
                    )}

                    {groups.map((group) => (
                        <div key={group.label}>
                            <p className="px-4 pb-1 pt-3 text-sm text-ink-500">{group.label}</p>
                            <ul>
                                {group.items.map((item) => {
                                    cursor++;
                                    const isActive = cursor === active;

                                    return (
                                        <li key={item.id}>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    onClose();
                                                    router.visit(item.href);
                                                }}
                                                className={cn(
                                                    'flex w-full flex-col items-start px-4 py-2 text-left',
                                                    isActive ? 'bg-brand-subtle' : 'bg-card hover:bg-sunken',
                                                )}
                                            >
                                                <span className="text-base text-ink-900">{item.title}</span>
                                                {item.subtitle && (
                                                    <span className="text-sm text-ink-500">{item.subtitle}</span>
                                                )}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </div>

                <div className="flex items-center gap-4 border-t border-subtle px-4 py-2 text-xs text-ink-500">
                    <span>↑↓ to move</span>
                    <span>Enter to open</span>
                    <span>Esc to close</span>
                </div>
            </div>
        </div>,
        document.body,
    );
}
