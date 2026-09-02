import { cn } from '@shared/lib/cn';
import { number } from '@shared/lib/format';

interface Props {
    tabs: { value: string; label: string; badge: string }[];
    counts: Record<string, number>;
    value: string;
    onChange: (value: string) => void;
}

/**
 * The status tabs, each wearing its own phase's colour as the underline.
 *
 * The colour is the only thing that varies, and it is never the only signal:
 * the active tab is also the one with ink-900 text and a count in a filled
 * pill, so the selection survives a greyscale screen and a colour-blind reader.
 *
 * Counts come from the server under the current filters, so they answer "how
 * many would I see if I clicked here" rather than "how many exist".
 */
export function StatusTabs({ tabs, counts, value, onChange }: Props) {
    return (
        <div
            role="tablist"
            aria-label="Filter posts by status"
            className="-mb-px flex items-center gap-1 overflow-x-auto border-b border-subtle"
        >
            {tabs.map((tab) => {
                const selected = tab.value === value;
                const count = counts[tab.value] ?? 0;

                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() => onChange(tab.value)}
                        style={selected ? { borderBottomColor: underline(tab.badge) } : undefined}
                        className={cn(
                            'flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 pb-2.5 pt-2',
                            'text-base font-medium transition-colors duration-fast ease-standard',
                            selected
                                ? 'text-ink-900'
                                : 'border-b-transparent text-ink-500 hover:bg-sunken hover:text-ink-700',
                        )}
                    >
                        {tab.label}
                        <span
                            className={cn(
                                'num rounded-pill px-1.5 py-0.5 text-xs tabular-nums',
                                selected ? 'bg-sunken text-ink-700' : 'text-ink-500',
                            )}
                        >
                            {number(count)}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

/** The All tab has no status of its own, so it wears the brand. */
function underline(badge: string): string {
    if (badge === 'all') return 'var(--brand-blue)';

    const token = badge === 'in_progress' ? 'progress' : badge === 'content_review' ? 'review' : badge;

    return `var(--status-${token}-fg)`;
}
