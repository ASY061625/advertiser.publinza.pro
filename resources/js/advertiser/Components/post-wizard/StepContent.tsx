import { Input, Select, Textarea } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { money, number } from '@shared/lib/format';
import type { PostWizardState, WizardWebsite } from '@shared/types/postWizard';

interface Props {
    state: PostWizardState;
    patch: (changes: Partial<PostWizardState>) => void;
    site: WizardWebsite | null;
    /** The folder's brief, or the project's where the folder has none. */
    publisherTask: string | null;
    article: File | null;
    onArticle: (file: File | null) => void;
    image: File | null;
    onImage: (file: File | null) => void;
}

/**
 * Who writes it.
 *
 * The two branches ask for opposite things — one collects an article, the other
 * commissions one — so they are a radio with two bodies rather than one form
 * with fields that grey out. A greyed field still reads as something you failed
 * to fill in.
 */
export function StepContent({
    state,
    patch,
    site,
    publisherTask,
    article,
    onArticle,
    image,
    onImage,
}: Props) {
    const service = site?.services.find((entry) => entry.type === state.serviceType) ?? site?.services[0];
    const writingFee = service?.writingFeeCents ?? 0;
    const publisherWrites = state.contentMode === 'publisher_writes';
    const minWords = site?.minWords ?? 0;
    const words = countWords(state.body);

    return (
        <div className="flex flex-col gap-4">
            <fieldset className="flex flex-col gap-2">
                <legend className="mb-1 text-sm font-medium text-ink-700">Who writes the article?</legend>

                {(
                    [
                        ['advertiser_provides', 'I’ll provide the article', 0],
                        ['publisher_writes', 'Publisher writes it', writingFee],
                    ] as const
                ).map(([value, label, fee]) => (
                    <label
                        key={value}
                        className={cn(
                            'flex cursor-pointer items-center gap-2 rounded-card border px-3 py-2 text-base',
                            state.contentMode === value
                                ? 'border-brand bg-brand-subtle text-ink-900'
                                : 'border-subtle text-ink-700 hover:bg-sunken',
                        )}
                    >
                        <input
                            type="radio"
                            name="wizard-content-mode"
                            checked={state.contentMode === value}
                            onChange={() =>
                                patch({
                                    contentMode: value,
                                    // Prefill the brief the first time the
                                    // publisher branch is opened, and never
                                    // overwrite what somebody has typed into it.
                                    brief:
                                        value === 'publisher_writes' && state.brief === ''
                                            ? (publisherTask ?? '')
                                            : state.brief,
                                })
                            }
                            className="size-4 accent-[color:var(--brand-blue)]"
                        />
                        <span>{label}</span>
                        {/* The fee sits on the option that incurs it: this is
                            the moment the choice is made, not a footnote. */}
                        {fee > 0 && value === 'publisher_writes' && (
                            <span className="num ml-auto text-sm text-ink-500">+{money(fee)}</span>
                        )}
                    </label>
                ))}
            </fieldset>

            {publisherWrites ? (
                <div className="flex flex-col gap-3">
                    <Textarea
                        label="Brief for the publisher"
                        rows={6}
                        maxLength={5000}
                        showCount
                        value={state.brief}
                        onChange={(event) => patch({ brief: event.target.value })}
                        hint={
                            publisherTask === null
                                ? 'What the article should cover, and anything it must avoid.'
                                : 'Prefilled from this project’s brief. Edit it for this post.'
                        }
                    />

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Input
                            label="Keywords"
                            value={state.keywords}
                            onChange={(event) => patch({ keywords: event.target.value })}
                            hint="Comma separated."
                            placeholder="invoicing, freelance accounting"
                        />

                        <Input
                            label="Tone"
                            value={state.tone}
                            onChange={(event) => patch({ tone: event.target.value })}
                            placeholder="Practical, no superlatives"
                        />
                    </div>

                    {/* Only where the publisher has actually offered a choice of
                        length. Most have not, and inventing tiers would be the
                        form promising something nobody agreed to. */}
                    {site !== null && site.wordCountTiers.length > 0 && (
                        <Select
                            label="Article length"
                            value={state.targetWords}
                            onChange={(event) => patch({ targetWords: event.target.value })}
                            hint="Included in the price — this publisher writes to any of these."
                            options={[
                                { value: '', label: `Publisher’s default (${number(minWords)} words)` },
                                ...site.wordCountTiers.map((tier) => ({
                                    value: String(tier),
                                    label: `${number(tier)} words`,
                                })),
                            ]}
                        />
                    )}
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    <Input
                        label="Title"
                        value={state.title}
                        onChange={(event) => patch({ title: event.target.value })}
                        placeholder="The quiet case for boring accounting software"
                    />

                    <Textarea
                        label="Article"
                        rows={9}
                        value={state.body}
                        onChange={(event) => patch({ body: event.target.value })}
                        placeholder="Paste the article here, or upload a file below."
                        hint={
                            minWords === 0
                                ? undefined
                                : state.body === ''
                                  ? `${number(minWords)} words minimum for this site.`
                                  : `${number(words)} of ${number(minWords)} words.`
                        }
                    />

                    {/* Short is a warning, not a block. "I will finish it later"
                        is a legitimate answer — the order goes through and the
                        post waits as a draft until the copy arrives. */}
                    {minWords > 0 && state.body !== '' && words < minWords && (
                        <p className="num text-sm text-warning">
                            {number(minWords - words)} words short of what this publisher accepts. You can
                            still order and finish it later.
                        </p>
                    )}

                    <FilePicker
                        label="Upload instead"
                        accept=".doc,.docx,.md,.markdown,.txt"
                        hint=".docx, .md or .txt"
                        file={article}
                        onChange={onArticle}
                    />

                    <FilePicker
                        label="Image (optional)"
                        accept="image/*"
                        hint="One image, credited by the publisher."
                        file={image}
                        onChange={onImage}
                    />
                </div>
            )}
        </div>
    );
}

function FilePicker({
    label,
    accept,
    hint,
    file,
    onChange,
}: {
    label: string;
    accept: string;
    hint: string;
    file: File | null;
    onChange: (file: File | null) => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                <span className="rounded-button border border-subtle bg-card px-3 py-1.5 font-medium hover:bg-sunken">
                    {label}
                </span>
                <input
                    type="file"
                    accept={accept}
                    className="sr-only"
                    onChange={(event) => onChange(event.target.files?.[0] ?? null)}
                />
                <span className="text-ink-500">{file?.name ?? hint}</span>
            </label>

            {file !== null && (
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    className="text-sm text-ink-500 underline-offset-2 hover:text-danger hover:underline"
                >
                    Remove
                </button>
            )}
        </div>
    );
}

/** Mirrors the server's count: tags out, then split on whitespace. */
export function countWords(text: string): number {
    const plain = text
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return plain === '' ? 0 : plain.split(' ').length;
}
