import { useMemo, useState } from 'react';
import { Checkbox, Input, SearchIcon } from '@shared/ui';
import { number } from '@shared/lib/format';
import { cn } from '@shared/lib/cn';
import type { FacetOption } from '@shared/types/catalog';
import { flagFor } from './flags';

interface Props {
    label: string;
    options: FacetOption[];
    selected: number[];
    onChange: (selected: number[]) => void;
    /** Renders the country flag glyph before each name. */
    showFlags?: boolean;
    /** Above this many options the list gets its own search box. */
    searchAfter?: number;
}

/** How many rows are shown before the list scrolls. */
const VISIBLE = 8;

/**
 * A searchable checkbox list with a count against every option.
 *
 * The counts are the point. They are computed against every *other* filter, so
 * a zero means "ticking this as well would give you nothing" rather than "this
 * does not exist" — which is what turns the rail from a set of switches into a
 * map of the inventory.
 *
 * Selected options are pinned to the top. Otherwise ticking something in a list
 * of two hundred countries and then typing to find the next one makes the first
 * choice disappear, and there is no way to see what is on without clearing the
 * search.
 */
export function FacetList({ label, options, selected, onChange, showFlags = false, searchAfter = 10 }: Props) {
    const [term, setTerm] = useState('');

    const visible = useMemo(() => {
        const needle = term.trim().toLowerCase();
        const matches = needle === '' ? options : options.filter((o) => o.name.toLowerCase().includes(needle));
        const chosen = new Set(selected);

        return [...matches].sort((a, b) => {
            const aOn = chosen.has(a.id) ? 0 : 1;
            const bOn = chosen.has(b.id) ? 0 : 1;

            return aOn - bOn || b.count - a.count || a.name.localeCompare(b.name);
        });
    }, [options, selected, term]);

    function toggle(id: number) {
        onChange(selected.includes(id) ? selected.filter((value) => value !== id) : [...selected, id]);
    }

    return (
        <div className="flex flex-col gap-2">
            {options.length > searchAfter && (
                <Input
                    label={`Search ${label.toLowerCase()}`}
                    hideLabel
                    type="search"
                    className="h-8"
                    placeholder={`Search ${label.toLowerCase()}`}
                    leadingIcon={<SearchIcon size={14} />}
                    value={term}
                    onChange={(event) => setTerm(event.target.value)}
                />
            )}

            <div className="flex items-center justify-between text-xs">
                <button
                    type="button"
                    className="text-brand hover:underline disabled:text-ink-500 disabled:no-underline"
                    disabled={visible.length === 0}
                    onClick={() => onChange([...new Set([...selected, ...visible.map((o) => o.id)])])}
                >
                    Select all
                </button>
                <button
                    type="button"
                    className="text-ink-500 hover:underline disabled:no-underline"
                    disabled={selected.length === 0}
                    onClick={() => onChange([])}
                >
                    Clear
                </button>
            </div>

            <ul className={cn('flex flex-col gap-1.5', visible.length > VISIBLE && 'max-h-56 overflow-y-auto pr-1')}>
                {visible.map((option) => (
                    <li key={option.id} className="flex items-center justify-between gap-2">
                        <Checkbox
                            label={
                                <span className="flex min-w-0 items-center gap-1.5">
                                    {showFlags && option.code && <span aria-hidden="true">{flagFor(option.code)}</span>}
                                    <span className="truncate">{option.name}</span>
                                </span>
                            }
                            checked={selected.includes(option.id)}
                            onChange={() => toggle(option.id)}
                        />
                        <span
                            className={cn(
                                'num shrink-0 text-xs',
                                // A zero is still shown, and shown as quieter:
                                // it says the option exists and would add
                                // nothing, which is worth knowing before
                                // ticking it.
                                option.count === 0 ? 'text-ink-300' : 'text-ink-500',
                            )}
                        >
                            {number(option.count)}
                        </span>
                    </li>
                ))}

                {visible.length === 0 && <li className="py-1 text-xs text-ink-500">Nothing matches “{term}”.</li>}
            </ul>
        </div>
    );
}
