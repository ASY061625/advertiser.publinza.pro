import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { Button, DownloadIcon, FolderIcon, Select, TrashIcon, XIcon } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { ProjectOption } from '@shared/types/posts';

interface Props {
    count: number;
    projects: ProjectOption[];
    onClear: () => void;
    onExport: () => void;
    onDownload: () => void;
    onCancel: () => void;
    onMove: (folderId: number | null) => void;
}

/**
 * The sticky selection bar.
 *
 * Fixed to the bottom of the viewport rather than pinned under the table: the
 * grid is 100 rows tall and the selection is made by scrolling, so a bar at the
 * end of the list would be off screen exactly when it is needed.
 */
export function BulkBar({ count, projects, onClear, onExport, onDownload, onCancel, onMove }: Props) {
    const [moving, setMoving] = useState(false);
    const [folderId, setFolderId] = useState('');

    if (count === 0) return null;

    const folders = projects.flatMap((project) =>
        project.folders.map((folder) => ({
            value: String(folder.id),
            label: `${project.name} / ${folder.name}`,
        })),
    );

    return (
        <div
            role="region"
            aria-label="Bulk actions for selected posts"
            className={cn(
                'fixed inset-x-0 bottom-0 z-40 border-t border-subtle bg-card px-4 py-3 shadow-card',
                'lg:pl-sidebar',
            )}
        >
            <div className="mx-auto flex max-w-content flex-wrap items-center gap-3">
                <span className="num text-base font-medium text-ink-900">{number(count)} selected</span>

                <button
                    type="button"
                    onClick={onClear}
                    className="inline-flex items-center gap-1 rounded-button px-2 py-1 text-sm text-ink-500 hover:bg-sunken hover:text-ink-700"
                >
                    <XIcon size={12} />
                    Clear
                </button>

                <span className="ml-auto flex flex-wrap items-center gap-2">
                    {moving ? (
                        <span className="flex items-end gap-2">
                            <Select
                                label="Move to folder"
                                hideLabel
                                className="w-64"
                                value={folderId}
                                onChange={(event) => setFolderId(event.target.value)}
                                options={[{ value: '', label: 'No folder' }, ...folders]}
                            />
                            <Button
                                onClick={() => {
                                    onMove(folderId === '' ? null : Number(folderId));
                                    setMoving(false);
                                }}
                            >
                                Move
                            </Button>
                            <Button variant="ghost" onClick={() => setMoving(false)}>
                                Cancel
                            </Button>
                        </span>
                    ) : (
                        <>
                            <Button variant="secondary" onClick={onExport}>
                                <DownloadIcon size={14} />
                                Export selected
                            </Button>
                            <Button variant="secondary" onClick={onDownload}>
                                <DownloadIcon size={14} />
                                Download articles
                            </Button>
                            <Button variant="secondary" onClick={() => setMoving(true)}>
                                <FolderIcon size={14} />
                                Move to folder
                            </Button>
                            <Button variant="danger" onClick={onCancel}>
                                <TrashIcon size={14} />
                                Cancel posts
                            </Button>
                        </>
                    )}
                </span>
            </div>
        </div>
    );
}
