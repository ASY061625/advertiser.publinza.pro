import type { StatusKey } from '@shared/types';

/** Status colours are fixed across the whole product, advertiser and admin. */
export const STATUS_STYLES: Record<StatusKey, { label: string; fill: string; text: string }> = {
    draft: { label: 'Draft', fill: '#EDF2F9', text: 'var(--ink-500)' },
    new: { label: 'New', fill: 'var(--info-bg)', text: 'var(--brand-blue)' },
    in_progress: { label: 'In progress', fill: 'var(--gold-50)', text: '#B45309' },
    content_review: { label: 'Content review', fill: '#F3E8FF', text: '#7E22CE' },
    published: { label: 'Published', fill: 'var(--teal-50)', text: 'var(--success)' },
    frozen: { label: 'Frozen', fill: '#E2E8F0', text: 'var(--ink-700)' },
    rejected: { label: 'Rejected', fill: 'var(--danger-bg)', text: 'var(--danger)' },
    refunded: { label: 'Refunded', fill: '#FFF8EB', text: '#92400E' },
};
