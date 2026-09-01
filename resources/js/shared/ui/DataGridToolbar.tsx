import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { Button } from './Button';
import { Input } from './Input';
import { SearchIcon } from './icons';

export interface DataGridToolbarProps {
    search?: string;
    onSearchChange?: (value: string) => void;
    searchPlaceholder?: string;
    /** Filter controls — selects, multi-selects, range sliders. */
    filters?: ReactNode;
    /** Right-hand actions, e.g. Export. */
    actions?: ReactNode;
    /** Count of selected rows; switches the bar into selection mode. */
    selectedCount?: number;
    onClearSelection?: () => void;
    /** Bulk actions shown while rows are selected. */
    bulkActions?: ReactNode;
    className?: string;
}

/**
 * The bar above a data grid. When rows are selected it swaps to a selection
 * bar in place, so the table never jumps as actions appear.
 */
export function DataGridToolbar({
    search,
    onSearchChange,
    searchPlaceholder = 'Search',
    filters,
    actions,
    selectedCount = 0,
    onClearSelection,
    bulkActions,
    className,
}: DataGridToolbarProps) {
    if (selectedCount > 0) {
        return (
            <div
                className={cn(
                    'flex flex-wrap items-center justify-between gap-3 rounded-card border border-subtle',
                    'bg-brand-subtle px-4 py-2.5',
                    className,
                )}
            >
                <p className="num text-base font-medium text-brand">{selectedCount} selected</p>
                <div className="flex items-center gap-2">
                    {bulkActions}
                    {onClearSelection && (
                        <Button variant="ghost" size="sm" onClick={onClearSelection}>
                            Clear selection
                        </Button>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className={cn('flex flex-wrap items-end justify-between gap-3', className)}>
            <div className="flex flex-wrap items-end gap-3">
                {onSearchChange && (
                    <div className="w-64">
                        <Input
                            hideLabel
                            label={searchPlaceholder}
                            placeholder={searchPlaceholder}
                            value={search ?? ''}
                            leadingIcon={<SearchIcon size={15} />}
                            onChange={(event) => onSearchChange(event.target.value)}
                        />
                    </div>
                )}
                {filters}
            </div>

            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}
