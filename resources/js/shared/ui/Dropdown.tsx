import { useCallback, useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@shared/lib/cn';
import { useDismiss } from './usePopover';

export interface DropdownItem {
    id: string;
    label: string;
    icon?: ReactNode;
    /** Renders in danger colours — destructive actions only. */
    destructive?: boolean;
    disabled?: boolean;
    onSelect: () => void;
}

export interface DropdownProps {
    /** The control that opens the menu. Receives no props — wire the click via
     *  the wrapper, so any element can be a trigger. */
    trigger: ReactNode;
    items: DropdownItem[];
    align?: 'start' | 'end';
    className?: string;
}

/**
 * The menu is portalled to the body and positioned from the trigger's box.
 *
 * It used to be an absolutely-positioned child of the trigger, which is simpler
 * and works everywhere except the place this component is used most: inside a
 * table row. A row in a scrollable table sits under a `position: sticky` cell,
 * and a sticky cell creates a stacking context — so a menu inside one cannot
 * paint over the next row however high its z-index goes, and the container's
 * `overflow-x: auto` clips whatever hangs below the last row. Portalling takes
 * the menu out of both.
 */
export function Dropdown({ trigger, items, align = 'end', className }: DropdownProps) {
    const [open, setOpen] = useState(false);
    const [box, setBox] = useState<{ top: number; left: number; right: number } | null>(null);
    const menu = useRef<HTMLDivElement>(null);
    // The dismiss ref goes on the wrapper, which holds the trigger; the
    // portalled menu is registered as also-inside. Putting it on the menu alone
    // would make the trigger "outside", so clicking it while open would close
    // and immediately reopen.
    const anchor = useDismiss<HTMLDivElement>(open, () => setOpen(false), menu);

    const place = useCallback(() => {
        const rect = anchor.current?.getBoundingClientRect();

        if (rect) {
            setBox({ top: rect.bottom + 4, left: rect.left, right: globalThis.innerWidth - rect.right });
        }
    }, [anchor]);

    useLayoutEffect(() => {
        if (!open) return;

        place();

        // Fixed positioning is measured once from the viewport, so anything
        // that moves the trigger has to re-measure or the menu detaches from it.
        globalThis.addEventListener('scroll', place, { capture: true, passive: true });
        globalThis.addEventListener('resize', place);

        return () => {
            globalThis.removeEventListener('scroll', place, { capture: true });
            globalThis.removeEventListener('resize', place);
        };
    }, [open, place]);

    return (
        <div ref={anchor} className={cn('relative inline-block', className)}>
            <span onClick={() => setOpen((v) => !v)} className="contents">
                {trigger}
            </span>

            {open &&
                box !== null &&
                createPortal(
                    <div
                        ref={menu}
                        role="menu"
                        style={align === 'end' ? { top: box.top, right: box.right } : { top: box.top, left: box.left }}
                        className={cn(
                            'fixed z-50 min-w-48 animate-scale-in overflow-hidden rounded-card',
                            'border border-subtle bg-card py-1 shadow-card',
                        )}
                    >
                        {items.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                role="menuitem"
                                disabled={item.disabled}
                                onClick={() => {
                                    setOpen(false);
                                    item.onSelect();
                                }}
                                className={cn(
                                    'flex w-full items-center gap-2.5 px-3 py-2 text-left text-base',
                                    'transition-colors duration-fast ease-standard',
                                    'disabled:pointer-events-none disabled:opacity-50',
                                    item.destructive
                                        ? 'text-danger hover:bg-danger-bg'
                                        : 'text-ink-700 hover:bg-sunken',
                                )}
                            >
                                {item.icon && <span className="shrink-0 text-ink-500">{item.icon}</span>}
                                {item.label}
                            </button>
                        ))}
                    </div>,
                    document.body,
                )}
        </div>
    );
}
