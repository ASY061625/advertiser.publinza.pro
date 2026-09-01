import { Link } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { Badge, GlobeIcon, type StatusKey } from '@shared/ui';
import type { Deadline } from '@shared/types/dashboard';

interface Props {
    deadlines: Deadline[];
}

/**
 * Everything due in the next seven days, soonest first.
 *
 * Under 48 hours reads amber — but colour is never the whole message: those
 * rows also say "Due in 6 hours" in words, so the urgency survives a greyscale
 * print, a colour-blind reader and a screen reader alike.
 */
export function UpcomingDeadlines({ deadlines }: Props) {
    return (
        <ul className="flex flex-col gap-1">
            {deadlines.map((deadline) => (
                <li key={deadline.id}>
                    <Link
                        href={`/posts/${deadline.id}`}
                        className={cn(
                            'flex items-center gap-3 rounded-card border-l-[3px] px-3 py-2.5',
                            'transition-colors duration-fast ease-standard',
                            deadline.urgent
                                ? 'border-l-warning bg-warning-bg hover:bg-warning-bg-hover'
                                : 'border-l-transparent hover:bg-row-hover',
                        )}
                    >
                        <GlobeIcon size={16} className="shrink-0 text-ink-500" />

                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-medium text-ink-900">{deadline.domain}</span>
                            <span
                                className={cn(
                                    'block text-xs',
                                    deadline.urgent ? 'font-medium text-warning' : 'text-ink-500',
                                )}
                            >
                                {relative(deadline.deadlineAt)}
                            </span>
                        </span>

                        <Badge status={deadline.badge as StatusKey} label={deadline.statusLabel} />
                    </Link>
                </li>
            ))}
        </ul>
    );
}

/**
 * "Due in 6 hours", "Due tomorrow", "Overdue by 2 days" — the deadline in the
 * unit a person would use out loud, never a bare timestamp.
 */
function relative(iso: string | null): string {
    if (iso === null) return 'No deadline set';

    const diffMs = new Date(iso).getTime() - Date.now();
    const hours = Math.round(Math.abs(diffMs) / 3_600_000);
    const overdue = diffMs < 0;

    if (hours < 1) return overdue ? 'Overdue' : 'Due within the hour';

    if (hours < 24) {
        const unit = hours === 1 ? 'hour' : 'hours';

        return overdue ? `Overdue by ${hours} ${unit}` : `Due in ${hours} ${unit}`;
    }

    const days = Math.round(hours / 24);
    const unit = days === 1 ? 'day' : 'days';

    return overdue ? `Overdue by ${days} ${unit}` : `Due in ${days} ${unit}`;
}
