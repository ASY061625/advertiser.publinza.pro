import { Fragment } from 'react';
import { cn } from '@shared/lib/cn';
import { ChevronRightIcon } from './icons';

export interface Crumb {
    label: string;
    href?: string;
}

/** The last crumb is the current page and is never a link. */
export function Breadcrumb({ items, className }: { items: Crumb[]; className?: string }) {
    return (
        <nav aria-label="Breadcrumb" className={className}>
            <ol className="flex flex-wrap items-center gap-1.5 text-base">
                {items.map((item, index) => {
                    const last = index === items.length - 1;

                    return (
                        <Fragment key={item.label}>
                            <li>
                                {last || !item.href ? (
                                    <span
                                        aria-current={last ? 'page' : undefined}
                                        className={cn(last ? 'text-ink-700' : 'text-ink-500')}
                                    >
                                        {item.label}
                                    </span>
                                ) : (
                                    <a
                                        href={item.href}
                                        className="rounded-button text-ink-500 transition-colors duration-fast hover:text-brand"
                                    >
                                        {item.label}
                                    </a>
                                )}
                            </li>
                            {!last && (
                                <li aria-hidden="true" className="text-ink-300">
                                    <ChevronRightIcon size={14} />
                                </li>
                            )}
                        </Fragment>
                    );
                })}
            </ol>
        </nav>
    );
}
