import { useEffect, useRef, useState } from 'react';
import { DownloadIcon, Input, MultiSelect, SearchIcon } from '@shared/ui';
import type { LedgerFilters, LedgerPage, QueryValue } from '@shared/types/balance';
import { LedgerTable } from './LedgerTable';

interface Props {
    ledger: LedgerPage;
    filters: LedgerFilters;
    types: { value: string; label: string }[];
    exportHref: (format: 'csv' | 'xlsx') => string;
    apply: (changes: Record<string, QueryValue>) => void;
}

export function TransactionsTab({ ledger, filters, types, exportHref, apply }: Props) {
    const filtered =
        filters.types.length > 0 ||
        filters.from !== null ||
        filters.to !== null ||
        filters.min !== null ||
        filters.max !== null ||
        filters.q !== null;

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="min-w-[220px] flex-1">
                    <Search value={filters.q ?? ''} onSearch={(q) => apply({ q: q === '' ? null : q })} />
                </div>

                <div className="w-[200px]">
                    <MultiSelect
                        label="Type"
                        options={types.map((type) => ({ value: type.value, label: type.label }))}
                        value={filters.types}
                        onChange={(next) => apply({ types: next })}
                        placeholder="Any type"
                    />
                </div>

                <Input
                    label="From"
                    type="date"
                    className="w-[150px]"
                    value={filters.from ?? ''}
                    onChange={(event) => apply({ from: event.target.value || null })}
                />

                <Input
                    label="To"
                    type="date"
                    className="w-[150px]"
                    value={filters.to ?? ''}
                    onChange={(event) => apply({ to: event.target.value || null })}
                />

                <Input
                    label="Min $"
                    type="number"
                    min={0}
                    className="w-[110px]"
                    value={filters.min === null ? '' : String(filters.min)}
                    onChange={(event) => apply({ min: event.target.value || null })}
                />

                <Input
                    label="Max $"
                    type="number"
                    min={0}
                    className="w-[110px]"
                    value={filters.max === null ? '' : String(filters.max)}
                    onChange={(event) => apply({ max: event.target.value || null })}
                />

                <div className="flex items-center gap-2">
                    {/* Both formats export the filtered set, not the table.
                        An export that quietly ignores a filter hands somebody a
                        spreadsheet that disagrees with the screen it came from. */}
                    <a
                        href={exportHref('csv')}
                        className="flex items-center gap-1.5 rounded-button border border-subtle bg-card px-3 py-2 text-base text-ink-700 transition-colors duration-fast hover:bg-sunken"
                    >
                        <DownloadIcon size={14} />
                        CSV
                    </a>
                    <a
                        href={exportHref('xlsx')}
                        className="flex items-center gap-1.5 rounded-button border border-subtle bg-card px-3 py-2 text-base text-ink-700 transition-colors duration-fast hover:bg-sunken"
                    >
                        <DownloadIcon size={14} />
                        XLSX
                    </a>
                </div>
            </div>

            {ledger.rows.length === 0 ? (
                <p className="rounded-card border border-subtle bg-card py-12 text-center text-base text-ink-500">
                    {filtered
                        ? 'No transaction matches those filters.'
                        : 'Nothing has moved yet. Your first top-up will appear here.'}
                </p>
            ) : (
                <>
                    <LedgerTable rows={ledger.rows} />

                    <p className="flex flex-wrap items-center justify-between gap-3 text-sm text-ink-500">
                        <span className="num">
                            {ledger.total} {ledger.total === 1 ? 'transaction' : 'transactions'}
                            {filtered ? ' matching' : ''}
                        </span>

                        {ledger.lastPage > 1 && (
                            <span className="flex items-center gap-2">
                                <button
                                    type="button"
                                    disabled={ledger.page <= 1}
                                    onClick={() => apply({ page: ledger.page - 1 })}
                                    className="rounded-button border border-subtle px-3 py-1.5 text-ink-700 disabled:opacity-40"
                                >
                                    Previous
                                </button>
                                <span className="num">
                                    Page {ledger.page} of {ledger.lastPage}
                                </span>
                                <button
                                    type="button"
                                    disabled={ledger.page >= ledger.lastPage}
                                    onClick={() => apply({ page: ledger.page + 1 })}
                                    className="rounded-button border border-subtle px-3 py-1.5 text-ink-700 disabled:opacity-40"
                                >
                                    Next
                                </button>
                            </span>
                        )}
                    </p>
                </>
            )}
        </div>
    );
}

/**
 * Debounced, and it holds its own text.
 *
 * A controlled input driven by a server round trip loses characters typed
 * during the request — the value snaps back to what the server last knew every
 * time a response lands mid-word.
 */
function Search({ value, onSearch }: { value: string; onSearch: (value: string) => void }) {
    const [text, setText] = useState(value);
    const timer = useRef<number>();

    useEffect(() => setText(value), [value]);
    useEffect(() => () => window.clearTimeout(timer.current), []);

    return (
        <Input
            label="Search descriptions"
            type="search"
            placeholder="Search descriptions"
            value={text}
            leadingIcon={<SearchIcon size={16} />}
            onChange={(event) => {
                const next = event.target.value;
                setText(next);
                window.clearTimeout(timer.current);
                timer.current = window.setTimeout(() => onSearch(next.trim()), 300);
            }}
        />
    );
}
