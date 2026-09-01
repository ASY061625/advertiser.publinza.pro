import { useState, type ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { money } from '@shared/lib/format';
import type { AdvertiserSharedProps } from '@shared/types';

const NAV = [
    { label: 'Catalog', href: '/catalog' },
    { label: 'Projects', href: '/projects' },
    { label: 'Posts', href: '/posts' },
    { label: 'Messages', href: '/messages' },
    { label: 'Billing', href: '/billing' },
];

export function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const { props, url } = usePage<AdvertiserSharedProps>();
    const [collapsed, setCollapsed] = useState(false);

    return (
        <div className="min-h-screen bg-canvas">
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-40 flex flex-col border-r border-ink-300 bg-card',
                    collapsed ? 'w-sidebar-collapsed' : 'w-sidebar',
                )}
            >
                <div className="flex h-header items-center gap-2 px-5 font-sora text-md font-semibold text-ink-900">
                    {collapsed ? 'P' : 'Publinza'}
                </div>
                <nav className="flex flex-1 flex-col gap-1 px-3 py-4">
                    {NAV.map((item) => {
                        const active = url.startsWith(item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'rounded-button px-3 py-2 font-sora text-base font-medium',
                                    active ? 'bg-brand-subtle text-brand' : 'text-ink-700 hover:bg-sunken',
                                )}
                            >
                                {collapsed ? item.label.charAt(0) : item.label}
                            </Link>
                        );
                    })}
                </nav>
                <button
                    type="button"
                    onClick={() => setCollapsed((value) => !value)}
                    className="border-t border-ink-300 px-4 py-3 text-left text-sm text-ink-500 hover:text-ink-700"
                >
                    {collapsed ? 'Expand' : 'Collapse sidebar'}
                </button>
            </aside>

            <div className={cn(collapsed ? 'pl-sidebar-collapsed' : 'pl-sidebar')}>
                <header className="sticky top-0 z-30 flex h-header items-center justify-between border-b border-ink-300 bg-card px-6">
                    <h1 className="font-sora text-lg font-semibold text-ink-900">{title}</h1>
                    <div className="flex items-center gap-4">
                        <span className="tabular rounded-pill bg-gold-subtle px-3 py-1 text-sm text-[#B45309]">
                            Balance {money(props.balanceMinorUnits)}
                        </span>
                        <span className="text-sm text-ink-500">{props.auth.user?.name}</span>
                    </div>
                </header>

                <main className="mx-auto max-w-content px-6 py-6">{children}</main>
            </div>
        </div>
    );
}
