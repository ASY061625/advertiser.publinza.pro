import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    Button,
    Dropdown,
    IconButton,
    Input,
    Modal,
    MoreIcon,
    Skeleton,
    Table,
    type Column,
    type SortState,
} from '@shared/ui';
import { date } from '@shared/lib/format';
import type { CompetitorRow, MeasureKey } from '@shared/types/competitors';
import { DeltaChip } from './DeltaChip';
import { DomainMark } from './DomainMark';
import { MEASURES, valueOf } from './measures';
import { strokeFor } from './palette';

interface Props {
    projectId: number;
    rows: CompetitorRow[];
    readOnly: boolean;
    onOpenGap: (row: CompetitorRow) => void;
}

/**
 * The comparison table.
 *
 * Sorting is client-side. Every row is already on the page — there are at most
 * ten — so a round trip to reorder ten rows would be slower than the sort and
 * would cost the scroll position on the way back.
 */
export function CompetitorTable({ projectId, rows, readOnly, onOpenGap }: Props) {
    const [sort, setSort] = useState<SortState>({ column: 'organicTraffic', direction: 'desc' });
    const [editing, setEditing] = useState<CompetitorRow | null>(null);
    const [removing, setRemoving] = useState<CompetitorRow | null>(null);

    const sorted = useMemo(() => sortRows(rows, sort), [rows, sort]);

    const columns: Column<CompetitorRow>[] = [
        {
            id: 'domain',
            header: 'Domain',
            sortable: true,
            width: '220px',
            cell: (row) => {
                const stroke = strokeFor(row.slot);

                return (
                    <div className="flex min-w-0 items-center gap-2.5">
                        {/* The chart's stroke for this row, drawn exactly as the
                            chart draws it — so a line in the trend chart can be
                            traced back to a table row without counting legend
                            entries. */}
                        <svg width="14" height="10" aria-hidden="true" className="shrink-0">
                            <line
                                x1="0"
                                y1="5"
                                x2="14"
                                y2="5"
                                stroke={stroke.color}
                                strokeWidth={stroke.width}
                                strokeDasharray={stroke.dash}
                            />
                        </svg>

                        <DomainMark domain={row.domain} />

                        <div className="min-w-0">
                            <p className="truncate text-base text-ink-900">{row.domain}</p>
                            {row.label && <p className="truncate text-xs text-ink-500">{row.label}</p>}
                        </div>
                    </div>
                );
            },
        },

        ...MEASURES.map((measure): Column<CompetitorRow> => ({
            id: measure.key,
            header: measure.header,
            numeric: true,
            sortable: true,
            cell: (row) => {
                if (row.state === 'pending' && row.metrics === null) {
                    return <Skeleton className="ml-auto h-4 w-16" />;
                }

                const value = valueOf(row.metrics, measure.key);

                if (value === null) {
                    return (
                        <span className="text-ink-500" title="Not measured by this provider">
                            —
                        </span>
                    );
                }

                return (
                    <span className="inline-flex items-baseline whitespace-nowrap" title={measure.exact(value)}>
                        {measure.format(value)}
                        <DeltaChip delta={row.deltas?.[measure.key]} />
                    </span>
                );
            },
        })),

        {
            id: 'updatedAt',
            header: 'Last updated',
            sortable: true,
            cell: (row) =>
                row.state === 'pending' ? (
                    <span className="text-sm text-ink-500">Measuring…</span>
                ) : row.updatedAt ? (
                    <span className="text-sm text-ink-500">{date(row.updatedAt)}</span>
                ) : (
                    <span className="text-sm text-ink-500">—</span>
                ),
        },

        {
            id: 'actions',
            header: '',
            width: '52px',
            cell: (row) => (
                <div className="flex justify-end">
                    <Dropdown
                        trigger={
                            <IconButton
                                label={`Actions for ${row.domain}`}
                                variant="ghost"
                                size="sm"
                                icon={<MoreIcon size={16} />}
                            />
                        }
                        items={[
                            {
                                id: 'refresh',
                                label: refreshLabel(row),
                                disabled: readOnly || row.cooldownSeconds > 0 || row.state === 'pending',
                                onSelect: () =>
                                    router.post(
                                        `/projects/${projectId}/competitors/${row.id}/refresh`,
                                        {},
                                        { preserveScroll: true, only: ['competitors', 'flash', 'errors'] },
                                    ),
                            },
                            {
                                id: 'label',
                                label: 'Edit label',
                                disabled: readOnly,
                                onSelect: () => setEditing(row),
                            },
                            {
                                id: 'gap',
                                label: 'View gap keywords',
                                disabled: row.gapKeywords === 0,
                                onSelect: () => onOpenGap(row),
                            },
                            {
                                id: 'remove',
                                label: 'Remove',
                                destructive: true,
                                disabled: readOnly,
                                onSelect: () => setRemoving(row),
                            },
                        ]}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Table
                columns={columns}
                rows={sorted}
                rowKey={(row) => String(row.id)}
                sort={sort}
                onSortChange={setSort}
                stickyFirstColumn
            />

            {/* Keyed by row, so the field is re-created — and re-seeded from
                that row's label — every time a different competitor is opened.
                One instance holding state across rows is how an edit dialog
                ends up saving the previous row's label onto this one. */}
            {editing && (
                <LabelDialog key={editing.id} projectId={projectId} row={editing} onClose={() => setEditing(null)} />
            )}
            <RemoveDialog projectId={projectId} row={removing} onClose={() => setRemoving(null)} />
        </>
    );
}

/**
 * The cooldown, on the control it gates.
 *
 * A disabled button with no reason on it is the same as a broken one. The
 * remaining time is rounded up in whole hours because the limit is a day: "in
 * 7 hours" is what someone needs, and a ticking countdown to the second would
 * be precision about a wait nobody is watching.
 */
function refreshLabel(row: CompetitorRow): string {
    if (row.state === 'pending') return 'Refreshing…';
    if (row.cooldownSeconds <= 0) return 'Refresh';

    const hours = Math.ceil(row.cooldownSeconds / 3600);

    return hours <= 1 ? 'Refresh (in under an hour)' : `Refresh (in ${hours} hours)`;
}

function sortRows(rows: CompetitorRow[], sort: SortState): CompetitorRow[] {
    const factor = sort.direction === 'asc' ? 1 : -1;

    return [...rows].sort((a, b) => {
        if (sort.column === 'domain') return a.domain.localeCompare(b.domain) * factor;

        if (sort.column === 'updatedAt') {
            return ((Date.parse(a.updatedAt ?? '') || 0) - (Date.parse(b.updatedAt ?? '') || 0)) * factor;
        }

        const key = sort.column as MeasureKey;
        const left = valueOf(a.metrics, key);
        const right = valueOf(b.metrics, key);

        // A row with no number for this column sits at the bottom whichever way
        // the sort runs. Treating null as zero would rank a site nobody
        // measured above one measured at zero, which is a different claim.
        if (left === null && right === null) return 0;
        if (left === null) return 1;
        if (right === null) return -1;

        return (left - right) * factor;
    });
}

function LabelDialog({ projectId, row, onClose }: { projectId: number; row: CompetitorRow; onClose: () => void }) {
    // Seeded from the row: an empty initial value would silently clear a label
    // for anyone who opened the dialog and pressed Save without typing.
    const [label, setLabel] = useState(row.label ?? '');

    return (
        <Modal
            open
            onClose={onClose}
            title={`Label for ${row.domain}`}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        onClick={() => {
                            router.patch(
                                `/projects/${projectId}/competitors/${row.id}`,
                                { label },
                                { preserveScroll: true, only: ['competitors', 'flash', 'errors'], onSuccess: onClose },
                            );
                        }}
                    >
                        Save label
                    </Button>
                </>
            }
        >
            <Input
                label="Label"
                autoFocus
                value={label}
                placeholder="Main rival"
                hint="Shown under the domain. Leave it empty to remove the label."
                onChange={(event) => setLabel(event.target.value)}
            />
        </Modal>
    );
}

function RemoveDialog({
    projectId,
    row,
    onClose,
}: {
    projectId: number;
    row: CompetitorRow | null;
    onClose: () => void;
}) {
    if (row === null) return null;

    return (
        <Modal
            open
            onClose={onClose}
            title={`Remove ${row.domain}?`}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        variant="danger"
                        onClick={() =>
                            router.delete(`/projects/${projectId}/competitors/${row.id}`, {
                                preserveScroll: true,
                                only: ['competitors', 'flash', 'errors'],
                                onSuccess: onClose,
                            })
                        }
                    >
                        Remove competitor
                    </Button>
                </>
            }
        >
            <p>
                Its measurements go with it, and adding the domain back later starts a fresh set. This frees one of the
                project’s competitor slots.
            </p>
        </Modal>
    );
}
