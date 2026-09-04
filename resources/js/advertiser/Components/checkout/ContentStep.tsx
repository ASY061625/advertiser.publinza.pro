import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button, CheckIcon, Textarea, WarningIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { number } from '@shared/lib/format';
import type { CheckoutContent, CheckoutContentItem, ContentState } from '@shared/types/cart';

/**
 * The articles, one editor per line.
 *
 * Every draft is saved to the server as it is submitted, not held in the
 * browser. A buyer who has pasted four articles and lost the fifth to a refresh
 * has lost all five, because they will not sit down and do it again.
 *
 * The word count is checked against the publisher's own minimum, which is a
 * real acceptance criterion rather than a suggestion — a 400-word draft against
 * a 1,200-word minimum comes back rejected days later, and catching it here
 * costs nothing.
 */
export function ContentStep({ content }: { content: CheckoutContent }) {
    const outstanding = content.needed - content.ready;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-subtle bg-card px-4 py-3">
                <p className="text-base text-ink-700">
                    {content.needed === 0 ? (
                        'Every placement in this order is written by its publisher. Nothing to do here.'
                    ) : (
                        <>
                            <span className="num font-semibold text-ink-900">
                                {content.ready} of {content.needed}
                            </span>{' '}
                            articles ready.
                        </>
                    )}
                </p>

                {outstanding > 0 && (
                    <p className="text-sm text-ink-500">
                        You can also do this later — the rest of the order goes through and these stay as
                        drafts.
                    </p>
                )}
            </div>

            <ul className="flex flex-col gap-3">
                {content.items.map((item) => (
                    <ArticleCard key={item.itemId} item={item} />
                ))}
            </ul>
        </div>
    );
}

const STATES: Record<ContentState, { label: string; className: string }> = {
    not_needed: { label: 'Publisher writes it', className: 'bg-teal-subtle text-success' },
    empty: { label: 'Not started', className: 'bg-sunken text-ink-500' },
    short: { label: 'Too short', className: 'bg-warning-bg text-warning' },
    ready: { label: 'Ready', className: 'bg-success-bg text-success' },
};

function ArticleCard({ item }: { item: CheckoutContentItem }) {
    const [open, setOpen] = useState(item.state === 'short');
    const state = STATES[item.state];

    // A draft that saved too short stays open. Closing it would be closing the
    // one editor the buyer still has work to do in, and asking them to find the
    // Edit button again to finish something they were already finishing.
    useEffect(() => {
        if (item.state === 'short') setOpen(true);
    }, [item.state]);

    if (item.publisherWrites) {
        return (
            <li className="flex items-center gap-3 rounded-card border border-subtle bg-card px-4 py-3">
                <CheckIcon size={16} className="text-success" />
                <span className="min-w-0 flex-1 font-medium text-ink-900">{item.domain}</span>
                <span className={cn('rounded-pill px-2 py-0.5 text-xs font-medium', state.className)}>
                    {state.label}
                </span>
            </li>
        );
    }

    return (
        <li className="overflow-hidden rounded-card border border-subtle bg-card">
            <div className="flex flex-wrap items-center gap-3 px-4 py-3">
                <span className="min-w-0 flex-1">
                    <span className="block font-medium text-ink-900">{item.domain}</span>
                    <span className="num block text-sm text-ink-500">
                        {item.wordCount === null
                            ? `Minimum ${number(item.minWords)} words`
                            : `${number(item.wordCount)} of ${number(item.minWords)} words`}
                        {item.fileName && ` · ${item.fileName}`}
                    </span>
                </span>

                <span className={cn('rounded-pill px-2 py-0.5 text-xs font-medium', state.className)}>
                    {state.label}
                </span>

                <Button size="sm" variant="secondary" onClick={() => setOpen((value) => !value)}>
                    {item.wordCount === null ? 'Add article' : open ? 'Close' : 'Edit'}
                </Button>
            </div>

            {item.state === 'short' && (
                <p className="num flex items-center gap-2 border-t border-subtle bg-warning-bg px-4 py-2 text-sm text-ink-700">
                    <WarningIcon size={14} className="shrink-0 text-warning" />
                    {number(item.minWords - (item.wordCount ?? 0))} words short of what this publisher accepts.
                </p>
            )}

            {open && <Editor item={item} onDone={() => setOpen(false)} />}
        </li>
    );
}

function Editor({ item, onDone }: { item: CheckoutContentItem; onDone: () => void }) {
    const form = useForm<{ title: string; body: string; file: File | null }>({
        title: item.title ?? '',
        body: item.body ?? '',
        file: null,
    });

    // Counted here as well as on the server, so the figure moves while somebody
    // types. The server's count is the one that decides — this is a preview of
    // it, and both strip tags before splitting so they agree.
    const words = countWords(form.data.body);

    return (
        <form
            className="flex flex-col gap-3 border-t border-subtle px-4 py-4"
            onSubmit={(event) => {
                event.preventDefault();
                // Errors on this form are the word count and the file type,
                // both of which have to survive the response — so the component
                // is preserved and only the props are replaced.
                form.post(`/checkout/${item.itemId}/article`, {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: onDone,
                });
            }}
        >
            <label className="flex flex-col gap-1">
                <span className="text-sm font-medium text-ink-700">Title (optional)</span>
                <input
                    type="text"
                    value={form.data.title}
                    onChange={(event) => form.setData('title', event.target.value)}
                    className="h-9 rounded-button border border-subtle bg-card px-3 text-base text-ink-900"
                />
            </label>

            <Textarea
                label="Article"
                rows={10}
                value={form.data.body}
                error={form.errors.body}
                onChange={(event) => form.setData('body', event.target.value)}
                placeholder="Paste the article here."
                hint={
                    form.data.body === ''
                        ? undefined
                        : `${number(words)} words — the publisher needs ${number(item.minWords)}.`
                }
            />

            <div className="flex flex-wrap items-center gap-3">
                <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                    <span className="rounded-button border border-subtle bg-card px-3 py-1.5 font-medium hover:bg-sunken">
                        Upload a file
                    </span>
                    <input
                        type="file"
                        accept=".doc,.docx,.md,.markdown,.txt"
                        className="sr-only"
                        onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                    />
                    <span className="text-ink-500">
                        {form.data.file?.name ?? '.docx, .md or .txt'}
                    </span>
                </label>

                <span className="ml-auto flex items-center gap-2">
                    {item.wordCount !== null && (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() =>
                                router.delete(`/checkout/${item.itemId}/article`, {
                                    preserveScroll: true,
                                    preserveState: false,
                                })
                            }
                        >
                            Remove
                        </Button>
                    )}

                    <Button type="submit" loading={form.processing}>
                        Save article
                    </Button>
                </span>
            </div>

            {form.errors.file && (
                <p role="alert" className="text-sm text-danger">
                    {form.errors.file}
                </p>
            )}
        </form>
    );
}

/** Mirrors the server's count: tags out, then split on whitespace. */
function countWords(text: string): number {
    const plain = text
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return plain === '' ? 0 : plain.split(' ').length;
}
