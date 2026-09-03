import { useState, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { Button, Input, PlusIcon, Tooltip, TrashIcon } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { LandingPageRow } from '@shared/types/wizard';
import { anchorHealth, landingPageErrors, parseBulk, registrable, hostOf } from '../wizard/validation';

export interface LandingPageEditorProps {
    rows: LandingPageRow[];
    onChange: (rows: LandingPageRow[]) => void;
    /** The site being promoted. Every target URL has to be a page on it. */
    websiteUrl: string;
    /** Server-side errors, keyed `landing_pages.{index}.{field}`. */
    errors?: Record<string, string>;
    /**
     * How many posts already point at each row, keyed by row key. A row above
     * zero cannot be removed — placements point at it.
     */
    usage?: Record<string, number>;
    /** The wizard always keeps one row; a folder may legitimately have none. */
    minRows?: number;
    heading?: ReactNode;
}

/** A blank row. The key is what survives a drag, so it has to be unique. */
export function emptyLandingPage(): LandingPageRow {
    return { key: `lp-new-${Math.random().toString(36).slice(2)}`, anchor_text: '', url: '' };
}

/**
 * The repeatable landing-page list: drag to reorder, bulk paste, per-row
 * validation against the promoted domain.
 *
 * Shared by the create wizard's third step and the folder editor, which are the
 * same widget with one difference — in the folder editor a row can already have
 * posts pointing at it, and those rows refuse to be removed.
 */
export function LandingPageEditor({
    rows,
    onChange,
    websiteUrl,
    errors = {},
    usage = {},
    minRows = 0,
    heading,
}: LandingPageEditorProps) {
    const [bulkOpen, setBulkOpen] = useState(false);
    const [bulkText, setBulkText] = useState('');
    const [bulkErrors, setBulkErrors] = useState<{ line: number; text: string; reason: string }[]>([]);
    const [dragging, setDragging] = useState<number | null>(null);

    const rowErrors = landingPageErrors({ website_url: websiteUrl, landing_pages: rows });
    const health = anchorHealth({ website_url: websiteUrl, landing_pages: rows });
    const promoted = registrable(hostOf(websiteUrl) ?? '') ?? 'your site';

    function setRow(index: number, changes: Partial<LandingPageRow>) {
        onChange(rows.map((row, i) => (i === index ? { ...row, ...changes } : row)));
    }

    function move(from: number, to: number) {
        if (from === to || to < 0 || to >= rows.length) return;

        const next = [...rows];
        next.splice(to, 0, ...next.splice(from, 1));
        onChange(next);
    }

    function applyBulk() {
        if (bulkText.trim() === '') return;

        const { rows: parsed, errors: problems } = parseBulk(bulkText);
        setBulkErrors(problems);

        if (parsed.length === 0) return;

        // Replaces blank starter rows, appends to real ones — pasting should
        // not silently discard what is already typed.
        const existing = rows.filter((row) => row.anchor_text.trim() !== '' || row.url.trim() !== '');

        onChange([...existing, ...parsed.map((row) => ({ ...emptyLandingPage(), ...row }))]);

        // Only the lines that failed stay behind, so a second attempt is a
        // matter of fixing what is left rather than finding it again.
        setBulkText(problems.map((problem) => problem.text).join('\n'));
    }

    return (
        <div className="flex flex-col gap-5">
            {heading ?? (
                <div>
                    <h3 className="text-sm font-medium text-ink-700">Landing pages</h3>
                    <p className="mt-0.5 text-sm text-ink-500">
                        The pages on {promoted} that links will point to, and the words those links use. Drag to
                        reorder.
                    </p>
                </div>
            )}

            <ol className="flex flex-col gap-2">
                {rows.map((row, index) => {
                    const uses = usage[row.key] ?? 0;
                    // Only the first row shows its labels, so only the first
                    // row's side columns need to clear one. Without this the
                    // handles and the bin sit below the inputs they belong to.
                    const align = index === 0 ? 'pt-[1.6rem]' : 'pt-1';

                    return (
                        <li
                            key={row.key}
                            draggable
                            onDragStart={() => setDragging(index)}
                            onDragOver={(event) => event.preventDefault()}
                            onDrop={() => {
                                if (dragging !== null) move(dragging, index);
                                setDragging(null);
                            }}
                            onDragEnd={() => setDragging(null)}
                            className={cn(
                                'rounded-card border border-subtle bg-card p-3',
                                dragging === index && 'opacity-50',
                            )}
                        >
                            <div className="flex items-start gap-2">
                                {/* Drag is the shortcut; the arrows are the way
                                    this works with a keyboard or a screen
                                    reader. Both move the same row. */}
                                <span className={cn('flex shrink-0 flex-col items-center gap-0.5', align)}>
                                    <GripIcon />
                                    <ArrowButton
                                        label={`Move row ${index + 1} up`}
                                        disabled={index === 0}
                                        onClick={() => move(index, index - 1)}
                                    >
                                        ▲
                                    </ArrowButton>
                                    <ArrowButton
                                        label={`Move row ${index + 1} down`}
                                        disabled={index === rows.length - 1}
                                        onClick={() => move(index, index + 1)}
                                    >
                                        ▼
                                    </ArrowButton>
                                </span>

                                <div className="grid flex-1 gap-2 sm:grid-cols-2">
                                    <Input
                                        label={`Anchor text ${index + 1}`}
                                        hideLabel={index > 0}
                                        value={row.anchor_text}
                                        onChange={(event) =>
                                            setRow(index, { anchor_text: event.target.value.slice(0, 120) })
                                        }
                                        placeholder="best invoicing software"
                                        maxLength={120}
                                        error={errors[`landing_pages.${index}.anchor_text`]}
                                    />
                                    <Input
                                        label={`Target URL ${index + 1}`}
                                        hideLabel={index > 0}
                                        type="url"
                                        value={row.url}
                                        onChange={(event) => setRow(index, { url: event.target.value })}
                                        placeholder={`https://${promoted}/pricing`}
                                        error={rowErrors[index] ?? errors[`landing_pages.${index}.url`]}
                                    />
                                </div>

                                {/* A fixed column, so the inputs line up down
                                    the list whether or not a row is in use. */}
                                <span className={cn('flex w-[6.5rem] shrink-0 items-center justify-end gap-1', align)}>
                                    {uses > 0 && (
                                        <Tooltip
                                            content={`${number(uses)} post${uses === 1 ? '' : 's'} already point${
                                                uses === 1 ? 's' : ''
                                            } at this page.`}
                                        >
                                            <span className="rounded-pill bg-sunken px-2 py-0.5 text-xs text-ink-500">
                                                <span className="num">{number(uses)}</span> in use
                                            </span>
                                        </Tooltip>
                                    )}

                                    <RemoveButton
                                        index={index}
                                        uses={uses}
                                        atMinimum={rows.length <= minRows}
                                        onRemove={() => onChange(rows.filter((_, i) => i !== index))}
                                    />
                                </span>
                            </div>
                        </li>
                    );
                })}
            </ol>

            {rows.length === 0 && (
                <p className="rounded-card border border-dashed border-subtle px-3 py-4 text-center text-sm text-ink-500">
                    No landing pages in this folder yet.
                </p>
            )}

            <div className="flex flex-wrap items-center gap-2">
                <Button variant="secondary" size="sm" onClick={() => onChange([...rows, emptyLandingPage()])}>
                    <PlusIcon size={14} />
                    Add landing page
                </Button>

                <Button variant="ghost" size="sm" onClick={() => setBulkOpen((open) => !open)}>
                    {bulkOpen ? 'Hide bulk paste' : 'Bulk paste'}
                </Button>
            </div>

            {bulkOpen && (
                <div className="rounded-card border border-subtle bg-sunken p-3">
                    <label htmlFor="bulk-paste" className="text-sm font-medium text-ink-700">
                        One per line, as <code className="text-ink-900">anchor text | URL</code>
                    </label>

                    <textarea
                        id="bulk-paste"
                        value={bulkText}
                        onChange={(event) => setBulkText(event.target.value)}
                        onBlur={applyBulk}
                        rows={5}
                        spellCheck={false}
                        placeholder={`best invoicing software | https://${promoted}/pricing\nhow we handle VAT | https://${promoted}/vat`}
                        className={cn(
                            'mt-1.5 w-full rounded-input border border-subtle bg-card px-3 py-2 text-base',
                            'font-mono text-ink-900 placeholder:text-ink-300 focus:border-brand focus:outline-none',
                        )}
                    />

                    <p className="mt-1 text-sm text-ink-500">Rows are added when you click away from the box.</p>

                    {bulkErrors.length > 0 && (
                        <div className="mt-2 rounded-card bg-danger-bg px-3 py-2">
                            <p className="text-sm font-medium text-danger">
                                {bulkErrors.length} line{bulkErrors.length === 1 ? '' : 's'} could not be read and{' '}
                                {bulkErrors.length === 1 ? 'is' : 'are'} still in the box:
                            </p>
                            <ul className="mt-1 flex flex-col gap-0.5">
                                {bulkErrors.map((problem) => (
                                    <li key={problem.line} className="text-sm text-danger">
                                        <span className="num">Line {problem.line}</span> — {problem.reason}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}

            {/* Advisory, never blocking: anchor mix is a judgement call and the
                heuristic behind it is crude enough that it should not be one. */}
            {health.total >= 3 && health.share > 0.6 && (
                <p className="rounded-card border border-subtle bg-sunken px-3 py-2.5 text-sm text-ink-700">
                    <span className="font-medium text-ink-900">A note on anchors.</span> {health.commercial} of{' '}
                    {health.total} read as exact-match commercial phrases. Mixing in branded anchors (your company name)
                    and generic ones (&ldquo;this guide&rdquo;) tends to hold up better over time. Entirely your call —
                    nothing here is blocked.
                </p>
            )}

            {errors.landing_pages && (
                <p role="alert" className="text-sm text-danger">
                    {errors.landing_pages}
                </p>
            )}
        </div>
    );
}

/**
 * Disabled Remove always says why. "This button does nothing and will not tell
 * you why" is the worst control in a form, and a row with placements pointing at
 * it is exactly the case someone will be confused by.
 */
function RemoveButton({
    index,
    uses,
    atMinimum,
    onRemove,
}: {
    index: number;
    uses: number;
    atMinimum: boolean;
    onRemove: () => void;
}) {
    const reason =
        uses > 0
            ? `${number(uses)} post${uses === 1 ? '' : 's'} already point${uses === 1 ? 's' : ''} at this page, so it ` +
              'cannot be removed. Edit the anchor or the URL instead.'
            : atMinimum
              ? 'A project needs at least one landing page for its links to point at.'
              : null;

    return (
        <Tooltip content={reason ?? `Remove row ${index + 1}`}>
            <button
                type="button"
                aria-label={`Remove row ${index + 1}`}
                aria-disabled={reason !== null}
                onClick={() => reason === null && onRemove()}
                className={cn(
                    'rounded-button p-1.5 transition-colors duration-fast ease-standard',
                    reason === null
                        ? 'text-ink-500 hover:bg-danger-bg hover:text-danger'
                        : 'cursor-not-allowed text-ink-300',
                )}
            >
                <TrashIcon size={14} />
            </button>
        </Tooltip>
    );
}

function ArrowButton({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'rounded-button px-1.5 py-0.5 text-xs leading-none text-ink-500',
                'transition-colors duration-fast hover:bg-sunken hover:text-ink-900',
                'disabled:pointer-events-none disabled:opacity-30',
            )}
        >
            {children}
        </button>
    );
}

/** Decoration: the affordance for the drag the whole row already accepts. */
function GripIcon() {
    return (
        <svg width={14} height={14} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" className="text-ink-300">
            <circle cx="9" cy="6" r="1.6" />
            <circle cx="15" cy="6" r="1.6" />
            <circle cx="9" cy="12" r="1.6" />
            <circle cx="15" cy="12" r="1.6" />
            <circle cx="9" cy="18" r="1.6" />
            <circle cx="15" cy="18" r="1.6" />
        </svg>
    );
}
