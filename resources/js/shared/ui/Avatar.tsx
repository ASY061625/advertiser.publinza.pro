import { useState } from 'react';
import { cn } from '@shared/lib/cn';

export interface AvatarProps {
    name: string;
    src?: string;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

const SIZES = { sm: 'size-6 text-xs', md: 'size-8 text-sm', lg: 'size-11 text-md' } as const;

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

/** Falls back to initials when there is no image, or when the image fails. */
export function Avatar({ name, src, size = 'md', className }: AvatarProps) {
    const [failed, setFailed] = useState(false);

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-pill',
                'bg-brand-subtle font-sora font-medium text-brand',
                SIZES[size],
                className,
            )}
            title={name}
        >
            {src && !failed ? (
                <img src={src} alt={name} className="size-full object-cover" onError={() => setFailed(true)} />
            ) : (
                <span aria-hidden="true">{initials(name)}</span>
            )}
            <span className="sr-only">{name}</span>
        </span>
    );
}
