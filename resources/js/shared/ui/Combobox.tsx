import { useMemo, useState, useId, type KeyboardEvent } from 'react';
import { cn } from '@shared/lib/cn';
import { controlBase, controlTone, describedBy, Field } from './Field';
import { CheckIcon, ChevronDownIcon } from './icons';
import { useDismiss } from './usePopover';

export interface ComboboxOption {
    value: string;
    label: string;
    /** Quiet second line, e.g. a domain's category. */
    meta?: string;
    disabled?: boolean;
}

export interface ComboboxProps {
    label: string;
    options: ComboboxOption[];
    value: string | null;
    onChange: (value: string | null) => void;
    placeholder?: string;
    hint?: string;
    error?: string;
    disabled?: boolean;
    loading?: boolean;
    className?: string;
}

/**
 * Single-select with type-to-filter. Follows the ARIA combobox pattern: the
 * input owns the listbox, arrow keys move the active option, Enter commits it
 * and Escape closes without changing the value.
 */
export function Combobox({
    label,
    options,
    value,
    onChange,
    placeholder = 'Search…',
    hint,
    error,
    disabled,
    loading = false,
    className,
}: ComboboxProps) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);

    const selected = options.find((option) => option.value === value) ?? null;

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (needle === '') return options;

        return options.filter(
            (option) =>
                option.label.toLowerCase().includes(needle) || (option.meta?.toLowerCase().includes(needle) ?? false),
        );
    }, [options, query]);

    const ref = useDismiss<HTMLDivElement>(open, () => {
        setOpen(false);
        setQuery('');
    });

    function commit(option: ComboboxOption) {
        if (option.disabled) return;
        onChange(option.value);
        setOpen(false);
        setQuery('');
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!open) {
                setOpen(true);
                return;
            }
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActive((current) => (current + step + filtered.length) % Math.max(filtered.length, 1));
        } else if (event.key === 'Enter') {
            if (!open) return;
            event.preventDefault();
            const option = filtered[active];
            if (option) commit(option);
        } else if (event.key === 'Escape') {
            setOpen(false);
            setQuery('');
        }
    }

    return (
        <Field id={id} label={label} hint={hint} error={error} className={className}>
            <div ref={ref} className="relative">
                <input
                    id={id}
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={`${id}-listbox`}
                    aria-autocomplete="list"
                    aria-activedescendant={open && filtered[active] ? `${id}-option-${active}` : undefined}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy(id, hint, error)}
                    autoComplete="off"
                    disabled={disabled}
                    placeholder={selected ? selected.label : placeholder}
                    value={open ? query : (selected?.label ?? '')}
                    onFocus={() => setOpen(true)}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActive(0);
                        setOpen(true);
                    }}
                    onKeyDown={onKeyDown}
                    className={cn(controlBase, controlTone(Boolean(error)), 'h-9 px-3 pr-9')}
                />

                <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-500">
                    <ChevronDownIcon size={16} />
                </span>

                {open && (
                    <ul
                        id={`${id}-listbox`}
                        role="listbox"
                        className="absolute z-40 mt-1 max-h-64 w-full animate-fade-in overflow-auto rounded-card border border-subtle bg-card py-1 shadow-card"
                    >
                        {loading && <li className="px-3 py-2 text-base text-ink-500">Loading…</li>}

                        {!loading && filtered.length === 0 && (
                            <li className="px-3 py-2 text-base text-ink-500">No matches. Try a shorter search.</li>
                        )}

                        {!loading &&
                            filtered.map((option, index) => {
                                const isSelected = option.value === value;

                                return (
                                    <li
                                        key={option.value}
                                        id={`${id}-option-${index}`}
                                        role="option"
                                        aria-selected={isSelected}
                                        aria-disabled={option.disabled}
                                        onPointerDown={(event) => {
                                            event.preventDefault();
                                            commit(option);
                                        }}
                                        onMouseEnter={() => setActive(index)}
                                        className={cn(
                                            'flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-base',
                                            index === active ? 'bg-brand-subtle' : 'bg-card',
                                            option.disabled && 'cursor-not-allowed opacity-50',
                                        )}
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-ink-900">{option.label}</span>
                                            {option.meta && (
                                                <span className="block truncate text-sm text-ink-500">
                                                    {option.meta}
                                                </span>
                                            )}
                                        </span>
                                        {isSelected && <CheckIcon size={16} className="shrink-0 text-brand" />}
                                    </li>
                                );
                            })}
                    </ul>
                )}
            </div>
        </Field>
    );
}
