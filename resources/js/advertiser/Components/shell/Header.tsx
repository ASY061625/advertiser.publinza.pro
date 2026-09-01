import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronRightIcon, HeartIcon, MenuIcon, MoreIcon, SparkleIcon, useDismiss } from '@shared/ui';
import type { Shell, ShellCounts } from '@shared/types/shell';
import type { User } from '@shared/types';
import { BalancePill } from './BalancePill';
import { CartMenu } from './CartMenu';
import { ConversationsMenu } from './ConversationsMenu';
import { HeaderButton } from './HeaderButton';
import { ProfileMenu } from './ProfileMenu';

export interface Crumb {
    label: string;
    href?: string;
}

interface HeaderProps {
    crumbs: Crumb[];
    shell: Shell;
    counts: ShellCounts;
    user: User;
    onOpenWhatsNew: () => void;
    onOpenMobileNav: () => void;
}

export function Header({ crumbs, shell, counts, user, onOpenWhatsNew, onOpenMobileNav }: HeaderProps) {
    const [overflowOpen, setOverflowOpen] = useState(false);
    const overflowRef = useDismiss<HTMLDivElement>(overflowOpen, () => setOverflowOpen(false));

    return (
        <header className="sticky top-0 z-30 flex h-header items-center justify-between gap-4 border-b border-subtle bg-card px-4 lg:px-6">
            <div className="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    onClick={onOpenMobileNav}
                    aria-label="Open menu"
                    className="flex size-9 shrink-0 items-center justify-center rounded-button text-ink-700 hover:bg-sunken lg:hidden"
                >
                    <MenuIcon size={18} />
                </button>

                <nav aria-label="Breadcrumb" className="min-w-0">
                    <ol className="flex min-w-0 items-center gap-1.5">
                        {crumbs.map((crumb, index) => {
                            const last = index === crumbs.length - 1;

                            return (
                                <li key={crumb.label} className="flex min-w-0 items-center gap-1.5">
                                    {index > 0 && (
                                        <ChevronRightIcon
                                            size={14}
                                            className="shrink-0 text-ink-300"
                                            aria-hidden="true"
                                        />
                                    )}

                                    {last || !crumb.href ? (
                                        <span
                                            aria-current={last ? 'page' : undefined}
                                            className="truncate font-sora text-md font-semibold text-ink-900"
                                        >
                                            {crumb.label}
                                        </span>
                                    ) : (
                                        <Link
                                            href={crumb.href}
                                            className="truncate text-base text-ink-500 transition-colors duration-fast hover:text-brand"
                                        >
                                            {crumb.label}
                                        </Link>
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                </nav>
            </div>

            <div className="flex shrink-0 items-center gap-1.5">
                {/* Items 1–4 sit inline from lg and collapse into one overflow
                    menu below it. Balance and profile always stay visible. */}
                <div className="hidden items-center gap-1.5 lg:flex">
                    <HeaderButton
                        label="What's new"
                        dot
                        count={counts.changelog}
                        onClick={onOpenWhatsNew}
                        icon={<SparkleIcon size={18} />}
                    />

                    {/* A link, not a dropdown: it goes straight to the list. */}
                    <Link
                        href="/lists?tab=favorites"
                        aria-label={`Favorites, ${counts.favorites} saved`}
                        className="relative flex size-9 items-center justify-center rounded-button text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-700"
                    >
                        <HeartIcon size={18} />
                        {counts.favorites > 0 && (
                            <span
                                aria-hidden="true"
                                className="num absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-pill bg-brand px-1 text-[10px] font-medium text-white ring-2 ring-card"
                            >
                                {counts.favorites > 99 ? '99+' : counts.favorites}
                            </span>
                        )}
                    </Link>

                    <ConversationsMenu conversations={shell.conversations} unread={counts.conversations} />
                    <CartMenu cart={shell.cart} count={counts.cart} />
                </div>

                <div ref={overflowRef} className="relative lg:hidden">
                    <HeaderButton
                        label="More"
                        dot
                        count={counts.changelog + counts.conversations + counts.cart}
                        expanded={overflowOpen}
                        onClick={() => setOverflowOpen((v) => !v)}
                        icon={<MoreIcon size={18} />}
                    />

                    {overflowOpen && (
                        <div
                            role="menu"
                            className="absolute right-0 z-50 mt-1 w-60 animate-scale-in overflow-hidden rounded-card border border-subtle bg-card py-1 shadow-card"
                        >
                            <button
                                type="button"
                                role="menuitem"
                                onClick={() => {
                                    setOverflowOpen(false);
                                    onOpenWhatsNew();
                                }}
                                className="flex w-full items-center justify-between px-4 py-2.5 text-left text-base text-ink-700 hover:bg-sunken"
                            >
                                What&apos;s new
                                {counts.changelog > 0 && <span className="size-2 rounded-pill bg-brand" />}
                            </button>

                            {[
                                { label: 'Favorites', href: '/lists?tab=favorites', count: counts.favorites },
                                { label: 'Conversations', href: '/messages', count: counts.conversations },
                                { label: 'Cart', href: '/cart', count: counts.cart },
                            ].map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    role="menuitem"
                                    onClick={() => setOverflowOpen(false)}
                                    className="flex items-center justify-between px-4 py-2.5 text-base text-ink-700 hover:bg-sunken"
                                >
                                    {item.label}
                                    {item.count > 0 && (
                                        <span className="num rounded-pill bg-brand-subtle px-1.5 text-xs text-brand">
                                            {item.count}
                                        </span>
                                    )}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                <BalancePill availableCents={shell.balance.availableCents} frozenCents={shell.balance.frozenCents} />

                <ProfileMenu user={user} />
            </div>
        </header>
    );
}
