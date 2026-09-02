import { cn } from '@shared/lib/cn';
import { GlobeIcon, Skeleton } from '@shared/ui';
import type { SitePreview } from '@shared/types/wizard';

interface Props {
    preview: SitePreview | null;
    loading: boolean;
}

/**
 * Shows what we found at the address, so a typo is caught here rather than
 * three steps later.
 *
 * Every field is rendered as text. The title and description come from a site
 * Publinza does not control, and the favicon is a URL that site chose — so it
 * is loaded as an image with a glyph fallback and never inlined.
 */
export function SitePreviewCard({ preview, loading }: Props) {
    if (loading) {
        return (
            <div className="mt-2 flex items-start gap-3 rounded-card border border-subtle bg-sunken p-3">
                <Skeleton width="w-8" height="h-8" shape="block" />
                <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <Skeleton width="w-48" height="h-4" />
                    <Skeleton width="w-full" height="h-3" />
                </span>
            </div>
        );
    }

    if (preview === null) return null;

    if (!preview.ok) {
        return (
            <p className="mt-2 rounded-card bg-warning-bg px-3 py-2 text-sm text-warning">
                {preview.reason ?? 'We could not read that site.'} You can carry on — the preview is only a check that
                you typed the right address.
            </p>
        );
    }

    return (
        <div className="mt-2 flex items-start gap-3 rounded-card border border-subtle bg-sunken p-3">
            {preview.favicon ? (
                <img
                    src={preview.favicon}
                    alt=""
                    width={32}
                    height={32}
                    loading="lazy"
                    referrerPolicy="no-referrer"
                    className="size-8 shrink-0 rounded-[6px] bg-card object-contain"
                    onError={(event) => {
                        event.currentTarget.style.display = 'none';
                    }}
                />
            ) : (
                <span className="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-card text-ink-300">
                    <GlobeIcon size={16} />
                </span>
            )}

            <span className="min-w-0 flex-1">
                <span className={cn('block truncate text-sm font-medium text-ink-900')}>
                    {preview.title ?? preview.host}
                </span>
                <span className="block truncate text-xs text-ink-500">{preview.host}</span>
                {preview.description && (
                    <span className="mt-1 line-clamp-2 block text-xs text-ink-500">{preview.description}</span>
                )}
            </span>
        </div>
    );
}
