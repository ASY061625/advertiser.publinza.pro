import { cn } from '@shared/lib/cn';

/**
 * The product's status vocabulary. These eight values and their colours are
 * fixed across every surface, advertiser and admin alike — a "Posted" chip is
 * the same chip in the catalog, the project drawer and the admin order table.
 */
export type StatusKey =
    'draft' | 'new' | 'in_progress' | 'content_review' | 'posted' | 'frozen' | 'rejected' | 'refunded';

const STATUS: Record<StatusKey, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-status-draft-bg text-status-draft-fg' },
    new: { label: 'New', className: 'bg-status-new-bg text-status-new-fg' },
    in_progress: { label: 'In progress', className: 'bg-status-progress-bg text-status-progress-fg' },
    content_review: { label: 'Content review', className: 'bg-status-review-bg text-status-review-fg' },
    posted: { label: 'Posted', className: 'bg-status-posted-bg text-status-posted-fg' },
    frozen: { label: 'Frozen', className: 'bg-status-frozen-bg text-status-frozen-fg' },
    rejected: { label: 'Rejected', className: 'bg-status-rejected-bg text-status-rejected-fg' },
    refunded: { label: 'Refunded', className: 'bg-status-refunded-bg text-status-refunded-fg' },
};

export const STATUS_KEYS = Object.keys(STATUS) as StatusKey[];

export function Badge({ status, className }: { status: StatusKey; className?: string }) {
    const { label, className: tone } = STATUS[status];

    return (
        <span
            className={cn(
                'inline-flex items-center whitespace-nowrap rounded-pill px-2.5 py-1 text-xs font-medium',
                tone,
                className,
            )}
        >
            {label}
        </span>
    );
}
