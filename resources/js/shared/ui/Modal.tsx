import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@shared/lib/cn';
import { IconButton } from './IconButton';
import { XIcon } from './icons';
import { useDismiss, useFocusTrap } from './usePopover';

export interface ModalProps {
    open: boolean;
    onClose: () => void;
    title: string;
    /** One line under the title. Not a paragraph. */
    description?: string;
    children?: ReactNode;
    /** Buttons, right-aligned. Primary action last. */
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

const SIZES = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' } as const;

export function Modal({ open, onClose, title, description, children, footer, size = 'md', className }: ModalProps) {
    const trapRef = useFocusTrap<HTMLDivElement>(open);
    const dismissRef = useDismiss<HTMLDivElement>(open, onClose);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 animate-fade-in bg-overlay" aria-hidden="true" />

            <div ref={dismissRef} className={cn('relative w-full animate-scale-in', SIZES[size], className)}>
                <div
                    ref={trapRef}
                    role="dialog"
                    aria-modal="true"
                    aria-label={title}
                    className="rounded-card border border-subtle bg-card shadow-card"
                >
                    <header className="flex items-start justify-between gap-4 px-5 pb-3 pt-5">
                        <div>
                            <h2 className="font-sora text-md font-semibold text-ink-900">{title}</h2>
                            {description && <p className="mt-1 text-base text-ink-500">{description}</p>}
                        </div>
                        <IconButton label="Close" icon={<XIcon size={16} />} size="sm" onClick={onClose} />
                    </header>

                    {children && <div className="px-5 py-2 text-base text-ink-700">{children}</div>}

                    {footer && (
                        <footer className="flex justify-end gap-2 border-t border-subtle px-5 py-4">{footer}</footer>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
