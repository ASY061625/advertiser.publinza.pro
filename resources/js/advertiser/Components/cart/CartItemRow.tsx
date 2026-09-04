import { router } from '@inertiajs/react';
import { Checkbox, IconButton, TrashIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { money } from '@shared/lib/format';
import type { CartLine } from '@shared/types/cart';
import { ItemWarnings } from './ItemWarnings';

interface Props {
    item: CartLine;
    selected: boolean;
    onSelect: (id: number, selected: boolean) => void;
    onEdit: (item: CartLine) => void;
}

/**
 * One line: what was bought, where it points, and what it costs.
 *
 * The fees are itemised under the price rather than folded into it. A single
 * number is smaller and worse — an advertiser comparing this line against the
 * catalog page they bought it from needs to see why the two differ, and "you
 * asked the publisher to write it" is the answer.
 */
export function CartItemRow({ item, selected, onSelect, onEdit }: Props) {
    const unavailable = item.warnings.some((warning) => warning.kind === 'unavailable');

    return (
        <li className={cn('flex flex-col gap-2 px-4 py-3', unavailable && 'bg-danger-bg/40')}>
            <div className="flex items-start gap-3">
                <span className="pt-0.5">
                    <Checkbox
                        label={`Select ${item.website.domain}`}
                        hideLabel
                        checked={selected}
                        onChange={(event) => onSelect(item.id, event.target.checked)}
                    />
                </span>

                <span className="flex size-8 shrink-0 items-center justify-center rounded-card border border-subtle bg-sunken font-sora text-sm font-semibold uppercase text-ink-500">
                    {item.website.domain.charAt(0)}
                </span>

                {/* Below sm the price sits under the description rather than
                    beside it. Side by side on a 390px screen leaves the middle
                    column narrow enough to wrap "Article placement" onto two
                    lines, which is unreadable for the sake of a right edge
                    nobody is scanning. */}
                <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-start sm:gap-4">
                    <div className="min-w-0 flex-1">
                        <p className="flex flex-wrap items-baseline gap-x-2">
                            <span className="font-sora text-base font-semibold text-ink-900">
                                {item.website.domain}
                            </span>
                            <span className="text-sm text-ink-500">{item.serviceLabel}</span>
                            <span aria-hidden="true" className="text-ink-300">
                                ·
                            </span>
                            <span className="text-sm text-ink-500">{item.contentLabel}</span>
                            {item.express && (
                                <span className="rounded-pill bg-warning-bg px-2 py-0.5 text-xs font-medium text-warning">
                                    Express
                                </span>
                            )}
                        </p>

                        <p className="mt-1 flex flex-wrap items-baseline gap-x-2 text-sm text-ink-500">
                            {item.folder && <span className="text-ink-700">{item.folder.name}</span>}

                            {item.anchorText ? (
                                <>
                                    <span className="text-ink-700">“{item.anchorText}”</span>
                                    {item.targetUrl && <span className="truncate">→ {item.targetUrl}</span>}
                                </>
                            ) : (
                                <span className="text-warning">No landing page chosen yet</span>
                            )}
                        </p>

                        <p className="mt-1.5 flex items-center gap-3 text-sm">
                            <button
                                type="button"
                                onClick={() => onEdit(item)}
                                className="font-medium text-brand underline-offset-2 hover:underline"
                            >
                                Edit
                            </button>
                        </p>
                    </div>

                    <div className="shrink-0 text-left sm:text-right">
                        <p className="num font-sora text-md font-semibold text-ink-900">{money(item.totalCents)}</p>

                        {/* Only the parts that are actually charged. A line with no
                        fees shows one number, because it is one number. */}
                        {(item.writingFeeCents > 0 || item.expressFeeCents > 0) && (
                            <dl className="mt-0.5 flex flex-col gap-0.5 text-xs text-ink-500 sm:items-end">
                                <div className="flex gap-2">
                                    <dt>Placement</dt>
                                    <dd className="num">{money(item.baseCents)}</dd>
                                </div>
                                {item.writingFeeCents > 0 && (
                                    <div className="flex gap-2">
                                        <dt>Writing</dt>
                                        <dd className="num">+{money(item.writingFeeCents)}</dd>
                                    </div>
                                )}
                                {item.expressFeeCents > 0 && (
                                    <div className="flex gap-2">
                                        <dt>Express</dt>
                                        <dd className="num">+{money(item.expressFeeCents)}</dd>
                                    </div>
                                )}
                            </dl>
                        )}

                        {item.quotedCents !== null && (
                            <p className="num mt-0.5 text-xs text-warning">
                                was {money(item.quotedCents)} when you added it
                            </p>
                        )}
                    </div>
                </div>

                <IconButton
                    label={`Remove ${item.website.domain}`}
                    variant="ghost"
                    size="sm"
                    icon={<TrashIcon size={16} />}
                    onClick={() => router.delete(`/cart/${item.id}`, { preserveScroll: true, preserveState: false })}
                />
            </div>

            <ItemWarnings item={item} />
        </li>
    );
}
