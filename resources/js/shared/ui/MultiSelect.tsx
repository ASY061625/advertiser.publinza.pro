import { useMemo, useState, useId, type KeyboardEvent } from 'react';
import { cn } from '@shared/lib/cn';
import { controlBase, controlTone, describedBy, Field } from './Field';
import { CheckIcon, ChevronDownIcon, SearchIcon, XIcon } from './icons';
import { useDismiss } from './usePopover';

export interface MultiSelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}

export interface MultiSelectProps {
    label: string;
    options: MultiSelectOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    hint?: string;
    error?: string;
    disabled?: boolean;
    /** Chips beyond this collapse into a "+n more" chip. */
    maxVisibleChips?: number;
    /** Hides the label visually for filter-bar use; it still announces. */
    hideLabel?: boolean;
    className?: string;
}

/**
 * Multi-select with an in-popover search and removable chips.
 *
 * Backspace on an empty search removes the last chip, which is the behaviour
 * anyone who has used a tag input expects.
 */
export function MultiSelect({
    label,
    options,
    value,
    onChange,
    placeholder = 'Select…',
    hint,
    error,
    disabled,
    maxVisibleChips = 3,
    hideLabel = false,
    className,
}: MultiSelectProps) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const ref = useDismiss<HTMLDivElement>(open, () => {
        setOpen(false);
        setQuery('');
    });

    const selected = useMemo(
        () =>
            value.map((v) => options.find((o) => o.value === v)).filter((o): o is MultiSelectOption => o !== undefined),
        [value, options],
    );

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return needle === '' ? options : options.filter((o) => o.label.toLowerCase().includes(needle));
    }, [options, query]);

    function toggle(option: MultiSelectOption) {
        if (option.disabled) return;
        onChange(value.includes(option.value) ? value.filter((v) => v !== option.value) : [...value, option.value]);
    }

    function onSearchKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'Backspace' && query === '' && value.length > 0) {
            onChange(value.slice(0, -1));
        }
    }

    const visible = selected.slice(0, maxVisibleChips);
    const overflow = selected.length - visible.length;

    return (
        <Field id={id} label={label} hint={hint} error={error} hideLabel={hideLabel} className={className}>
            <div ref={ref} className="relative">
                <button
                    id={id}
                    type="button"
                    disabled={disabled}
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy(id, hint, error)}
                    onClick={() => setOpen((v) => !v)}
                    className={cn(
                        controlBase,
                        controlTone(Boolean(error)),
                        'flex min-h-9 items-center gap-1.5 px-2 py-1 text-left',
                    )}
                >
                    <span className="flex flex-1 flex-wrap items-center gap-1.5">
                        {selected.length === 0 && <span className="px-1 text-ink-500">{placeholder}</span>}

                        {visible.map((option) => (
                            <span
                                key={option.value}
                                className="inline-flex items-center gap-1 rounded-pill bg-brand-subtle py-0.5 pl-2 pr-1 text-xs font-medium text-brand"
                            >
                                {option.label}
                                {/* A span, not a button: this sits inside a button already. */}
                                <span
                                    role="button"
                                    tabIndex={-1}
                                    aria-label={`Remove ${option.label}`}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        onChange(value.filter((v) => v !== option.value));
                                    }}
                                    className="rounded-pill p-0.5 transition-colors duration-fast hover:bg-card"
                                >
                                    <XIcon size={12} />
                                </span>
                            </span>
                        ))}

                        {overflow > 0 && (
                            <span className="num rounded-pill bg-sunken px-2 py-0.5 text-xs text-ink-500">
                                +{overflow} more
                            </span>
                        )}
                    </span>

                    <ChevronDownIcon size={16} className="shrink-0 text-ink-500" />
                </button>

                {open && (
                    <div className="absolute z-40 mt-1 w-full animate-fade-in overflow-hidden rounded-card border border-subtle bg-card shadow-card">
                        <div className="relative border-b border-subtle">
                            <SearchIcon
                                size={15}
                                className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                            />
                            <input
                                autoFocus
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={onSearchKeyDown}
                                placeholder="Search options"
                                aria-label={`Search ${label}`}
                                className="h-9 w-full bg-card pl-9 pr-3 text-base text-ink-900 placeholder:text-ink-500"
                            />
                        </div>

                        <ul role="listbox" aria-multiselectable="true" className="max-h-56 overflow-auto py-1">
                            {filtered.length === 0 && (
                                <li className="px-3 py-2 text-base text-ink-500">No matches. Try a shorter search.</li>
                            )}

                            {filtered.map((option) => {
                                const checked = value.includes(option.value);

                                return (
                                    <li
                                        key={option.value}
                                        role="option"
                                        aria-selected={checked}
                                        aria-disabled={option.disabled}
                                        onClick={() => toggle(option)}
                                        className={cn(
                                            'flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-base text-ink-900',
                                            'transition-colors duration-fast hover:bg-brand-subtle',
                                            option.disabled && 'cursor-not-allowed opacity-50',
                                        )}
                                    >
                                        {option.label}
                                        {checked && <CheckIcon size={16} className="shrink-0 text-brand" />}
                                    </li>
                                );
                            })}
                        </ul>

                        {value.length > 0 && (
                            <div className="border-t border-subtle p-2">
                                <button
                                    type="button"
                                    onClick={() => onChange([])}
                                    className="w-full rounded-button py-1.5 text-sm text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-700"
                                >
                                    Clear all
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Field>
    );
}
