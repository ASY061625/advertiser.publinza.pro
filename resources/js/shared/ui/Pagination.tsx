import { cn } from '@shared/lib/cn';
import { IconButton } from './IconButton';
import { ChevronLeftIcon, ChevronRightIcon } from './icons';

export interface PaginationProps {
    page: number;
    pageCount: number;
    total?: number;
    perPage?: number;
    onPageChange: (page: number) => void;
    className?: string;
}

/** Windowed page numbers with ellipses, so the control stays one line wide. */
function pageWindow(page: number, pageCount: number): (number | 'gap')[] {
    if (pageCount <= 7) return Array.from({ length: pageCount }, (_, i) => i + 1);

    const pages = new Set<number>([1, pageCount, page, page - 1, page + 1]);
    const sorted = [...pages].filter((p) => p >= 1 && p <= pageCount).sort((a, b) => a - b);

    const out: (number | 'gap')[] = [];
    let previous = 0;

    for (const p of sorted) {
        if (previous && p - previous > 1) out.push('gap');
        out.push(p);
        previous = p;
    }

    return out;
}

export function Pagination({ page, pageCount, total, perPage, onPageChange, className }: PaginationProps) {
    if (pageCount <= 1) return null;

    const from = perPage ? (page - 1) * perPage + 1 : null;
    const to = perPage && total ? Math.min(page * perPage, total) : null;

    return (
        <nav aria-label="Pagination" className={cn('flex flex-wrap items-center justify-between gap-3', className)}>
            {total !== undefined && from !== null && to !== null ? (
                <p className="num text-sm text-ink-500">
                    {from.toLocaleString('en-US')}–{to.toLocaleString('en-US')} of {total.toLocaleString('en-US')}
                </p>
            ) : (
                <span />
            )}

            <div className="flex items-center gap-1">
                <IconButton
                    label="Previous page"
                    size="sm"
                    variant="secondary"
                    icon={<ChevronLeftIcon size={16} />}
                    disabled={page <= 1}
                    onClick={() => onPageChange(page - 1)}
                />

                {pageWindow(page, pageCount).map((entry, index) =>
                    entry === 'gap' ? (
                        <span key={`gap-${index}`} className="px-1.5 text-ink-300" aria-hidden="true">
                            …
                        </span>
                    ) : (
                        <button
                            key={entry}
                            type="button"
                            aria-current={entry === page ? 'page' : undefined}
                            onClick={() => onPageChange(entry)}
                            className={cn(
                                'num size-8 rounded-button text-sm transition-colors duration-fast ease-standard',
                                entry === page ? 'bg-brand font-medium text-white' : 'text-ink-700 hover:bg-sunken',
                            )}
                        >
                            {entry}
                        </button>
                    ),
                )}

                <IconButton
                    label="Next page"
                    size="sm"
                    variant="secondary"
                    icon={<ChevronRightIcon size={16} />}
                    disabled={page >= pageCount}
                    onClick={() => onPageChange(page + 1)}
                />
            </div>
        </nav>
    );
}
