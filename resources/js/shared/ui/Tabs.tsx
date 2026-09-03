import { useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

export interface TabItem {
    id: string;
    label: string;
    /** Optional trailing count, e.g. the number of rows behind the tab. */
    count?: number;
    disabled?: boolean;
    content?: ReactNode;
}

export interface TabsProps {
    items: TabItem[];
    value: string;
    onChange: (id: string) => void;
    className?: string;
    /**
     * Lets the tab row scroll sideways when the tabs are wider than the space,
     * instead of wrapping. Only the row scrolls — the panel below stays the
     * width of the page, which is the point: a `min-width` on the whole
     * component would drag the panel's content off the right of a phone.
     */
    scrollable?: boolean;
    /**
     * Arrow keys move focus without switching tab; Enter or Space commits.
     *
     * The default is automatic activation, which is right when the panels are
     * already loaded. Use this when activating a tab costs something — a page
     * load, a request, a navigation — because with automatic activation you
     * cannot arrow *past* such a tab to reach the ones after it.
     */
    manualActivation?: boolean;
}

/**
 * Underline tabs. Arrow keys move between tabs and Home/End jump to the ends,
 * per the WAI-ARIA tabs pattern — activating as they go, or on Enter/Space when
 * `manualActivation` is set.
 */
export function Tabs({ items, value, onChange, className, scrollable = false, manualActivation = false }: TabsProps) {
    const baseId = useId();
    const listRef = useRef<HTMLDivElement>(null);

    // Which tab the keyboard is on, which is only ever different from the
    // selected one while arrowing through a manually-activated bar.
    const [focused, setFocused] = useState(value);

    useEffect(() => setFocused(value), [value]);

    function focus(id: string) {
        setFocused(id);
        listRef.current?.querySelector<HTMLButtonElement>(`#${CSS.escape(`${baseId}-${id}`)}`)?.focus();
    }

    function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        const enabled = items.filter((item) => !item.disabled);
        const current = enabled.findIndex((item) => item.id === focused);
        if (current === -1) return;

        if (manualActivation && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            onChange(focused);

            return;
        }

        let next = current;
        if (event.key === 'ArrowRight') next = (current + 1) % enabled.length;
        else if (event.key === 'ArrowLeft') next = (current - 1 + enabled.length) % enabled.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = enabled.length - 1;
        else return;

        event.preventDefault();
        const target = enabled[next];
        if (!target) return;

        if (!manualActivation) onChange(target.id);
        focus(target.id);
    }

    const active = items.find((item) => item.id === value);

    return (
        <div className={className}>
            <div
                ref={listRef}
                role="tablist"
                onKeyDown={onKeyDown}
                className={cn(
                    'flex items-center gap-6 border-b border-subtle',
                    scrollable && 'max-w-full overflow-x-auto',
                )}
            >
                {items.map((item) => {
                    const selected = item.id === value;

                    return (
                        <button
                            key={item.id}
                            id={`${baseId}-${item.id}`}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            aria-controls={`${baseId}-${item.id}-panel`}
                            tabIndex={item.id === focused ? 0 : -1}
                            disabled={item.disabled}
                            onClick={() => {
                                focus(item.id);
                                onChange(item.id);
                            }}
                            className={cn(
                                'relative -mb-px flex shrink-0 items-center gap-2 border-b-2 pb-3 pt-2',
                                'font-sora text-base font-medium transition-colors duration-fast ease-standard',
                                'disabled:pointer-events-none disabled:opacity-50',
                                selected
                                    ? 'border-brand text-brand'
                                    : 'border-transparent text-ink-500 hover:text-ink-700',
                            )}
                        >
                            {item.label}
                            {item.count !== undefined && (
                                <span
                                    className={cn(
                                        'num rounded-pill px-1.5 py-0.5 text-xs',
                                        selected ? 'bg-brand-subtle text-brand' : 'bg-sunken text-ink-500',
                                    )}
                                >
                                    {item.count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {active?.content && (
                <div
                    id={`${baseId}-${active.id}-panel`}
                    role="tabpanel"
                    aria-labelledby={`${baseId}-${active.id}`}
                    className="pt-5"
                >
                    {active.content}
                </div>
            )}
        </div>
    );
}
