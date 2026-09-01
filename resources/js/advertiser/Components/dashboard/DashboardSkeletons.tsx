import { Skeleton } from '@shared/ui';

/**
 * Per-panel skeletons, shaped like the thing that replaces them.
 *
 * They are only ever shown for a range change, never on first paint — the page
 * arrives with its first payload already inlined. Each one keeps the panel's
 * height so changing the range does not make the page jump.
 */

export function StatCardSkeleton() {
    return (
        <div className="rounded-card border border-subtle bg-card p-5 shadow-card">
            <Skeleton width="w-24" height="h-3.5" />
            <Skeleton width="w-28" height="h-8" className="mt-3" />
            <Skeleton width="w-20" height="h-5" className="mt-3" />
        </div>
    );
}

export function ChartSkeleton() {
    // Bar heights are fixed, not random: a skeleton that reshuffles on every
    // render reads as loading noise rather than as a placeholder.
    const bars = [42, 68, 55, 80, 61, 74, 48, 88, 66, 52, 79, 60];

    return (
        <div>
            <div className="flex h-[132px] items-end gap-2" aria-hidden="true">
                {bars.map((height, i) => (
                    // The height is per-bar data, so it is an inline style —
                    // Tailwind cannot see a class name built at runtime.
                    <span key={i} className="flex-1" style={{ height: `${height}%` }}>
                        <Skeleton width="w-full" height="h-full" shape="block" />
                    </span>
                ))}
            </div>
            <Skeleton width="w-full" height="h-[108px]" shape="block" className="mt-6" />
        </div>
    );
}

export function StatusSkeleton() {
    return (
        <div>
            <Skeleton width="w-full" height="h-2.5" />
            <div className="mt-4 flex flex-col gap-3">
                {Array.from({ length: 5 }, (_, i) => (
                    <div key={i} className="flex items-center gap-3 px-2">
                        <Skeleton width="w-2.5" height="h-2.5" shape="circle" />
                        <Skeleton width="w-24" height="h-5" />
                        <Skeleton width="w-8" height="h-3.5" className="ml-auto" />
                        <Skeleton width="w-8" height="h-3.5" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export function RowsSkeleton({ rows = 5 }: { rows?: number }) {
    return (
        <div className="flex flex-col gap-3">
            {Array.from({ length: rows }, (_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton width="w-4" height="h-4" shape="circle" />
                    <Skeleton width={i % 2 === 0 ? 'w-40' : 'w-32'} height="h-4" />
                    <Skeleton width="w-16" height="h-4" className="ml-auto" />
                </div>
            ))}
        </div>
    );
}

export function TableSkeleton({ rows = 8 }: { rows?: number }) {
    return (
        <div className="flex flex-col gap-3">
            {Array.from({ length: rows }, (_, i) => (
                <div key={i} className="flex items-center gap-4">
                    <Skeleton width="w-4" height="h-4" shape="circle" />
                    <Skeleton width="w-36" height="h-4" />
                    <Skeleton width="w-24" height="h-4" />
                    <Skeleton width="w-40" height="h-4" />
                    <Skeleton width="w-20" height="h-5" className="ml-auto" />
                    <Skeleton width="w-16" height="h-4" />
                </div>
            ))}
        </div>
    );
}
