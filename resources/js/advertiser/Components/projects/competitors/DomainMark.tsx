import { cn } from '@shared/lib/cn';

/**
 * The 20px mark beside a domain.
 *
 * Not a favicon. Fetching one means asking a third party for an icon and
 * telling them, on every page load, which rivals this advertiser is tracking —
 * a list that is some of the most commercially sensitive information in the
 * product. The dashboard already made this call for a weaker case: the sites an
 * advertiser buys on. Whose competitors they watch is a stronger one.
 *
 * So it is a monogram, in the same slot at the same size a favicon would fill,
 * and nothing shifts if Publinza ever serves its own marks.
 */
export function DomainMark({ domain, tone = 'default' }: { domain: string; tone?: 'default' | 'brand' }) {
    const initial = (domain.replace(/^www\./, '')[0] ?? '?').toUpperCase();

    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex size-5 shrink-0 items-center justify-center rounded-[4px] text-xs font-semibold',
                tone === 'brand' ? 'bg-brand text-white' : 'bg-sunken text-ink-700',
            )}
        >
            {initial}
        </span>
    );
}
