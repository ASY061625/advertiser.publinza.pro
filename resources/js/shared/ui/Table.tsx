import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { Checkbox } from './Checkbox';
import { ChevronDownIcon, ChevronUpIcon, SortIcon } from './icons';

export type SortDirection = 'asc' | 'desc';

export interface SortState {
    column: string;
    direction: SortDirection;
}

export interface Column<Row> {
    id: string;
    header: string;
    /** Right-aligns and applies tabular figures. */
    numeric?: boolean;
    sortable?: boolean;
    width?: string;
    cell: (row: Row) => ReactNode;
}

export interface TableProps<Row> {
    columns: Column<Row>[];
    rows: Row[];
    rowKey: (row: Row) => string;
    /** 48px everywhere except the catalog, which needs 56px for the quant-bars. */
    density?: 'default' | 'catalog';
    stickyHeader?: boolean;
    stickyFirstColumn?: boolean;
    sort?: SortState;
    onSortChange?: (sort: SortState) => void;
    selectedKeys?: string[];
    onSelectionChange?: (keys: string[]) => void;
    /** Rendered in place of the body when there are no rows. */
    empty?: ReactNode;
    loading?: boolean;
    className?: string;
}

export function Table<Row>({
    columns,
    rows,
    rowKey,
    density = 'default',
    stickyHeader = true,
    stickyFirstColumn = false,
    sort,
    onSortChange,
    selectedKeys,
    onSelectionChange,
    empty,
    loading = false,
    className,
}: TableProps<Row>) {
    const selectable = selectedKeys !== undefined && onSelectionChange !== undefined;
    const keys = rows.map(rowKey);
    const allSelected = selectable && keys.length > 0 && keys.every((key) => selectedKeys.includes(key));
    const someSelected = selectable && !allSelected && keys.some((key) => selectedKeys.includes(key));

    function toggleAll() {
        if (!selectable) return;
        onSelectionChange(
            allSelected ? selectedKeys.filter((key) => !keys.includes(key)) : [...new Set([...selectedKeys, ...keys])],
        );
    }

    function toggleRow(key: string) {
        if (!selectable) return;
        onSelectionChange(selectedKeys.includes(key) ? selectedKeys.filter((k) => k !== key) : [...selectedKeys, key]);
    }

    function nextSort(columnId: string): SortState {
        if (sort?.column === columnId) {
            return { column: columnId, direction: sort.direction === 'asc' ? 'desc' : 'asc' };
        }

        return { column: columnId, direction: 'desc' };
    }

    const rowHeight = density === 'catalog' ? 'h-row-catalog' : 'h-row';

    return (
        <div className={cn('overflow-x-auto rounded-card border border-subtle bg-card shadow-card', className)}>
            <table
                className={cn(
                    'w-full border-collapse text-left text-base',
                    stickyHeader && 'table-sticky-head',
                    stickyFirstColumn && 'table-sticky-col',
                )}
            >
                <thead>
                    <tr>
                        {selectable && (
                            <th scope="col" className="w-10 border-b border-subtle bg-sunken px-4 py-3">
                                <Checkbox
                                    label=""
                                    aria-label="Select all rows on this page"
                                    checked={allSelected}
                                    indeterminate={someSelected}
                                    onChange={toggleAll}
                                />
                            </th>
                        )}

                        {columns.map((column) => {
                            const active = sort?.column === column.id;

                            return (
                                <th
                                    key={column.id}
                                    scope="col"
                                    style={column.width ? { width: column.width } : undefined}
                                    aria-sort={
                                        active ? (sort.direction === 'asc' ? 'ascending' : 'descending') : undefined
                                    }
                                    className={cn(
                                        'border-b border-subtle px-4 py-3 text-sm font-medium text-ink-500',
                                        column.numeric && 'num text-right',
                                    )}
                                >
                                    {column.sortable && onSortChange ? (
                                        <button
                                            type="button"
                                            onClick={() => onSortChange(nextSort(column.id))}
                                            className={cn(
                                                'inline-flex items-center gap-1 rounded-button transition-colors duration-fast hover:text-ink-700',
                                                column.numeric && 'flex-row-reverse',
                                                active && 'text-ink-900',
                                            )}
                                        >
                                            {column.header}
                                            {active ? (
                                                sort.direction === 'asc' ? (
                                                    <ChevronUpIcon size={14} />
                                                ) : (
                                                    <ChevronDownIcon size={14} />
                                                )
                                            ) : (
                                                <SortIcon size={13} className="text-ink-300" />
                                            )}
                                        </button>
                                    ) : (
                                        column.header
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>

                <tbody>
                    {loading &&
                        Array.from({ length: 5 }, (_, i) => (
                            <tr key={`skeleton-${i}`} className={rowHeight}>
                                {selectable && <td className="border-b border-subtle px-4" />}
                                {columns.map((column) => (
                                    <td key={column.id} className="border-b border-subtle px-4">
                                        <span className="block h-3 w-2/3 animate-pulse rounded-pill bg-sunken" />
                                    </td>
                                ))}
                            </tr>
                        ))}

                    {!loading && rows.length === 0 && empty && (
                        <tr>
                            <td colSpan={columns.length + (selectable ? 1 : 0)} className="p-0">
                                {empty}
                            </td>
                        </tr>
                    )}

                    {!loading &&
                        rows.map((row) => {
                            const key = rowKey(row);
                            const selected = selectable && selectedKeys.includes(key);

                            return (
                                <tr
                                    key={key}
                                    data-selected={selected || undefined}
                                    className={cn(
                                        rowHeight,
                                        'transition-colors duration-fast ease-standard',
                                        selected ? 'bg-brand-subtle' : 'hover:bg-row-hover',
                                    )}
                                >
                                    {selectable && (
                                        <td className="border-b border-subtle px-4">
                                            <Checkbox
                                                label=""
                                                aria-label={`Select row ${key}`}
                                                checked={selected}
                                                onChange={() => toggleRow(key)}
                                            />
                                        </td>
                                    )}

                                    {columns.map((column) => (
                                        <td
                                            key={column.id}
                                            className={cn(
                                                'border-b border-subtle px-4 text-ink-700',
                                                column.numeric && 'num text-right',
                                            )}
                                        >
                                            {column.cell(row)}
                                        </td>
                                    ))}
                                </tr>
                            );
                        })}
                </tbody>
            </table>
        </div>
    );
}
