import { cn } from '@shared/lib/cn';
import { date, money, number } from '@shared/lib/format';
import {
    Badge,
    Checkbox,
    Dropdown,
    GlobeIcon,
    IconButton,
    MoreIcon,
    SortIcon,
    ChevronDownIcon,
    ChevronUpIcon,
} from '@shared/ui';
import type { PostRow } from '@shared/types/posts';

export interface RowAction {
    id: string;
    label: string;
    destructive?: boolean;
    enabled: (row: PostRow) => boolean;
    run: (row: PostRow) => void;
}

interface Props {
    rows: PostRow[];
    columns: string[];
    sort: { column: string; direction: 'asc' | 'desc' };
    onSortChange: (column: string) => void;
    selected: number[];
    onSelectionChange: (ids: number[]) => void;
    onRowClick: (row: PostRow) => void;
    actions: RowAction[];
    activeId: number | null;
}

const ANCHOR_MAX = 32;
const URL_MAX = 34;

/** Column id → header, alignment and whether the server can sort by it. */
const META: Record<string, { header: string; sortable: boolean; numeric?: boolean }> = {
    id: { header: 'ID', sortable: true, numeric: true },
    website: { header: 'Website', sortable: true },
    project: { header: 'Project', sortable: true },
    folder: { header: 'Folder', sortable: false },
    anchor_text: { header: 'Anchor text', sortable: true },
    target_url: { header: 'Target URL', sortable: false },
    status: { header: 'Status', sortable: true },
    price: { header: 'Price', sortable: true, numeric: true },
    created_at: { header: 'Created', sortable: true },
    published_at: { header: 'Published', sortable: true },
    deadline_at: { header: 'Deadline', sortable: true },
};

/** The sort key the server understands for a column. */
const SORT_KEY: Record<string, string> = { website: 'domain' };

/**
 * The grid.
 *
 * Its own table rather than the shared one: this needs runtime column order and
 * visibility, a row click that opens a drawer without swallowing the checkbox
 * or the action menu, and per-row action availability. Pushing all of that into
 * the shared Table would make every other grid in the product pay for it.
 */
