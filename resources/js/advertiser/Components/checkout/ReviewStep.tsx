import { Link } from '@inertiajs/react';
import { money } from '@shared/lib/format';
import type { CartPayload } from '@shared/types/cart';

/**
 * Every line, read-only, with its full configuration.
 *
 * Read-only on purpose. This step exists to be checked, and a screen where each
 * field is also an input invites re-configuring instead of reading — which is
 * how a wrong anchor gets looked at four times and changed none of them. The
 * one control is a link back to the cart, where editing belongs.
 *
 * The Article column is the reason this step comes before the content step: it
 * is the buyer's first sight of how many of these they are on the hook to
 * write.
 */
export function ReviewStep({ cart }: { cart: CartPayload }) {
    return (
        <div className="flex flex-col gap-4">
            {cart.groups.map((group) => (
                <section key={group.id} className="overflow-hidden rounded-card border border-subtle bg-card">
                    <header className="flex items-center justify-between gap-3 border-b border-subtle px-4 py-3">
                        <span className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                            {group.project && (
                                <span
                                    aria-hidden="true"
                                    className="size-2 shrink-0 rounded-full"
                                    style={{ backgroundColor: group.project.color ?? 'var(--ink-300)' }}
                                />
                            )}
                            <span className="font-sora text-md font-semibold text-ink-900">
                                {group.project?.name ?? 'No project'}
                            </span>
                        </span>
                        <span className="num shrink-0 font-sora text-md font-semibold text-ink-900">
                            {money(group.subtotalCents)}
                        </span>
                    </header>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-base">
                            <thead>
                                <tr className="border-b border-subtle text-left text-xs uppercase tracking-wide text-ink-500">
                                    <th scope="col" className="px-4 py-2 font-medium">
                                        Site
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-medium">
                                        Landing page
                                    </th>
                                    <th scope="col" className="px-4 py-2 font-medium">
                                        Article
                                    </th>
                                    <th scope="col" className="px-4 py-2 text-right font-medium">
                                        Price
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-subtle">
                                {group.items.map((item) => (
                                    <tr key={item.id} className="align-top">
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-ink-900">{item.website.domain}</p>
                                            <p className="text-sm text-ink-500">
                                                {item.serviceLabel}
                                                {item.folder && ` · ${item.folder.name}`}
                                                {item.express && ' · Express'}
                                            </p>
                                        </td>

                                        <td className="px-4 py-3">
                                            {item.anchorText ? (
                                                <>
                                                    <p className="text-ink-900">“{item.anchorText}”</p>
                                                    <p className="max-w-[22rem] truncate text-sm text-ink-500">
                                                        {item.targetUrl}
                                                    </p>
                                                </>
                                            ) : (
                                                <span className="text-sm text-warning">Not set</span>
                                            )}
                                        </td>

                                        <td className="whitespace-nowrap px-4 py-3">
                                            {item.contentMode === 'publisher_writes' ? (
                                                <span className="rounded-pill bg-teal-subtle px-2 py-0.5 text-xs font-medium text-success">
                                                    We’ll write it
                                                </span>
                                            ) : (
                                                <span className="rounded-pill bg-sunken px-2 py-0.5 text-xs font-medium text-ink-700">
                                                    You’ll upload it
                                                </span>
                                            )}
                                        </td>

                                        <td className="num whitespace-nowrap px-4 py-3 text-right text-ink-900">
                                            {money(item.totalCents)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            ))}

            <p className="text-sm text-ink-500">
                Something wrong?{' '}
                <Link href="/cart" className="font-medium text-brand hover:underline">
                    Go back to the cart
                </Link>{' '}
                to change any of it.
            </p>
        </div>
    );
}
