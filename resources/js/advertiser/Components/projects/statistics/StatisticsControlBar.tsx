import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { Button, ChevronDownIcon, DownloadIcon, Select, useDismiss, useToast } from '@shared/ui';
import type { RangeKey } from '@shared/types/dashboard';
import type { StatisticsGranularity } from '@shared/types/statistics';
import { DateRangeControl, type RangeSelection } from '../../dashboard/DateRangeControl';

interface Props {
    projectId: number;
    range: RangeSelection;
    rangeLabel: string;
    granularity: StatisticsGranularity;
    folderId: number | null;
    folders: { id: number; name: string }[];
    onChange: (patch: {
        range?: RangeSelection;
        granularity?: StatisticsGranularity;
        folderId?: number | null;
    }) => void;
    /** The query the export should reproduce, as the server understands it. */
    exportQuery: Record<string, string>;
}

const GRANULARITIES: { value: StatisticsGranularity; label: string }[] = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
];

const FORMATS: { id: 'csv' | 'xlsx' | 'pdf'; label: string }[] = [
    { id: 'csv', label: 'CSV' },
    { id: 'xlsx', label: 'Excel (XLSX)' },
    { id: 'pdf', label: 'PDF' },
];

/**
 * One row of controls the whole tab obeys, and the export that reproduces
 * exactly what they are showing.
 */
export function StatisticsControlBar({
    projectId,
    range,
    rangeLabel,
    granularity,
    folderId,
    folders,
    onChange,
    exportQuery,
}: Props) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
            <div className="flex flex-wrap items-end gap-3">
                <DateRangeControl
                    value={range}
                    activeLabel={rangeLabel}
                    onChange={(next) => onChange({ range: next })}
                />

                <div className="flex rounded-button border border-subtle p-0.5" role="group" aria-label="Granularity">
                    {GRANULARITIES.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            aria-pressed={granularity === option.value}
                            onClick={() => onChange({ granularity: option.value })}
                            className={cn(
                                'rounded-[4px] px-2.5 py-1.5 font-sora text-sm font-medium transition-colors duration-fast',
                                granularity === option.value
                                    ? 'bg-brand-subtle text-brand'
                                    : 'text-ink-500 hover:text-ink-700',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>

                {folders.length > 1 && (
                    <Select
                        label="Folder"
                        hideLabel
                        className="w-44"
                        value={folderId === null ? '' : String(folderId)}
                        onChange={(event) =>
                            onChange({ folderId: event.target.value === '' ? null : Number(event.target.value) })
                        }
                        options={[
                            { value: '', label: 'All folders' },
                            ...folders.map((folder) => ({ value: String(folder.id), label: folder.name })),
                        ]}
                    />
                )}
            </div>

            <ExportButton projectId={projectId} query={exportQuery} />
        </div>
    );
}

/**
 * The split button: the obvious format on the left, the others behind the
 * chevron. Exports are queued, so the answer is "we will tell you", not a file
 * — saying so at the moment of clicking is the difference between a slow
 * download and a broken button.
 */
function ExportButton({ projectId, query }: { projectId: number; query: Record<string, string> }) {
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState<string | null>(null);
    const { toast } = useToast();
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    function start(format: string) {
        setOpen(false);
        setPending(format);

        void fetch(`/projects/${projectId}/statistics/export?${new URLSearchParams(query).toString()}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ format }),
        })
            .then((response) => {
                if (!response.ok) throw new Error('rejected');

                toast({
                    tone: 'success',
                    title: `Building your ${format.toUpperCase()}.`,
                    description: 'We’ll notify you when it’s ready. The download link works for 24 hours.',
                });
            })
            .catch(() =>
                toast({
                    tone: 'danger',
                    title: 'We could not start that export.',
                    description: 'Try again in a moment.',
                }),
            )
            .finally(() => setPending(null));
    }

    return (
        <div ref={ref} className="relative flex">
            <Button
                variant="secondary"
                loading={pending === 'csv'}
                onClick={() => start('csv')}
                className="rounded-r-none"
            >
                <DownloadIcon size={14} />
                Export
            </Button>

            <Button
                variant="secondary"
                aria-label="Choose an export format"
                aria-expanded={open}
                onClick={() => setOpen((value) => !value)}
                className="-ml-px rounded-l-none px-2"
            >
                <ChevronDownIcon size={14} />
            </Button>

            {open && (
                <div
                    role="menu"
                    className="absolute right-0 top-full z-40 mt-1 min-w-44 animate-scale-in overflow-hidden rounded-card border border-subtle bg-card py-1 shadow-card"
                >
                    {FORMATS.map((format) => (
                        <button
                            key={format.id}
                            type="button"
                            role="menuitem"
                            onClick={() => start(format.id)}
                            className="flex w-full items-center px-3 py-2 text-left text-base text-ink-700 transition-colors duration-fast hover:bg-sunken"
                        >
                            {format.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export type { RangeKey };
