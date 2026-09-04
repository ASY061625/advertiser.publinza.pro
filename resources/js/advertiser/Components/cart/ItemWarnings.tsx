import { router } from '@inertiajs/react';
import { WarningIcon } from '@shared/ui';
import type { CartLine, CartWarning } from '@shared/types/cart';

/**
 * What is wrong with a line, and the two things a buyer can do about it.
 *
 * Every warning offers both dismiss and remove, because they mean opposite
 * things and only the buyer knows which applies. "Does not accept crypto" might
 * be irrelevant — this particular article is not about crypto — or it might be
 * the reason to drop the line. Guessing costs either a lost sale or a rejected
 * placement, and asking costs one click.
 *
 * Dismissal is stored on the line rather than in component state: a warning
 * that comes back on every page load is a warning people learn to scroll past,
 * and by then the strip has stopped working for the ones that matter.
 */
export function ItemWarnings({ item }: { item: CartLine }) {
    if (item.warnings.length === 0) return null;

    return (
        <ul className="flex flex-col gap-1.5 sm:ml-11">
            {item.warnings.map((warning) => (
                <li
                    key={warning.kind}
                    className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-card border border-warning bg-warning-bg px-3 py-2 text-sm text-ink-700"
                >
                    <WarningIcon size={14} className="shrink-0 text-warning" />
                    <span className="min-w-0 flex-1">{warning.message}</span>

                    <span className="flex shrink-0 items-center gap-3">
                        {/* "Unavailable" has no dismiss: the line cannot be
                            bought, so hiding the reason would leave a buyer
                            stuck at checkout with no explanation. */}
                        {warning.kind !== 'unavailable' && (
                            <button
                                type="button"
                                onClick={() => dismiss(item.id, warning)}
                                className="text-ink-500 underline-offset-2 hover:underline"
                            >
                                Dismiss
                            </button>
                        )}

                        <button
                            type="button"
                            onClick={() =>
                                router.delete(`/cart/${item.id}`, {
                                    preserveScroll: true,
                                    preserveState: false,
                                })
                            }
                            className="font-medium text-danger underline-offset-2 hover:underline"
                        >
                            Remove
                        </button>
                    </span>
                </li>
            ))}
        </ul>
    );
}

function dismiss(itemId: number, warning: CartWarning) {
    router.post(
        `/cart/${itemId}/dismiss`,
        { kind: warning.kind },
        { preserveScroll: true, preserveState: false },
    );
}
