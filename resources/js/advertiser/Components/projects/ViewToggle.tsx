import { cn } from '@shared/lib/cn';

interface Props {
    value: 'table' | 'cards';
    onChange: (value: 'table' | 'cards') => void;
}

/** Two layouts of the same data. The choice is stored per account. */
export function ViewToggle({ value, onChange }: Props) {
    return (
        <div
            role="group"
            aria-label="Layout"
            className="inline-flex rounded-card border border-subtle bg-card p-0.5 shadow-card"
        >
            {(
                [
                    ['table', 'Table', <RowsIcon key="t" />],
                    ['cards', 'Cards', <GridIcon key="c" />],
                ] as const
            ).map(([id, label, icon]) => (
                <button
                    key={id}
                    type="button"
                    aria-pressed={value === id}
                    onClick={() => onChange(id)}
                    title={`${label} view`}
                    className={cn(
                        'inline-flex items-center gap-1.5 rounded-button px-2.5 py-1.5 text-sm font-medium',
                        'transition-colors duration-fast ease-standard',
                        value === id ? 'bg-brand-subtle text-brand' : 'text-ink-500 hover:bg-sunken hover:text-ink-700',
                    )}
                >
                    {icon}
                    <span className="sr-only sm:not-sr-only">{label}</span>
                </button>
            ))}
        </div>
    );
}

function RowsIcon() {
    return (
        <svg
            width={14}
            height={14}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            aria-hidden="true"
        >
            <path d="M3 6h18M3 12h18M3 18h18" />
        </svg>
    );
}

function GridIcon() {
    return (
        <svg
            width={14}
            height={14}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
            <rect x="14" y="14" width="7" height="7" rx="1.5" />
        </svg>
    );
}
