import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

export interface CardProps {
    title?: string;
    /** Right-aligned slot in the header — usually one button or a menu. */
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    padded?: boolean;
}

export function Card({ title, action, children, className, padded = true }: CardProps) {
    return (
        <section className={cn('rounded-card border border-subtle bg-card shadow-card', className)}>
            {(title ?? action) && (
                <header className="flex items-center justify-between gap-4 border-b border-subtle px-5 py-4">
                    {title && <h3 className="font-sora text-md font-semibold text-ink-900">{title}</h3>}
                    {action}
                </header>
            )}
            <div className={cn(padded && 'p-5')}>{children}</div>
        </section>
    );
}
