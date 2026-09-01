import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@shared/lib/cn';
import { IconButton } from './IconButton';
import { XIcon } from './icons';
import { useDismiss, useFocusTrap } from './usePopover';

export interface DrawerProps {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    children?: ReactNode;
    footer?: ReactNode;
    className?: string;
}

/**
 * A right-hand drawer, 480px wide, sliding in over 180ms. Used for detail views
 * that should not lose the list behind them — a catalog site, an order, a
 * message thread.
 */
export function Drawer({ open, onClose, title, description, children, footer, className }: DrawerProps) {
    const trapRef = useFocusTrap<HTMLDivElement>(open);
    const dismissRef = useDismiss<HTMLDivElement>(open, onClose);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50">
            <div className="absolute inset-0 animate-fade-in bg-overlay" aria-hidden="true" />

            <div
                ref={dismissRef}
                className={cn('absolute inset-y-0 right-0 w-full max-w-drawer animate-slide-in-right', className)}
            >
                <div
                    ref={trapRef}
                    role="dialog"
                    aria-modal="true"
                    aria-label={title}
                    className="flex h-full flex-col border-l border-subtle bg-card shadow-card"
                >
                    <header className="flex items-start justify-between gap-4 border-b border-subtle px-5 py-4">
                        <div className="min-w-0">
                            <h2 className="truncate font-sora text-md font-semibold text-ink-900">{title}</h2>
                            {description && <p className="mt-0.5 truncate text-base text-ink-500">{description}</p>}
                        </div>
                        <IconButton label="Close" icon={<XIcon size={16} />} size="sm" onClick={onClose} />
                    </header>

                    <div className="flex-1 overflow-y-auto px-5 py-4 text-base text-ink-700">{children}</div>

                    {footer && (
                        <footer className="flex justify-end gap-2 border-t border-subtle px-5 py-4">{footer}</footer>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
