import { Skeleton } from '@shared/ui';

/**
 * Eight rows, shaped like the grid they replace, so nothing shifts when the
 * data lands. Shown only for a filter or page change — the first render
 * arrives with rows already in it.
 */
export function PostsSkeleton({ columns }: { columns: number }) {
    return (
        <div className="overflow-hidden rounded-card border border-subtle bg-card shadow-card">
            {Array.from({ length: 8 }, (_, row) => (
                <div key={row} className="flex items-center gap-4 border-b border-subtle px-3 py-3 last:border-0">
                    <Skeleton width="w-4" height="h-4" />
                    <Skeleton width="w-6" height="h-4" />
                    <span className="flex w-48 flex-col gap-1.5">
                        <Skeleton width="w-40" height="h-4" />
                        <Skeleton width="w-24" height="h-3" />
                    </span>
                    {Array.from({ length: Math.max(1, columns - 2) }, (_, cell) => (
                        <Skeleton key={cell} width={cell % 2 === 0 ? 'w-28' : 'w-20'} height="h-4" />
                    ))}
                </div>
            ))}
        </div>
    );
}
