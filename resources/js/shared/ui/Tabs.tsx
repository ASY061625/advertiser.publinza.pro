import { useId, useRef, type KeyboardEvent, type ReactNode } from 'react';
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
}

/**
 * Underline tabs. Arrow keys move between tabs and Home/End jump to the ends,
 * per the WAI-ARIA tabs pattern.
 */
export function Tabs({ items, value, onChange, className }: TabsProps) {
    const baseId = useId();
    const listRef = useRef<HTMLDivElement>(null);

    function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        const enabled = items.filter((item) => !item.disabled);
        const current = enabled.findIndex((item) => item.id === value);
        if (current === -1) return;

        let next = current;
        if (event.key === 'ArrowRight') next = (current + 1) % enabled.length;
        else if (event.key === 'ArrowLeft') next = (current - 1 + enabled.length) % enabled.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = enabled.length - 1;
        else return;

        event.preventDefault();
        const target = enabled[next];
        if (!target) return;

        onChange(target.id);
        listRef.current?.querySelector<HTMLButtonElement>(`#${CSS.escape(`${baseId}-${target.id}`)}`)?.focus();
    }

    const active = items.find((item) => item.id === value);

    return (
        <div className={className}>
            <div
                ref={listRef}
                role="tablist"
                onKeyDown={onKeyDown}
                className="flex items-center gap-6 border-b border-subtle"
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
                            tabIndex={selected ? 0 : -1}
                            disabled={item.disabled}
                            onClick={() => onChange(item.id)}
                            className={cn(
                                'relative -mb-px flex items-center gap-2 border-b-2 pb-3 pt-2',
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
