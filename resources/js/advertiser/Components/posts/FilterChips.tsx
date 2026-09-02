import { XIcon } from '@shared/ui';
import { money, number } from '@shared/lib/format';
import type { PostFilterState, PostOptions } from '@shared/types/posts';

interface Props {
    filters: PostFilterState;
    options: PostOptions;
    onChange: (patch: Partial<PostFilterState>) => void;
    onClearAll: () => void;
}

interface Chip {
    /** Unique per chip, since one key can produce several. */
    key: string;
    label: string;
    clear: Partial<PostFilterState>;
}

/**
 * Every active filter, as something you can read and remove.
 *
 * The advanced disclosure can be closed over thirteen set filters, and a
 * shared link arrives with filters nobody in this browser chose. The chips are
 * what stops the grid from silently hiding rows for a reason that is not on
 * screen — so they name the value, not just the field: "DR 40–80", not "DR".
 */
export function FilterChips({ filters, options, onChange, onClearAll }: Props) {
    const chips = build(filters, options);

    if (chips.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-2">
            {chips.map((chip) => (
                <span
                    key={chip.key}
                    className="inline-flex items-center gap-1.5 rounded-pill bg-brand-subtle py-1 pl-3 pr-1.5 text-sm text-brand"
                >
                    {chip.label}
                    <button
                        type="button"
                        onClick={() => onChange(chip.clear)}
                        aria-label={`Remove filter: ${chip.label}`}
                        className="rounded-pill p-0.5 transition-colors duration-fast hover:bg-brand hover:text-white"
                    >
                        <XIcon size={12} />
                    </button>
                </span>
            ))}

            <button
                type="button"
                onClick={onClearAll}
                className="ml-1 text-sm font-medium text-ink-500 underline underline-offset-2 hover:text-ink-700"
            >
                Clear all
            </button>
        </div>
    );
}

function build(filters: PostFilterState, options: PostOptions): Chip[] {
    const chips: Chip[] = [];
    const nameOf = (list: { id: number; name: string }[], id: number) =>
        list.find((item) => item.id === id)?.name ?? `#${id}`;

    if (filters.q) {
        chips.push({ key: 'q', label: `Search: “${filters.q}”`, clear: { q: undefined } });
    }

    // Each selected value is its own chip. One "3 projects" chip would force
    // someone to reopen the control to find out which three.
    const lists: [keyof PostFilterState, string, { id: number; name: string }[]][] = [
        ['projects', 'Project', options.projects],
        ['categories', 'Category', options.categories],
        ['countries', 'Country', options.countries],
        ['languages', 'Language', options.languages],
    ];

    for (const [key, prefix, source] of lists) {
        const values = (filters[key] as number[] | undefined) ?? [];

        for (const id of values) {
            chips.push({
                key: `${key}-${id}`,
                label: `${prefix}: ${nameOf(source, id)}`,
                clear: { [key]: values.filter((value) => value !== id) },
            });
        }
    }

    for (const status of filters.statuses ?? []) {
        chips.push({
            key: `status-${status}`,
            label: `Status: ${options.statuses.find((s) => s.value === status)?.label ?? status}`,
            clear: { statuses: (filters.statuses ?? []).filter((value) => value !== status) },
        });
    }

    if (filters.from || filters.to) {
        const field = filters.date_field === 'published' ? 'Published' : 'Created';
        const from = filters.from ?? 'any';
        const to = filters.to ?? 'now';

        chips.push({
            key: 'dates',
            label: `${field}: ${from} → ${to}`,
            clear: { from: undefined, to: undefined },
        });
    }

    if (filters.min_price !== undefined || filters.max_price !== undefined) {
        chips.push({
            key: 'price',
            label: `Price: ${range(filters.min_price, filters.max_price, (v) => money(v * 100))}`,
            clear: { min_price: undefined, max_price: undefined },
        });
    }

    if (filters.min_dr !== undefined || filters.max_dr !== undefined) {
        chips.push({
            key: 'dr',
            label: `DR: ${range(filters.min_dr, filters.max_dr, String)}`,
            clear: { min_dr: undefined, max_dr: undefined },
        });
    }

    if (filters.min_traffic !== undefined || filters.max_traffic !== undefined) {
        chips.push({
            key: 'traffic',
            label: `Traffic: ${range(filters.min_traffic, filters.max_traffic, number)}`,
            clear: { min_traffic: undefined, max_traffic: undefined },
        });
    }

    if (filters.content_mode) {
        chips.push({
            key: 'content_mode',
            label: filters.content_mode === 'advertiser_provides' ? 'I provide the article' : 'Publisher writes it',
            clear: { content_mode: undefined },
        });
    }

    if (filters.anchor) {
        chips.push({ key: 'anchor', label: `Anchor contains “${filters.anchor}”`, clear: { anchor: undefined } });
    }

    if (filters.target) {
        chips.push({ key: 'target', label: `Target URL contains “${filters.target}”`, clear: { target: undefined } });
    }

    if (filters.folder) {
        const folder = options.projects
            .flatMap((project) => project.folders)
            .find((item) => item.id === filters.folder);

        chips.push({
            key: 'folder',
            label: `Folder: ${folder?.name ?? `#${filters.folder}`}`,
            clear: { folder: undefined },
        });
    }

    if (filters.unread === 1) {
        chips.push({ key: 'unread', label: 'Has unread messages', clear: { unread: undefined } });
    }

    if (filters.deadline) {
        const labels: Record<string, string> = {
            '24h': 'Due within 24 hours',
            '3d': 'Due within 3 days',
            '7d': 'Due within 7 days',
            overdue: 'Overdue',
        };

        chips.push({ key: 'deadline', label: labels[filters.deadline] ?? '', clear: { deadline: undefined } });
    }

    return chips;
}

/** "$100–$500", "from $100", "up to $500" — never a bare bound with no sense. */
function range(min: number | undefined, max: number | undefined, format: (value: number) => string): string {
    if (min !== undefined && max !== undefined) return `${format(min)}–${format(max)}`;

    return min !== undefined ? `from ${format(min)}` : `up to ${format(max ?? 0)}`;
}
