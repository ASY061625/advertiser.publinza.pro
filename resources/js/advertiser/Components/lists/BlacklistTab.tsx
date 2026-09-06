import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, EmptyState, InfoIcon, Input, Modal, Textarea, TrashIcon } from '@shared/ui';
import { date } from '@shared/lib/format';
import type { CatalogRangeSet } from '@shared/types/catalog';
import type { ImportReport, ListFilters, ListRow, WishlistSummary } from '@shared/types/lists';
import { CatalogTable } from '../catalog/CatalogTable';
import { ListToolbar } from './ListToolbar';
import { RemoveButton, RowMenu } from './RowMenu';

interface Props {
    rows: ListRow[];
    ranges: CatalogRangeSet;
    filters: ListFilters;
    categories: { id: number; name: string }[];
    sorts: { value: string; label: string }[];
    wishlists: WishlistSummary[];
    report: ImportReport | null;
    selected: Set<number>;
    onToggle: (id: number, next: boolean) => void;
    onToggleAll: (next: boolean) => void;
    apply: (changes: Record<string, unknown>) => void;
    exportHref: string;
}

/**
 * Sites this advertiser never wants to see.
 *
 * The note at the top is not decoration. A blacklist that silently removes
 * sites from search results is a feature people forget they turned on, and the
 * next thing they report is that the catalog is missing sites.
 */
export function BlacklistTab({
    rows,
    ranges,
    filters,
    categories,
    sorts,
    wishlists,
    report,
    selected,
    onToggle,
    onToggleAll,
    apply,
    exportHref,
}: Props) {
    const [importing, setImporting] = useState(false);
    const empty = rows.length === 0 && filters.q === null && filters.category === null;

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <p className="flex items-start gap-2 rounded-card border border-subtle bg-sunken px-4 py-3 text-base text-ink-700">
                <InfoIcon size={16} className="mt-0.5 shrink-0 text-ink-500" />
                <span>
                    Blacklisted sites are hidden from your catalog and left out of every recommendation. You
                    can still see them by turning on{' '}
                    <span className="font-medium text-ink-900">Show blacklisted</span> in the catalog’s
                    filters.
                </span>
            </p>

            {report !== null && <ImportSummary report={report} />}

            {empty ? (
                <EmptyState
                    illustration={<TrashIcon size={26} />}
                    direction="Nothing blocked"
                    body="Blacklist a site and we'll keep it out of your catalog."
                    action={
                        <span className="flex flex-wrap items-center justify-center gap-2">
                            <a href="/catalog">
                                <Button size="lg">Browse the catalog</Button>
                            </a>
                            <Button size="lg" variant="secondary" onClick={() => setImporting(true)}>
                                Import a list
                            </Button>
                        </span>
                    }
                />
            ) : (
                <>
                    <ListToolbar
                        filters={filters}
                        categories={categories}
                        sorts={sorts}
                        apply={apply}
                        exportHref={exportHref}
                    >
                        {selected.size > 0 && (
                            <Button
                                variant="secondary"
                                onClick={() =>
                                    router.post(
                                        '/lists/blacklist/remove',
                                        { website_ids: [...selected] },
                                        { preserveScroll: true, preserveState: false },
                                    )
                                }
                            >
                                Unblock {selected.size}
                            </Button>
                        )}

                        <Button variant="secondary" onClick={() => setImporting(true)}>
                            Import domains
                        </Button>
                    </ListToolbar>

                    {rows.length === 0 ? (
                        <p className="rounded-card border border-subtle bg-card py-10 text-center text-base text-ink-500">
                            No blocked site matches that.
                        </p>
                    ) : (
                        <CatalogTable
                            sites={rows}
                            ranges={ranges}
                            columns={['website', 'category']}
                            minWidth="940px"
                            actionsWidth="w-[104px]"
                            selection={{ selected, onToggle, onToggleAll }}
                            showBlacklistState={false}
                            extraColumns={[
                                {
                                    key: 'reason',
                                    header: 'Reason',
                                    className: 'w-[280px]',
                                    render: (row) => <ReasonField row={row} />,
                                },
                                {
                                    key: 'blockedBy',
                                    header: 'Blocked by',
                                    className: 'whitespace-nowrap text-sm text-ink-700',
                                    render: (row) => (row.blockedBy === 'publinza' ? 'Publinza' : 'You'),
                                },
                                {
                                    key: 'added',
                                    header: 'Date',
                                    className: 'whitespace-nowrap text-sm text-ink-500',
                                    render: (row) => (row.addedAt === null ? '—' : date(row.addedAt)),
                                },
                            ]}
                            renderActions={(row) => (
                                <span className="flex items-center justify-end gap-1">
                                    <RemoveButton
                                        label={`Unblock ${row.domain}`}
                                        onClick={() =>
                                            router.post(
                                                `/sites/${row.slug}/blacklist`,
                                                {},
                                                { preserveScroll: true, preserveState: false },
                                            )
                                        }
                                    />
                                    <RowMenu row={row} from="blacklist" wishlists={wishlists} />
                                </span>
                            )}
                        />
                    )}
                </>
            )}

            {importing && <ImportDialog onClose={() => setImporting(false)} />}
        </div>
    );
}

