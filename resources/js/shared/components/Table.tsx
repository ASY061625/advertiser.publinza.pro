import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

interface TableProps {
    children: ReactNode;
    /** Catalog rows are 56px to make room for the quant-bars; 48px elsewhere. */
    density?: 'catalog' | 'default';
    stickyFirstColumn?: boolean;
    className?: string;
}

export function Table({ children, density = 'default', stickyFirstColumn = false, className }: TableProps) {
    return (
        <div className="overflow-x-auto rounded-card border border-ink-300 bg-surface-card shadow-card">
            <table
                className={cn(
                    'w-full border-collapse text-left text-base',
                    'table-sticky-head',
                    stickyFirstColumn && 'table-sticky-first',
                    density === 'catalog' ? '[&_tbody_tr]:h-row-catalog' : '[&_tbody_tr]:h-row',
                    className,
                )}
            >
                {children}
            </table>
        </div>
    );
}

export function Th({ children, numeric = false }: { children: ReactNode; numeric?: boolean }) {
    return (
        <th
            scope="col"
            className={cn(
                'border-b border-ink-300 px-4 py-3 text-sm font-medium text-ink-500',
                numeric && 'numeric text-right',
            )}
        >
            {children}
        </th>
    );
}

export function Td({ children, numeric = false }: { children: ReactNode; numeric?: boolean }) {
    return (
        <td className={cn('border-b border-ink-300 px-4 text-ink-700', numeric && 'numeric text-right')}>{children}</td>
    );
}
