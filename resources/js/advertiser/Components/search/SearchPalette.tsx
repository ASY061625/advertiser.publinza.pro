import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { SearchIcon, Spinner, useFocusTrap } from '@shared/ui';
import type { SearchGroup, SearchResponse } from '@shared/types/search';
import { useAddPost } from '../post-wizard/AddPostProvider';
import { SearchRow } from './SearchRow';
import { matchActions } from './actions';
import { clearRecentSearches, pushRecentSearch, readRecentSearches } from './recentSearches';

/** Long enough that a fast typist fires one request, short enough to feel live. */
const DEBOUNCE_MS = 200;

/** Matches GlobalSearch::MIN_TERM. Below it the server answers with recents. */
const MIN_TERM = 2;

/**
 * Cmd/Ctrl+K, from every authenticated screen.
 *
 * Keyboard-first: arrows move through every row regardless of which group it is
 * in, Tab jumps to the head of the next group, Enter opens, Escape closes. The
 * mouse is supported but second — this is a thing you use without leaving the
 * home row.
 */
export function SearchPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [query, setQuery] = useState('');
    const [response, setResponse] = useState<SearchResponse | null>(null);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const [history, setHistory] = useState<string[]>([]);

    const trapRef = useFocusTrap<HTMLDivElement>(open);
    const listRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const { open: openAddPost } = useAddPost();

    // Every request carries a sequence number and only the newest may write.
    // Typing "tech" then deleting to "te" would otherwise let the slower first
    // response land last and paint results for a query nobody is looking at.
    const ticket = useRef(0);

    const trimmed = query.trim();
    const searching = trimmed.length >= MIN_TERM;

    const serverGroups = response?.groups ?? [];

    /*
     * Every action when the search found nothing.
     *
     * A zero result is exactly when somebody needs another way through, so the
     * palette widens rather than emptying: the fuzzy match had nothing to offer,
     * so offer all eight commands instead of an empty box under "Nothing
     * matched".
     */
    const foundNothing = searching && !loading && response !== null && serverGroups.length === 0;

    const actions = useMemo(
        () => matchActions(trimmed, { addPost: () => openAddPost() }, foundNothing),
        [trimmed, openAddPost, foundNothing],
    );

    /*
     * Actions last, and always present when they match.
     *
     * The server's four groups arrive in their specified order; the fifth is
     * assembled here because it is a fixed list the browser already has. It
     * survives a zero-result search on purpose — "nothing matched" with a way
     * to top up your balance is still a useful palette.
     */
    const groups: SearchGroup[] = useMemo(() => {
        const server = response?.groups ?? [];

        return actions.length === 0
            ? server
            : [...server, { key: 'actions' as const, label: 'Actions', items: actions, seeAll: null }];
    }, [response, actions]);

    const flat = useMemo(
        () => groups.flatMap((group) => group.items.map((item) => ({ item, groupKey: group.key }))),
        [groups],
    );

    // Where each group starts in the flattened list, for Tab.
    const groupStarts = useMemo(() => {
        const starts: number[] = [];
        let at = 0;

        for (const group of groups) {
            starts.push(at);
            at += group.items.length;
        }

        return starts;
    }, [groups]);

    useEffect(() => {
        if (open) {
            setHistory(readRecentSearches());

            return;
        }

        // Reset on close rather than on open, so the palette never flashes the
        // previous search for a frame before clearing it.
        setQuery('');
        setResponse(null);
        setActive(0);
        setLoading(false);
    }, [open]);

    // Debounced. An empty box still asks: the server answers that with recently
    // viewed sites and projects, which is what makes Cmd+K a way back to
    // yesterday's work rather than only a search box.
    useEffect(() => {
        if (!open) return;

        const controller = new AbortController();
        const mine = ++ticket.current;

        const timer = window.setTimeout(() => {
            setLoading(true);

            void fetch(`/search?q=${encodeURIComponent(trimmed)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((r) => (r.ok ? (r.json() as Promise<SearchResponse>) : null))
                .then((data) => {
                    if (data === null || mine !== ticket.current) return;

                    setResponse(data);
                    setActive(0);
                })
                .catch(() => undefined)
                .finally(() => {
                    if (mine === ticket.current) setLoading(false);
                });
        }, DEBOUNCE_MS);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [trimmed, open]);

    const choose = useCallback(
        (index: number) => {
            const entry = flat[index];

            if (entry === undefined) return;

            if (searching) setHistory(pushRecentSearch(trimmed));

            onClose();

            if (entry.item.run !== undefined) {
                entry.item.run();

                return;
            }

            router.visit(entry.item.href);
        },
        [flat, onClose, searching, trimmed],
    );

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'Escape') {
            event.preventDefault();
            onClose();

            return;
        }

        if (event.key === 'Tab') {
            // Tab cycles *groups*, not rows — the one movement arrows cannot
            // do cheaply when a group has five items in it. Focus stays in the
            // input throughout, which is why preventing the default is right
            // here and would be wrong almost anywhere else.
            if (groupStarts.length === 0) return;

            event.preventDefault();

            // findLastIndex is ES2023 and this build targets earlier; a
            // backwards scan says the same thing and needs no lib bump.
            let current = 0;

            for (let i = groupStarts.length - 1; i >= 0; i--) {
                if ((groupStarts[i] ?? 0) <= active) {
                    current = i;
                    break;
                }
            }

            const step = event.shiftKey ? -1 : 1;
            const next = (current + step + groupStarts.length) % groupStarts.length;

            setActive(groupStarts[next] ?? 0);

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (flat.length === 0) return;

            const step = event.key === 'ArrowDown' ? 1 : -1;

            setActive((current) => (current + step + flat.length) % flat.length);

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            choose(active);
        }
    }

    // Keeps the highlighted row in view when the keyboard moves past the fold.
    useEffect(() => {
        const entry = flat[active];

        if (entry === undefined) return;

        listRef.current
            ?.querySelector(`#palette-${CSS.escape(entry.item.id)}`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [active, flat]);

    if (!open) return null;

    const showHistory = !searching && history.length > 0;
    const nothingMatched = foundNothing;

    let cursor = -1;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[10vh]">
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
                aria-label="Search Publinza"
                className="relative flex max-h-[80vh] w-full max-w-palette animate-scale-in flex-col overflow-hidden rounded-card border border-subtle bg-card shadow-card"
            >
                <div className="relative flex shrink-0 items-center border-b border-subtle">
                    <SearchIcon
                        size={16}
                        aria-hidden="true"
                        className="pointer-events-none absolute left-4 text-ink-500"
                    />

                    <input
                        ref={inputRef}
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="Search websites, projects, posts and conversations"
                        aria-label="Search websites, projects, posts and conversations"
                        role="combobox"
                        aria-expanded
                        aria-controls="palette-results"
                        aria-activedescendant={flat[active] ? `palette-${flat[active].item.id}` : undefined}
                        autoComplete="off"
                        spellCheck={false}
                        className="h-palette-input w-full bg-card pl-11 pr-4 text-md text-ink-900 placeholder:text-ink-500"
                    />
                </div>

                <div ref={listRef} id="palette-results" role="listbox" className="min-h-0 flex-1 overflow-y-auto py-2">
                    {/* A row, not an overlay. A spinner over the results makes
                        the list unreadable for as long as it is up; a row at the
                        top leaves the previous answer legible while the next one
                        is on its way. */}
                    {loading && (
                        <div className="flex items-center gap-3 px-4 py-2.5 text-base text-ink-500">
                            <Spinner size={14} />
                            Searching…
                        </div>
                    )}

                    {showHistory && (
                        <section>
                            <div className="flex items-center justify-between px-4 pb-1 pt-2">
                                <h2 className="text-xs font-medium uppercase tracking-wide text-ink-500">
                                    Recent searches
                                </h2>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setHistory(clearRecentSearches());
                                        // Focus goes back where the typing is.
                                        // A keyboard-first palette that drops
                                        // the caret when you press one of its
                                        // own buttons is one you have to reach
                                        // for the mouse to recover.
                                        inputRef.current?.focus();
                                    }}
                                    className="text-sm text-ink-500 underline hover:text-ink-700"
                                >
                                    Clear
                                </button>
                            </div>

                            <ul className="flex flex-wrap gap-1.5 px-4 pb-2 pt-1">
                                {history.map((term) => (
                                    <li key={term}>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setQuery(term);
                                                inputRef.current?.focus();
                                            }}
                                            className="flex items-center gap-1.5 rounded-pill bg-sunken px-2.5 py-1 text-sm text-ink-700 hover:bg-row-hover"
                                        >
                                            <SearchIcon size={11} className="text-ink-500" />
                                            {term}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {nothingMatched && (
                        <div className="px-4 py-6 text-center">
                            <p className="text-base text-ink-900">
                                Nothing matched <span className="font-medium">“{trimmed}”</span>
                            </p>
                            <p className="mt-1 text-sm text-ink-500">
                                Try fewer words, or a domain without the .com.
                            </p>
                        </div>
                    )}

                    {groups.map((group) => (
                        <section key={group.key}>
                            <div className="flex items-center justify-between px-4 pb-1 pt-3">
                                <h2 className="text-xs font-medium uppercase tracking-wide text-ink-500">
                                    {group.label}
                                </h2>

                                {group.seeAll !== null && (
                                    <Link
                                        href={group.seeAll}
                                        onClick={onClose}
                                        className="text-sm text-brand underline"
                                    >
                                        See all
                                    </Link>
                                )}
                            </div>

                            {group.items.map((item) => {
                                cursor++;
                                const index = cursor;

                                return (
                                    <SearchRow
                                        key={item.id}
                                        item={item}
                                        groupKey={group.key}
                                        query={searching ? trimmed : ''}
                                        active={index === active}
                                        onHover={() => setActive(index)}
                                        onChoose={() => choose(index)}
                                    />
                                );
                            })}
                        </section>
                    ))}

                    {!searching && !loading && groups.length === 0 && !showHistory && (
                        <p className="px-4 py-8 text-center text-base text-ink-500">
                            Search for a website, a project, a post or a conversation.
                        </p>
                    )}
                </div>

                {/* Hidden on a phone. Telling somebody about ↑↓ and Esc on a
                    device with no keyboard costs two rows of a small screen to
                    describe keys they do not have. */}
                <div className="hidden shrink-0 flex-wrap items-center gap-x-4 gap-y-1 border-t border-subtle px-4 py-2 text-xs text-ink-500 sm:flex">
                    <Hint keys="↑↓" label="move" />
                    <Hint keys="Tab" label="next group" />
                    <Hint keys="↵" label="open" />
                    <Hint keys="Esc" label="close" />
                </div>
            </div>
        </div>,
        document.body,
    );
}

function Hint({ keys, label }: { keys: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5">
            <kbd className="rounded border border-subtle bg-sunken px-1.5 py-0.5 font-sans text-[11px] text-ink-700">
                {keys}
            </kbd>
            {label}
        </span>
    );
}
