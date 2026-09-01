import { cn } from '@shared/lib/cn';

export interface SkeletonProps {
    /** Tailwind width class, e.g. `w-32`. */
    width?: string;
    height?: string;
    shape?: 'line' | 'block' | 'circle';
    className?: string;
}

/**
 * A loading placeholder. The shimmer is a background animation rather than a
 * transform, so `prefers-reduced-motion` flattens it to a static block via the
 * global rule in globals.css.
 */
export function Skeleton({ width = 'w-full', height = 'h-4', shape = 'line', className }: SkeletonProps) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'block animate-shimmer bg-[length:200%_100%]',
                'bg-[linear-gradient(90deg,var(--surface-sunken)_25%,#e3ebf6_37%,var(--surface-sunken)_63%)]',
                shape === 'circle' ? 'rounded-pill' : shape === 'block' ? 'rounded-card' : 'rounded-pill',
                width,
                height,
                className,
            )}
        />
    );
}

/** Convenience: a block of stacked lines for text placeholders. */
export function SkeletonText({ lines = 3 }: { lines?: number }) {
    return (
        <span className="flex flex-col gap-2">
            {Array.from({ length: lines }, (_, i) => (
                <Skeleton key={i} width={i === lines - 1 ? 'w-2/3' : 'w-full'} />
            ))}
        </span>
    );
}