export function PostsTable({
    rows,
    columns,
    sort,
    onSortChange,
    selected,
    onSelectionChange,
    onRowClick,
    actions,
    activeId,
}: Props) {
    const ids = rows.map((row) => row.id);
    const allSelected = ids.length > 0 && ids.every((id) => selected.includes(id));
    const someSelected = !allSelected && ids.some((id) => selected.includes(id));

    function toggleAll() {
        onSelectionChange(
            allSelected ? selected.filter((id) => !ids.includes(id)) : [...new Set([...selected, ...ids])],
        );
    }

    return (
        <div className="overflow-x-auto rounded-card border border-subtle bg-card shadow-card">
            <table className="w-full border-collapse text-left">
                <caption className="sr-only">
                    Posts. Select a row to open its details; use the column menu to change what is shown.
                </caption>
                <thead className="table-sticky-header">
                    <tr className="border-b border-subtle bg-card">
                        <th scope="col" className="w-10 px-3 py-2.5">
                            <Checkbox
                                label="Select all rows on this page"
                                hideLabel
                                checked={allSelected}
                                indeterminate={someSelected}
                                onChange={toggleAll}
                            />
                        </th>

                        {columns.map((id) => {
                            const meta = META[id];
                            if (!meta) return null;

                            const key = SORT_KEY[id] ?? id;
                            const active = sort.column === key;

                            return (
                                <th
                                    key={id}
                                    scope="col"
                                    aria-sort={
                                        active ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'
                                    }
                                    className={cn(
                                        'px-3 py-2.5 text-xs font-medium uppercase tracking-wide text-ink-500',
                                        meta.numeric && 'text-right',
                                    )}
                                >
                                    {meta.sortable ? (
                                        <button
                                            type="button"
                                            onClick={() => onSortChange(key)}
                                            className={cn(
                                                '-mx-1 inline-flex items-center gap-1 rounded-button px-1 py-0.5',
                                                'transition-colors duration-fast hover:text-ink-700',
                                                active && 'text-ink-900',
                                            )}
                                        >
                                            {meta.header}
                                            {active ? (
                                                sort.direction === 'asc' ? (
                                                    <ChevronUpIcon size={12} />
                                                ) : (
                                                    <ChevronDownIcon size={12} />
                                                )
                                            ) : (
                                                <SortIcon size={12} className="text-ink-300" />
                                            )}
                                        </button>
                                    ) : (
                                        meta.header
                                    )}
                                </th>
                            );
                        })}

                        <th scope="col" className="w-12 px-3 py-2.5">
                            <span className="sr-only">Row actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {rows.map((row) => {
                        const isSelected = selected.includes(row.id);

                        return (
                            <tr
                                key={row.id}
                                onClick={() => onRowClick(row)}
                                className={cn(
                                    'cursor-pointer border-b border-subtle last:border-0',
                                    'transition-colors duration-fast ease-standard',
                                    row.id === activeId
                                        ? 'bg-brand-subtle'
                                        : isSelected
                                          ? 'bg-sunken'
                                          : 'hover:bg-row-hover',
                                )}
                            >
                                {/* The checkbox and the menu are inside the clickable row, so
                                    both stop the click that would otherwise open the drawer. */}
                                <td className="px-3 py-2.5" onClick={(event) => event.stopPropagation()}>
                                    <Checkbox
                                        label={`Select post ${row.id}`}
                                        hideLabel
                                        checked={isSelected}
                                        onChange={() =>
                                            onSelectionChange(
                                                isSelected
                                                    ? selected.filter((id) => id !== row.id)
                                                    : [...selected, row.id],
                                            )
                                        }
                                    />
                                </td>

                                {columns.map((id) => (
                                    <td
                                        key={id}
                                        className={cn(
                                            'px-3 py-2.5 text-sm text-ink-700',
                                            META[id]?.numeric && 'num text-right',
                                        )}
                                    >
                                        {cell(id, row)}
                                    </td>
                                ))}

                                <td className="px-3 py-2.5 text-right" onClick={(event) => event.stopPropagation()}>
                                    <Dropdown
                                        items={actions
                                            .filter((action) => action.enabled(row))
                                            .map((action) => ({
                                                id: action.id,
                                                label: action.label,
                                                destructive: action.destructive,
                                                onSelect: () => action.run(row),
                                            }))}
                                        trigger={
                                            <IconButton
                                                label={`Actions for post ${row.id}`}
                                                variant="ghost"
                                                size="sm"
                                                icon={<MoreIcon size={16} />}
                                            />
                                        }
                                    />
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function cell(id: string, row: PostRow) {
    switch (id) {
        case 'id':
            return <span className="text-ink-500">{row.id}</span>;

        case 'website':
            return (
                <span className="flex items-center gap-2">
                    <GlobeIcon size={16} className="shrink-0 text-ink-300" />
                    <span className="min-w-0">
                        <span className="block truncate font-medium text-ink-900">{row.domain}</span>
                        <span className="num block text-xs text-ink-500">
                            {row.dr === null ? 'DR —' : `DR ${row.dr}`}
                            {' · '}
                            {row.traffic === null ? 'no traffic data' : `${number(row.traffic)}/mo`}
                        </span>
                    </span>
                </span>
            );

        case 'project':
            return row.project ?? <span className="text-ink-300">—</span>;

        case 'folder':
            return row.folder ?? <span className="text-ink-300">—</span>;

        case 'anchor_text':
            return <Truncated value={row.anchorText} max={ANCHOR_MAX} />;

        case 'target_url':
            return row.targetUrl ? (
                <a
                    href={row.targetUrl}
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    onClick={(event) => event.stopPropagation()}
                    title={row.targetUrl}
                    className="inline-flex items-center gap-1 text-brand hover:underline"
                >
                    {clip(row.targetUrl, URL_MAX)}
                    <ExternalLinkIcon />
                </a>
            ) : (
                <span className="text-ink-300">—</span>
            );

        case 'status':
            return <Badge status={row.badge} label={row.statusLabel} />;

        case 'price':
            return money(row.priceCents);

        case 'created_at':
            return <DateCell value={row.createdAt} />;

        case 'published_at':
            return <DateCell value={row.publishedAt} />;

        case 'deadline_at':
            return <DeadlineCell value={row.deadlineAt} />;

        default:
            return null;
    }
}

function Truncated({ value, max }: { value: string | null; max: number }) {
    if (!value) return <span className="text-ink-300">—</span>;

    const clipped = value.length > max;

    return <span title={clipped ? value : undefined}>{clip(value, max)}</span>;
}

function DateCell({ value }: { value: string | null }) {
    return value ? <span className="num text-ink-500">{date(value)}</span> : <span className="text-ink-300">—</span>;
}

/** A deadline inside 48 hours reads amber, and says so in words as well. */
function DeadlineCell({ value }: { value: string | null }) {
    if (!value) return <span className="text-ink-300">—</span>;

    const diff = new Date(value).getTime() - Date.now();
    const overdue = diff < 0;
    const urgent = !overdue && diff < 48 * 3_600_000;

    return (
        <span
            className={cn(
                'num',
                overdue ? 'font-medium text-danger' : urgent ? 'font-medium text-warning' : 'text-ink-500',
            )}
        >
            {date(value)}
            {overdue && <span className="ml-1 text-xs font-normal">overdue</span>}
            {urgent && <span className="ml-1 text-xs font-normal">soon</span>}
        </span>
    );
}

function clip(value: string, max: number): string {
    return value.length > max ? `${value.slice(0, max)}…` : value;
}

function ExternalLinkIcon() {
    return (
        <svg
            width={11}
            height={11}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className="shrink-0"
        >
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
            <path d="M15 3h6v6" />
            <path d="M10 14 21 3" />
        </svg>
    );
}
