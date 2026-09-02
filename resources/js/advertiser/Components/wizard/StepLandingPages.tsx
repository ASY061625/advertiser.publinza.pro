import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { Button, Input, PlusIcon, TrashIcon } from '@shared/ui';
import type { LandingPageRow, WizardState } from '@shared/types/wizard';
import { EMPTY_ROW } from './useProjectWizard';
import { anchorHealth, landingPageErrors, parseBulk, registrable, hostOf } from './validation';

interface Props {
    state: WizardState;
    errors: Record<string, string>;
    onChange: (changes: Partial<WizardState>) => void;
}

export function StepLandingPages({ state, errors, onChange }: Props) {
    const [bulkOpen, setBulkOpen] = useState(false);
    const [bulkText, setBulkText] = useState('');
    const [bulkErrors, setBulkErrors] = useState<{ line: number; text: string; reason: string }[]>([]);
    const [dragging, setDragging] = useState<number | null>(null);

    const rows = state.landing_pages;
    const rowErrors = landingPageErrors(state);
    const health = anchorHealth(state);
    const promoted = registrable(hostOf(state.website_url) ?? '') ?? 'your site';

    function setRow(index: number, changes: Partial<LandingPageRow>) {
        onChange({
            landing_pages: rows.map((row, i) => (i === index ? { ...row, ...changes } : row)),
        });
    }

    function move(from: number, to: number) {
        if (from === to || to < 0 || to >= rows.length) return;

        const next = [...rows];
        next.splice(to, 0, ...next.splice(from, 1));
        onChange({ landing_pages: next });
    }

    function applyBulk() {
        if (bulkText.trim() === '') return;

        const { rows: parsed, errors: problems } = parseBulk(bulkText);
        setBulkErrors(problems);

        if (parsed.length === 0) return;

        // Replaces blank starter rows, appends to real ones — pasting should
        // not silently discard what is already typed.
        const existing = rows.filter((row) => row.anchor_text.trim() !== '' || row.url.trim() !== '');

        onChange({
            landing_pages: [...existing, ...parsed.map((row) => ({ ...EMPTY_ROW(), ...row }))],
        });

        // Only the lines that failed stay behind, so a second attempt is a
        // matter of fixing what is left rather than finding it again.
        setBulkText(problems.map((problem) => problem.text).join('\n'));
    }

    return (
        <div className="flex flex-col gap-5">
            <div>
                <h3 className="text-sm font-medium text-ink-700">Landing pages</h3>
                <p className="mt-0.5 text-sm text-ink-500">
                    The pages on {promoted} that links will point to, and the words those links use. Drag to reorder.
                </p>
            </div>

            <ol className="flex flex-col gap-2">
                {rows.map((row, index) => (
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
                            {/* Drag is the shortcut; the arrows are the way this
                                works with a keyboard or a screen reader. */}
                            <span className="flex shrink-0 flex-col gap-0.5 pt-6">
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

                            <span className="shrink-0 pt-6">
                                <ArrowButton
                                    label={`Remove row ${index + 1}`}
                                    disabled={rows.length === 1}
                                    destructive
                                    onClick={() => onChange({ landing_pages: rows.filter((_, i) => i !== index) })}
                                >
                                    <TrashIcon size={14} />
                                </ArrowButton>
                            </span>
                        </div>
                    </li>
                ))}
            </ol>

            <div className="flex flex-wrap items-center gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => onChange({ landing_pages: [...rows, EMPTY_ROW()] })}
                >
                    <PlusIcon size={14} />
                    Add a page
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

function ArrowButton({
    label,
    disabled,
    destructive = false,
    onClick,
    children,
}: {
    label: string;
    disabled: boolean;
    destructive?: boolean;
    onClick: () => void;
    children: React.ReactNode;
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
                'transition-colors duration-fast disabled:pointer-events-none disabled:opacity-30',
                destructive ? 'p-1.5 hover:bg-danger-bg hover:text-danger' : 'hover:bg-sunken hover:text-ink-900',
            )}
        >
            {children}
        </button>
    );
}