/** The reason, edited in place and saved when the field is left. */
function ReasonField({ row }: { row: ListRow }) {
    const [value, setValue] = useState(row.reason ?? '');

    return (
        <Input
            label={`Reason for blocking ${row.domain}`}
            hideLabel
            value={value}
            placeholder="Why you blocked it"
            maxLength={500}
            onChange={(event) => setValue(event.target.value)}
            onBlur={() => {
                if (value === (row.reason ?? '')) return;

                router.patch(
                    `/lists/blacklist/${row.entryId}`,
                    { reason: value },
                    { preserveScroll: true, preserveState: false },
                );
            }}
        />
    );
}

/**
 * What an import actually did, in three named groups.
 *
 * The unmatched domains come back in full rather than as a count: a typo and a
 * site Publinza does not carry look identical from the server, and only the
 * advertiser can tell them apart.
 */
function ImportSummary({ report }: { report: ImportReport }) {
    const total = report.blocked.length + report.already.length + report.unmatched.length;

    return (
        <section className="flex flex-col gap-2 rounded-card border border-brand bg-brand-subtle px-4 py-3">
            <p className="num font-sora text-base font-semibold text-ink-900">
                {total} {total === 1 ? 'domain' : 'domains'} read
            </p>

            <ul className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <li className="num text-success">{report.blocked.length} blocked</li>
                <li className="num text-ink-500">{report.already.length} already on the list</li>
                <li className="num text-warning">{report.unmatched.length} not in the catalog</li>
            </ul>

            {report.unmatched.length > 0 && (
                <details className="text-sm text-ink-700">
                    <summary className="cursor-pointer text-ink-500">Show the ones we could not match</summary>
                    <p className="mt-1 break-words">{report.unmatched.join(', ')}</p>
                </details>
            )}
        </section>
    );
}

function ImportDialog({ onClose }: { onClose: () => void }) {
    const [domains, setDomains] = useState('');
    const [reason, setReason] = useState('');
    const [saving, setSaving] = useState(false);

    const lines = domains.split(/[\r\n,;]+/).filter((line) => line.trim() !== '').length;

    return (
        <Modal
            open
            onClose={onClose}
            title="Import domains to block"
            description="One per line. URLs, www prefixes and trailing slashes are all fine — we strip them."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        loading={saving}
                        disabled={lines === 0}
                        onClick={() => {
                            setSaving(true);
                            router.post(
                                '/lists/blacklist/import',
                                { domains, reason: reason || null },
                                {
                                    preserveScroll: true,
                                    preserveState: false,
                                    onFinish: () => {
                                        setSaving(false);
                                        onClose();
                                    },
                                },
                            );
                        }}
                    >
                        Block {lines === 0 ? '' : lines} {lines === 1 ? 'domain' : 'domains'}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-3">
                <Textarea
                    label="Domains"
                    rows={9}
                    value={domains}
                    onChange={(event) => setDomains(event.target.value)}
                    placeholder={'example.com\nhttps://www.another.co.uk/blog\nthird.io'}
                />

                <Input
                    label="Reason (optional)"
                    hint="Applied to every domain in this import. Only you will see it."
                    value={reason}
                    maxLength={500}
                    onChange={(event) => setReason(event.target.value)}
                    placeholder="Bulk import from the exclusion sheet"
                />
            </div>
        </Modal>
    );
}
