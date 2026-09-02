import { useId, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { number } from '@shared/lib/format';
import type { ProjectPostMix } from '@shared/types/projects';

interface Props {
    mix: ProjectPostMix;
    /** A compact rail under a table cell, or full width in a card. */
    size?: 'micro' | 'full';
}

/**
 * The four named segments are disjoint by status, and a fifth carries
 * everything else, so the widths always sum to the total printed above them. A
 * stacked bar whose segments overlap — or that silently drops four of the nine
 * statuses — describes a population that does not exist.
 *
 * Frozen means live but still inside its verification window, which is why it
 * sits beside Posted rather than slicing through it: what the bar distinguishes
 * is whether the money has settled.
 *
 * Colour is never the only encoding. Each segment names itself and its count on
 * hover, the bar has a text alternative, and the same counts are printed as
 * their own columns beside it.
 */
const SEGMENTS: { key: keyof ProjectPostMix; label: string; token: string }[] = [
    { key: 'new', label: 'New', token: 'var(--status-new-fg)' },
    { key: 'progress', label: 'In progress', token: 'var(--status-progress-fg)' },
    { key: 'posted', label: 'Posted', token: 'var(--status-posted-fg)' },
    { key: 'frozen', label: 'Frozen', token: 'var(--status-frozen-fg)' },
    { key: 'other', label: 'Other', token: 'var(--ink-300)' },
];

export function PostMixBar({ mix, size = 'micro' }: Props) {
    const [hovered, setHovered] = useState<keyof ProjectPostMix | null>(null);
    const id = useId();

    if (mix.total === 0) {
        return (
            <div
                role="img"
                aria-label="No posts yet"
                className={cn('rounded-pill bg-sunken', size === 'micro' ? 'h-1.5 w-24' : 'h-2 w-full')}
            />
        );
    }

    const visible = SEGMENTS.filter((segment) => mix[segment.key] > 0);

    return (
        <div className="relative">
            <div
                role="img"
                aria-label={visible.map((s) => `${s.label}: ${mix[s.key]}`).join(', ')}
                className={cn(
                    'flex gap-px overflow-hidden rounded-pill',
                    size === 'micro' ? 'h-1.5 w-24' : 'h-2 w-full',
                )}
            >
                {visible.map((segment) => (
                    <span
                        key={segment.key}
                        aria-hidden="true"
                        onMouseEnter={() => setHovered(segment.key)}
                        onMouseLeave={() => setHovered(null)}
                        style={{
                            width: `${(mix[segment.key] / mix.total) * 100}%`,
                            backgroundColor: segment.token,
                        }}
                        className={cn(
                            'h-full first:rounded-l-pill last:rounded-r-pill',
                            'transition-opacity duration-fast ease-standard',
                            hovered !== null && hovered !== segment.key && 'opacity-30',
                        )}
                    />
                ))}
            </div>

            {hovered !== null && (
                <span
                    id={id}
                    role="tooltip"
                    className={cn(
                        'pointer-events-none absolute -top-8 left-0 z-30 whitespace-nowrap rounded-card',
                        'bg-ink-900 px-2 py-1 text-xs text-white shadow-card',
                    )}
                >
                    {SEGMENTS.find((s) => s.key === hovered)?.label}:{' '}
                    <span className="num">{number(mix[hovered])}</span>
                </span>
            )}
        </div>
    );
}
