import { useState, type ReactNode } from 'react';
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

export function Dropdown({ trigger, items, align = 'end', className }: DropdownProps) {
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    return (
        <div ref={ref} className={cn('relative inline-block', className)}>
            <span onClick={() => setOpen((v) => !v)} className="contents">
                {trigger}
            </span>

            {open && (
                <div
                    role="menu"
                    className={cn(
                        'absolute z-40 mt-1 min-w-48 animate-scale-in overflow-hidden rounded-card',
                        'border border-subtle bg-card py-1 shadow-card',
                        align === 'end' ? 'right-0' : 'left-0',
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
                                item.destructive ? 'text-danger hover:bg-danger-bg' : 'text-ink-700 hover:bg-sunken',
                            )}
                        >
                            {item.icon && <span className="shrink-0 text-ink-500">{item.icon}</span>}
                            {item.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
