import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { Button, Checkbox, ChevronDownIcon, ChevronUpIcon, ListIcon, useDismiss } from '@shared/ui';
import type { ColumnPreferences } from '@shared/types/posts';

interface Props {
    preferences: ColumnPreferences;
    onChange: (order: string[], hidden: string[]) => void;
}

/**
 * Which columns show, and in what order.
 *
 * Order is changed with up/down buttons rather than drag-and-drop. Dragging is
 * nicer with a mouse and unusable without one; two buttons work with a
 * keyboard, a screen reader and a touch screen, and this is a preference
 * someone sets once.
 */
export function ColumnMenu({ preferences, onChange }: Props) {
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    const { order, hidden, available } = preferences;
    const labels = new Map(available.map((column) => [column.id, column]));

    function toggle(id: string) {
        onChange(order, hidden.includes(id) ? hidden.filter((value) => value !== id) : [...hidden, id]);
    }

    function move(id: string, delta: number) {
        const from = order.indexOf(id);
        const to = from + delta;

        if (from === -1 || to < 0 || to >= order.length) return;

        const next = [...order];
        next.splice(to, 0, ...next.splice(from, 1));

        onChange(next, hidden);
    }

    return (
        <div ref={ref} className="relative">
            <Button variant="secondary" onClick={() => setOpen((value) => !value)} aria-expanded={open}>
                <ListIcon size={14} />
                Columns
            </Button>

            {open && (
                <div
                    role="dialog"
                    aria-label="Choose and order columns"
                    className="absolute right-0 z-40 mt-1 w-72 animate-scale-in rounded-card border border-subtle bg-card p-2 shadow-card"
                >
                    {/* Eleven columns is taller than a short viewport, so the list
                        scrolls inside the popover rather than off the screen. */}
                    <ul className="flex max-h-[60vh] flex-col overflow-y-auto">
                        {order.map((id, index) => {
                            const column = labels.get(id);
                            if (!column) return null;

                            return (
                                <li
                                    key={id}
                                    className="flex items-center gap-2 rounded-card px-2 py-1.5 hover:bg-sunken"
                                >
                                    <Checkbox
                                        label={column.label}
                                        checked={!hidden.includes(id)}
                                        disabled={column.lockable}
                                        onChange={() => toggle(id)}
                                        className="flex-1"
                                    />

                                    <span className="flex shrink-0 gap-0.5">
                                        <ArrowButton
                                            label={`Move ${column.label} up`}
                                            disabled={index === 0}
                                            onClick={() => move(id, -1)}
                                        >
                                            <ChevronUpIcon size={13} />
                                        </ArrowButton>
                                        <ArrowButton
                                            label={`Move ${column.label} down`}
                                            disabled={index === order.length - 1}
                                            onClick={() => move(id, 1)}
                                        >
                                            <ChevronDownIcon size={13} />
                                        </ArrowButton>
                                    </span>
                                </li>
                            );
                        })}
                    </ul>

                    <p className="border-t border-subtle px-2 pb-1 pt-2 text-xs text-ink-500">
                        Website and Status are always shown — without them a row says nothing.
                    </p>
                </div>
            )}
        </div>
    );
}

function ArrowButton({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'rounded-button p-1 text-ink-500 transition-colors duration-fast',
                'hover:bg-card hover:text-ink-900 disabled:pointer-events-none disabled:opacity-30',
            )}
        >
            {children}
        </button>
    );
}
